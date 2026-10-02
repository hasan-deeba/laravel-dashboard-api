<?php

namespace App\Http\Controllers\Cms\AccessManagement;

use App\Http\Controllers\CrudController;
use App\Http\Requests\Cms\AccessManagement\Roles\CreateRoleRequest;
use App\Services\Cms\AccessManagement\RoleService;

class RoleController extends CrudController
{
    protected string $permissionPrefix = 'roles';

    public function __construct(RoleService $roleService)
    {
        $this->createRequest = CreateRoleRequest::class;
        $this->updateRequest = CreateRoleRequest::class;;

        parent::__construct($roleService);
    }
}
