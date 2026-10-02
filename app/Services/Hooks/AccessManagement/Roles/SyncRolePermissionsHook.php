<?php

namespace App\Services\Hooks\AccessManagement\Roles;

class SyncRolePermissionsHook
{
    public function __invoke($role, array $data, bool $isCreated): void
    {
        if (isset($data['permissions']) && is_array($data['permissions'])) {
            $permissions = array_map('intval', $data['permissions'] ?? []);

            $role->syncPermissions($permissions);
        }
    }
}
