<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'bandwidth_limit_kbps')) {
                $table->unsignedInteger('bandwidth_limit_kbps')->nullable()->after('requires_dedicated_public_ip')
                    ->comment('每用户对称带宽上限（Kbps），NULL 表示不限');
            }
            if (! Schema::hasColumn('products', 'traffic_quota_bytes')) {
                $table->unsignedBigInteger('traffic_quota_bytes')->nullable()->after('bandwidth_limit_kbps')
                    ->comment('当前有效订单周期内流量配额（字节），NULL 表示不限');
            }
        });

        if (! Schema::hasTable('agent_peer_transfer_state')) {
            Schema::create('agent_peer_transfer_state', function (Blueprint $table) {
                $table->id();
                $table->foreignId('server_id')->constrained()->cascadeOnDelete();
                $table->string('public_key', 255);
                $table->unsignedBigInteger('last_rx')->default(0);
                $table->unsignedBigInteger('last_tx')->default(0);
                $table->timestamps();
                $table->unique(['server_id', 'public_key'], 'agent_peer_transfer_server_pubkey_uq');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_peer_transfer_state');

        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'traffic_quota_bytes')) {
                $table->dropColumn('traffic_quota_bytes');
            }
            if (Schema::hasColumn('products', 'bandwidth_limit_kbps')) {
                $table->dropColumn('bandwidth_limit_kbps');
            }
        });
    }
};
