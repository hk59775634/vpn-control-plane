<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IpPool extends Model
{
    protected $table = 'ip_pool';

    protected $fillable = ['ip_address', 'region', 'status', 'created_by', 'vpn_user_id', 'last_unbound_at', 'server_id'];

    protected $attributes = [
        'status' => 'free',
    ];

    protected $casts = [
        'last_unbound_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function vpnUser(): BelongsTo
    {
        return $this->belongsTo(VpnUser::class, 'vpn_user_id');
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class, 'server_id');
    }
}
