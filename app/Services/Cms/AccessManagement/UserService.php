<?php

namespace App\Services\Cms\AccessManagement;

use App\Models\AccessManagement\Role;
use App\Models\AccessManagement\User;
use App\Services\Cms\CrudService;
use App\Services\Hooks\AccessManagement\Users\SyncRolesHook;
use App\Services\ResultService;
use Illuminate\Http\Request;

class UserService extends CrudService
{
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
}
