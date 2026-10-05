# laravel-dashboard-api
Easy To Use Dashboard
# Laravel Dashboard API

A Laravel 12 backend for a modular dashboard/CMS. The project provides shared CRUD infrastructure for dashboard resources, with module-specific configuration and hooks so common behavior does not need to be copied into every controller and service.

## What it includes

- A shared `CrudController` and `CrudService` for common CRUD workflows.
- Search/filter configuration, sorting, pagination, and report-column configuration.
- `beforeSave`, `afterSave`, and `beforeDelete` hooks for resource-specific business logic.
- CMS routes protected by Laravel Sanctum and permission checks using Spatie Laravel Permission.
- Model response transformation and default timestamp formatting through `ModelTrait`.
- Activity logging support through `ActivityLogsTrait`.
- An Artisan `make:module` command to scaffold a complete dashboard module.

## Requirements and setup

- PHP 8.2 or later
- Composer
- A database supported by Laravel (SQLite is the configured default)
- Node.js and npm if you need to build the frontend assets

Install the PHP dependencies, create a local `.env` file with your application and database settings, then run the migrations and seeders:

```bash
composer install
php artisan key:generate
php artisan migrate --seed
```

This repository does not currently include an `.env.example`; configure your local environment before running Artisan commands. The default database connection is SQLite, configured in `config/database.php`.

To build the frontend assets, if needed:

```bash
npm install
npm run build
```

## Module architecture

A module keeps the files for one dashboard resource together and follows the same general flow:

1. A **FormRequest** validates incoming data.
2. A module **Controller** extends `CrudController` and identifies the requests and permission prefix it uses.
3. A module **Service** extends `CrudService`, selects its Eloquent model, and configures search, ordering, report fields, and any hooks.
4. A **Model** defines its fillable fields and uses the shared model/activity-log traits.
5. A **routes file** registers the CMS endpoints.

The shared controller and service handle the repeated workflow; the module's service and hooks are where resource-specific behavior belongs. See `app/Services/Cms/AccessManagement/UserService.php` and `RoleService.php` for examples of service configuration and hooks.

## Create a new module

The `make:module` command can be used interactively or with command-line options.

### Interactive mode

Start the command and follow the prompts:

```bash
php artisan make:module
```

Or provide the model name and let the command prompt for the remaining details:

```bash
php artisan make:module Product
```

The command asks for the module folder, migration columns, and the model's `$minimumAllowedKey`, `$notAllowedKey`, and `$logExcept` values. If no columns are provided, the migration contains Laravel's default `id` and `timestamps` columns.

### Command-line mode

For example, create a `Product` model in the `Catalog` folder with custom migration columns:

```bash
php artisan make:module Product \
  --folder=Catalog \
  --columns="name:string,sku:string:unique,description:text:nullable,price:decimal(10,2):nullable,is_active:boolean:default(true)" \
  --min-allowed="id,name,sku,price,created_at" \
  --not-allowed="id" \
  --log-except="id,created_at"
```

To run without prompts, pass `--no-interaction` and provide the values you want to customize:

```bash
php artisan make:module Product \
  --folder=Catalog \
  --columns="name:string,price:decimal(10,2)" \
  --no-interaction
```

You can also include the folder in the name, such as `php artisan make:module Catalog/Product`.

### Command inputs

| Argument / option | Description |
| --- | --- |
| `name` | Optional model name, or a folder/model path such as `Catalog/Product`. |
| `--folder=` | Module folder/namespace. If omitted, the command uses the plural model name. |
| `--columns=` | Comma-separated migration columns in `name:type[:modifier...]` format. |
| `--min-allowed=` | Comma-separated `$minimumAllowedKey` values. Defaults to `id,created_at`. Alias: `--minimum-allowed-key=`. |
| `--not-allowed=` | Comma-separated `$notAllowedKey` values. Defaults to `id,created_at`. Alias: `--not-allowed-key=`. |
| `--log-except=` | Comma-separated `$logExcept` values. Defaults to `id,created_at`. |
| `--force` | Overwrite existing generated files. Use carefully. |
| `--no-interaction` | Disable prompts (Laravel Artisan option). |

Use `none` for an empty `$minimumAllowedKey`, `$notAllowedKey`, or `$logExcept` list. If a column type is omitted, it defaults to `string`. Column types and modifiers are written as Laravel schema-builder calls; for example:

- `name:string`
- `description:text:nullable`
- `price:decimal(10,2):nullable`
- `sku:string:unique`
- `is_active:boolean:default(true)`

The command always creates `id` and `timestamps` in the migration. Do not include `id`, `created_at`, or `updated_at` in `--columns`; those names are reserved by the generated migration.

### Files created for `Product` in `Catalog`

The command creates the module files and updates the route bootstrap and permission seeder:

```text
database/migrations/*_create_products_table.php
app/Models/Catalog/Product.php
app/Http/Requests/Cms/Catalog/CreateProductRequest.php
app/Http/Controllers/Cms/Catalog/ProductController.php
app/Services/Cms/Catalog/ProductService.php
routes/cms/catalog.php
```

It also registers the new route file in `bootstrap/app.php` and adds the module's permissions to `database/seeders/RolePermissionSeeder.php`.

### What the generated files contain

- **Migration:** `id`, the supplied columns, and `created_at` / `updated_at` timestamps. With no supplied columns, it contains only `id` and timestamps.
- **Model:** `$fillable` is populated from the supplied column names. It uses `HasFactory`, `ModelTrait`, and `ActivityLogsTrait`; the key lists default to `['id', 'created_at']`.
- **FormRequest:** creates a `CreateProductRequest` with a `sometimes` rule for each supplied column. Update the rules to match the validation and required fields for your resource before relying on them.
- **Controller:** extends `CrudController`, sets the permission prefix, and uses the generated `CreateProductRequest` for both create and update requests.
- **Service:** extends `CrudService` and configures searchable columns, default ordering, and report headings/fields based on the supplied columns.
- **Routes:** adds the builder, resource CRUD, and toggle-active routes under the CMS prefix with `auth:sanctum` middleware.
- **Permissions:** creates `{table}.view`, `{table}.create`, `{table}.edit`, and `{table}.delete` entries in the seeder. The command also syncs them to the database when the permission tables are available, and assigns them to an existing `super_admin` role on the `cms` guard.

For a `Product` model, the generated permission names are `products.view`, `products.create`, `products.edit`, and `products.delete`.

### After scaffolding

1. Review the generated migration and FormRequest. Add any required, unique, or type-specific validation rules your module needs.
2. Add relationships, casts, custom model fields, and module-specific service behavior.
3. Run the migration and seed the permissions for the current environment:

   ```bash
   php artisan migrate
   php artisan db:seed --class=RolePermissionSeeder
   ```

The command adds the permission definitions to the seeder, so the permissions can be recreated in other environments as well.

## Adding custom CRUD hooks

A module service can register hooks while configuring the shared CRUD workflow:

```php
$this->beforeSave([
    NormalizeProductDataHook::class,
])
    ->afterSave([
        SyncProductInventoryHook::class,
    ])
    ->beforeDelete([
        EnsureProductCanBeDeletedHook::class,
    ]);
```

Hooks can be invokable classes or container-resolvable class names. A `beforeSave` hook receives the input data, the existing model (or `null` on create), and an `isCreated` boolean; it can return modified data. An `afterSave` hook receives the saved model, input data, and `isCreated`. A `beforeDelete` hook receives the model being deleted.

## CMS routes and permissions

Generated routes are loaded under the `/cms` prefix and protected by `auth:sanctum`. A module's resource URL is based on its pluralized model name (for example, `Product` uses `/cms/products`). The command registers the route file in `bootstrap/app.php` and sets the controller's permission prefix to the generated table name.

The default CRUD permissions are:

```text
products.view
products.create
products.edit
products.delete
```

The generator adds these to `RolePermissionSeeder.php`. If the permissions table exists when the command runs, it also creates the permissions immediately and grants them to an existing `super_admin` role using the `cms` guard.
