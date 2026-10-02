<?php

namespace App\Traits;

trait AuthorizesPermissions
{
    protected string $permissionPrefix;

    /**
     * Map controller methods to permission actions.
     *
     * @var array<string, string>
     */
    protected array $permissionMap = [
        'index' => 'view',
        'builder' => 'view',
        'all' => 'view',
        'view' => 'view',
        'show' => 'view',
        'store' => 'create',
        'update' => 'edit',
        'updateRoles' => 'edit',
        'toggleActive' => 'edit',
        'toggleActiveStatus' => 'edit',
        'delete' => 'delete',
        'destroy' => 'delete',
    ];

    public function callAction($method, $parameters)
    {
        if (isset($this->permissionPrefix)) {
            $action = $this->permissionMap[$method] ?? null;

            if ($action !== null) {
                $this->authorize("{$this->permissionPrefix}.{$action}");
            }
        }

        return parent::callAction($method, $parameters);
    }
}
