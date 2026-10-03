<?php

namespace App\Models\AccessManagement;

use App\Helpers\AppHelper;
use App\Traits\ActivityLogsTrait;
use App\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, ModelTrait, ActivityLogsTrait;

    protected $fillable = ['name', 'email', 'password', 'image', 'is_active',];

    protected $hidden = ['password', 'remember_token',];
    protected $guard_name = 'cms';

    protected $minimumAllowedKey = ['id', 'name', 'email', 'is_super_admin', 'is_active',  'image', 'roles', 'created_at'];
    protected $notAllowedKey = ['password', 'created_at', 'updated_at', 'remember_token',];
    protected $specialFields = [];
    protected $extraFields = [];
    protected $logExcept = ['password', 'remember_token', 'updated_at', 'email_verified_at'];

    public function __construct(array $attributes = array())
    {
        $this->extraFields = [
            'is_super_admin' => function () {
                return $this->hasRole('super_admin');
            },
            'roles' => function () {
                return $this->roles->map(fn($role) => [
                    'id' => $role->id,
                    'name' => $role->name,
                ]);
            },
            'role_ids' => function () {
                return $this->roles->pluck('id')->toArray();
            },
            'permissions' => function () {
                return $this->getAllPermissions()->map(fn($perm) => [
                    'id' => $perm->id,
                    'name' => $perm->name,
                ]);
            },
        ];
        parent::__construct($attributes);
    }
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get all permissions as a flat array
     */
    public function getAllPermissionsArray(): array
    {
        return $this->getAllPermissions()->pluck('name')->toArray();
    }

    /**
     * Get role names as array
     */
    public function getRoleNamesArray(): array
    {
        return $this->getRoleNames()->toArray();
    }

    /**
     * Check if user is admin (has 'admin' role)
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }
}
