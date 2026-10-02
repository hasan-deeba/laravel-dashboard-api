<?php

namespace App\Services\Hooks\AccessManagement\Roles;

use Exception;

class BeforeDeleteRoleHook
{
    protected array $protectedRoles;

    public function __construct(array $protectedRoles = ['super_admin', 'admin'])
    {
        $this->protectedRoles = $protectedRoles;
    }

    public function __invoke($item): void
    {
        if (in_array($item->name, $this->protectedRoles, true)) {
            throw new Exception("System role '{$item->name}' is protected and cannot be deleted.");
        }
    }
}
