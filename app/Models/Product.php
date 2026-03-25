<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'name',
        'description',
        'price_cents',
        'currency',
        'duration_days',
        'enable_radius',
        'enable_wireguard',
        'requires_dedicated_public_ip',
        'bandwidth_limit_kbps',
        'traffic_quota_bytes',
    ];

    protected $attributes = [
        'currency' => 'USD',
        'duration_days' => 30,
        'enable_radius' => true,
        'enable_wireguard' => true,
        'requires_dedicated_public_ip' => false,
    ];
}
