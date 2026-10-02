<?php

namespace App\Services\Hooks\AccessManagement\Roles;

use Exception;

class BeforeSaveRoleHook
{
    protected array $protectedRoles;

    public function __construct(array $protectedRoles = ['super_admin'])
    {
        $this->protectedRoles = $protectedRoles;
    }

    public function __invoke(array $data, $item, bool $isCreated): array
    {
        if (!$isCreated && $item && in_array($item->name, $this->protectedRoles, true)) {
            if (isset($data['name']) && $data['name'] !== $item->name) {
                throw new Exception(__('messages.Cannot modify admin role name'));
            }
        }

        return $data;
    }
}
