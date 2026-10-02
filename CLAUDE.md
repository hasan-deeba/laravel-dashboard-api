# HeroDash — AI Agent Guide

Read this file **before editing anything**. It describes the architecture, the
conventions, and the exact recipes for common tasks. Following it prevents the
mistakes that break this codebase.

---

## 1. Stack

| Concern   | Choice     |
|-----------|------------|
| Framework | Laravel 12 |
| Frontend  | React 19   |

---

## 2. Golden rules

1. **Never edit `CrudController` or `CrudService`.** New behavior goes through
   hooks, Form Requests, or service configuration — never by modifying the base classes.
2. **Controllers extend `CrudController`, Services extend `CrudService`** — no exceptions.
3. **All validation lives in Form Requests**, never in controllers or services.
4. **Every service/business function returns a `ResultService`** object
   (see 4.4). Controllers are the only layer that returns HTTP responses.
5. **Follow the `User` module as the reference implementation** before
   inventing a new pattern.
6. Before creating any new abstraction, check if `CrudService` already handles it.

## 3. Directory map

```
app/
├── Models/{Module}/              e.g. Models/Products/Product.php
├── Http/
│   ├── Controllers/Cms/{Module}/   must extend CrudController
│   └── Requests/Cms/{Module}/      Create/Update Form Requests
├── Services/
│   ├── Cms/{Module}/               must extend CrudService
│   └── Hooks/{Module}/             afterSave / beforeSave / beforeDelete hooks
└── ...
routes/cms/{module}.php             all module routes (Route::resource)
```

---

## 4. Recipe — add a new CRUD module for the dashboard (e.g. `products`)

Copy the `User` module as the reference implementation — it exercises every
feature (Model, Request, Controller, Service, Route).

### 4.1 Model → `app/Models/{Module}/`

- **`$minimumAllowedKey`** (array): the columns returned to the frontend
  dashboard table, e.g. `['id', 'name', 'created_at']`.
- **`$specialFields`** (array): columns that need special treatment before
  being sent to the frontend — not only dates. Example: `created_at` is
  included so it can be transformed into a human-readable date for the UI.
- **`$logExcept`** (array): columns excluded from the change-log table
  (e.g. `updated_at`, `remember_token` in `User`).

**Relations — create them automatically, both directions.** When the task
mentions two related models, add the relation to BOTH models without being asked:

- User + Role (pivot `role_user` or `users_roles` per migration) →
  `roles()` in `User`, `users()` in `Role`.
- Product + Category → `category()` in `Product`, `products()` in `Category`.

Then add the relation **name** (as defined on the model) to the service's
`advanced` search columns (see 4.4).

### 4.2 Form Requests → `app/Http/Requests/Cms/{Module}/`

- Create `Create{Model}Request` and `Update{Model}Request`.
- If the rules are identical, create **only** the Create request.

### 4.3 Controller → `app/Http/Controllers/Cms/{Module}/`

- Must **always** extend `CrudController`. Keep it thin — no business logic.

### 4.4 Service → `app/Services/Cms/{Module}/`

- Must **always** extend `CrudService`.
- **Every function in any service returns a `ResultService`** (except
  controllers and places that must not). Shape:

```php
class ResultService
{
    public bool $valid;
    public int $code;
    public string $message;
    public ?array $item;

    public function __construct($valid = true, $code = 200, $message = 'success', $item = null)
    {
        $this->message = $message;
        $this->valid = $valid;
        $this->code = $code;
        $this->item = $item;
    }
}
```

**Builder function** — add it only when the table or form needs extra data
(e.g. roles dropdown in the User form, categories filter on the Product table):

```php
public function builder(Request $request): ResultService
{
    $roles = Role::all();

    return new ResultService(
        valid: true,
        code: 200,
        message: 'Success',
        item: [
            'roles' => app(Role::class)->transformList($roles),
        ]
    );
}
```

**Constructor configuration** — reference example (from `UserService`):

```php
public function __construct()
{
    parent::__construct(User::class);

    $this->searchColumns(
        normal: ['name', 'email'],
        advanced: ['name', 'email', 'created_at', 'is_active', 'roles']
    )
    ->orderBy(['id'], 'desc')
    ->report(
        headings: ['#ID', 'Name', 'Email', 'Date'],
        fields: ['users.id', 'users.name', 'users.email', 'users.created_at']
    )
    ->afterSave([
        new SyncRolesHook(),
    ]);
}
```

Rules for each option:

1. **parent::__construct(Model::class)** — pass the module's model.
2. **normal** — only plain text-searchable columns, for the text-input search.
   Never put relational columns here.
3. **advanced** — all searchable/filterable columns. For relations, use the
   relation **name as defined on the model** (e.g. `roles`, `category`), not the column name.
4. **orderBy** — the default sort of the index table.
5. **report** — headings and fields for the export feature.
6. **afterSave** — hooks for side effects after create/update: syncing pivot
   tables, special-case image/file handling, or anything `CrudService` doesn't
   handle normally. Create a dedicated hook class per side effect, stored in
   `app/Services/Hooks/{Module}/`, to keep the service clean.
7. **beforeSave** — hooks that can **block** create/update with a condition
   that cannot live in Form Request validation.
8. **beforeDelete** — conditions that **block** deletion.
9. **deleteOptions** — the model's child relations; an item cannot be deleted
   while children exist. Syntax: `->deleteOptions(['orders'])`.

**Image/file upload** — handled automatically by `CrudService` in the normal
case. Only add a custom `afterSave` hook when the task explicitly describes a
special upload case.

### 4.5 Routes → `routes/cms/{module}.php`

- Register all module routes here (product resource, category resource, …).
- Always prefer `Route::resource` where possible.

---

## 5. Common mistakes

- Editing `CrudController`/`CrudService` directly instead of using hooks or config → never do this; the fix belongs in a hook.
- Adding a relational column to `normal` search (normal = text input only).
- Forgetting to add the relation to BOTH models when creating related resources.
- Creating a separate Update request when the rules are identical to Create → use the Create request only.
- Returning raw arrays from services instead of `ResultService`.
- Forgetting `deleteOptions` on a model with children → deletion silently succeeds when it shouldn't (or crashes).
- Putting business logic in the controller instead of the service/hooks.
