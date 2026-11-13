<?php

namespace App\Models\Clients;

use App\Enum\GenderType;
use App\Enum\MaritalStatusType;
use App\Models\General\Notification;
use App\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Client extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, ModelTrait;

    protected $fillable = [
        'first_name',
        'last_name',
        'company_name',
        'age',
        'gender',
        'marital_status',
        'email',
        'password',
        'email_verified_at',
        'is_active',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $minimumAllowedKey = [
        'id', 'first_name', 'last_name', 'full_name', 'company_name', 'age',
        'gender', 'marital_status', 'email', 'is_active', 'is_verified', 'created_at',
    ];
    protected $notAllowedKey = ['password', 'remember_token', 'updated_at', 'email_verified_at'];
    protected $specialFields = [];
    protected $extraFields = [];

    public function __construct(array $attributes = array())
    {
        $this->specialFields = [
            'gender' => function () {
                return $this->gender ? [
                    'value' => $this->gender->value,
                    'name' => GenderType::label($this->gender),
                    'color' => GenderType::color($this->gender),
                ] : null;
            },
            'marital_status' => function () {
                return $this->marital_status ? [
                    'value' => $this->marital_status->value,
                    'name' => MaritalStatusType::label($this->marital_status),
                    'color' => MaritalStatusType::color($this->marital_status),
                ] : null;
            },
        ];

        $this->extraFields = [
            'full_name' => function () {
                return trim("{$this->first_name} {$this->last_name}");
            },
            'is_verified' => function () {
                return !is_null($this->email_verified_at);
            },
        ];
        parent::__construct($attributes);
    }

    public function notifications(): MorphMany
    {
        return $this->morphMany(Notification::class, 'notifiable')->latest();
    }

    public function devices(): HasMany
    {
        return $this->hasMany(ClientDevice::class);
    }

    protected function casts(): array
    {
        return [
            'gender' => GenderType::class,
            'marital_status' => MaritalStatusType::class,
            'age' => 'integer',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }
}
