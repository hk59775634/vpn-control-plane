<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ProvisionResourceAuditLog extends Model
{
    public $timestamps = false;

    protected $table = 'provision_resource_audit_logs';

    protected $fillable = [
        'vpn_user_id',
        'order_id',
        'product_id',
        'reseller_id',
        'event',
        'meta',
        'created_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function record(
        string $event,
        ?int $vpnUserId = null,
        ?int $orderId = null,
        ?int $productId = null,
        ?int $resellerId = null,
        array $meta = []
    ): void {
        DB::table('provision_resource_audit_logs')->insert([
            'vpn_user_id' => $vpnUserId,
            'order_id' => $orderId,
            'product_id' => $productId,
            'reseller_id' => $resellerId,
            'event' => $event,
            'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
        ]);
    }
}
