<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            if (!Schema::hasColumn('servers', 'wg_public_key')) {
                $table->string('wg_public_key', 191)->nullable()->after('vpn_ip_cidrs')->comment('WireGuard 服务器公钥');
            }
            if (!Schema::hasColumn('servers', 'wg_port')) {
                $table->unsignedSmallInteger('wg_port')->nullable()->after('wg_public_key')->comment('WireGuard 端口（默认 51820）');
            }
            if (!Schema::hasColumn('servers', 'wg_dns')) {
                $table->string('wg_dns', 191)->nullable()->after('wg_port')->comment('客户端 DNS（可选）');
            }
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            foreach (['wg_dns', 'wg_port', 'wg_public_key'] as $c) {
                if (Schema::hasColumn('servers', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};

