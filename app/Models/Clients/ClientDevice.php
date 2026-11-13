<?php

namespace App\Models\Clients;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientDevice extends Model
{
    protected $fillable = ['client_id', 'token', 'platform'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
