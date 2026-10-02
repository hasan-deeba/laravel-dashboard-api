<?php

namespace App\Models\AccessManagement;

use App\Enum\OTPType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UsersOtpCode extends Model
{
    use HasFactory;

    protected $fillable = ['email', 'guard', 'code', 'type', 'expires_at'];

    protected $casts = [
        'expires_at' => 'datetime',
        'type' => OTPType::class
    ];

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
