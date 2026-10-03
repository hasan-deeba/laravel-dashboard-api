<?php

namespace App\Models\AccessManagement;

use App\Helpers\AppHelper;
use App\Traits\ActivityLogsTrait;
use App\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use ModelTrait, ActivityLogsTrait;

    protected $guard_name = 'cms';
    protected $minimumAllowedKey = ['id', 'name', 'permissions', 'users', 'created_at', 'updated_at'];
    protected $notAllowedKey = [];
    protected $specialFields = [];
    protected $extraFields = [];
    protected $logExcept = ['guard_name'];

    public function __construct(array $attributes = array())
    {
        $this->extraFields = [
            'permissions' => function () {
                return $this->permissions->map(fn($perm) => [
                    'id' => $perm->id,
                    'name' => $perm->name,
                ]);
            },
            'users' => function () {
                return app(User::class)->transformList($this->users, ['id', 'name', 'email', 'is_active']);
            },
        ];
        parent::__construct($attributes);
    }

    public function users(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(User::class, config('permission.table_names')['model_has_roles'], config('permission.column_names')['role_pivot_key'] ?? 'role_id', config('permission.column_names')['model_morph_key'] ?? 'model_id');
    }
}
