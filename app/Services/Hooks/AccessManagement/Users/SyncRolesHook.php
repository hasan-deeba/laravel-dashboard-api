<?php

namespace App\Services\Hooks\AccessManagement\Users;

use App\Models\AccessManagement\Role;

class SyncRolesHook
{
    public function __invoke($item, array $data, bool $isCreated): void
    {
        if (array_key_exists('roles', $data)) {
            $roleIds = (array) ($data['roles'] ?? []);
            $roles = Role::whereIn('id', $roleIds)->get();
            $item->syncRoles($roles);
        }
    }
}
