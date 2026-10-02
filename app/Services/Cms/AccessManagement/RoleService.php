<?php

namespace App\Services\Cms\AccessManagement;

use App\Models\AccessManagement\Role;
use App\Services\Cms\CrudService;
use App\Services\Hooks\AccessManagement\Roles\BeforeDeleteRoleHook;
use App\Services\Hooks\AccessManagement\Roles\BeforeSaveRoleHook;
use App\Services\Hooks\AccessManagement\Roles\SyncRolePermissionsHook;
use App\Services\ResultService;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;

class RoleService extends CrudService
{
    public function __construct()
    {
        parent::__construct(Role::class);

        $this->searchColumns(normal: ['name'], advanced: ['name'])
            ->orderBy(['id'], 'desc')
            ->report(
                headings: ['#ID', 'Name'],
                fields: ['roles.id', 'roles.name']
            )
            ->beforeSave([
                new BeforeSaveRoleHook(protectedRoles: ['super_admin']),
            ])
            ->afterSave([
                SyncRolePermissionsHook::class,
            ])
            ->beforeDelete([
                new BeforeDeleteRoleHook(protectedRoles: ['super_admin']),
            ]);
    }

    public function builder(Request $request): ResultService
    {
        $query = Permission::query();

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $permissions = $query->get();

        $grouped = $permissions->groupBy(function ($permission) {
            $parts = explode('.', $permission->name);
            return count($parts) > 1 ? $parts[0] : 'general';
        });

        return new ResultService(
            valid: true,
            code: 200,
            message: 'Success',
            item: [
                'data' => $permissions->map(fn($perm) => [
                    'id' => $perm->id,
                    'name' => $perm->name,
                    'guard_name' => $perm->guard_name,
                    'group' => explode('.', $perm->name)[0] ?? 'general',
                    'created_at' => $perm->created_at,
                ]),
                'grouped' => $grouped->map(fn($group, $key) => [
                    'group' => $key,
                    'permissions' => $group->map(fn($perm) => [
                        'id' => $perm->id,
                        'name' => $perm->name,
                    ])->values(),
                ])->values(),
            ]
        );
    }
}
