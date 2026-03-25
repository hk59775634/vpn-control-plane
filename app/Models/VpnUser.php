<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VpnUser extends Model
{
    protected $table = 'vpn_users';

    protected $fillable = [
        'user_id',
        'email',
        'reseller_id',
        'region',
        'name',
        'status',
        'radius_username',
        'radius_password',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(Reseller::class);
    }

    public function orders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Order::class, 'vpn_user_id');
    }
}

