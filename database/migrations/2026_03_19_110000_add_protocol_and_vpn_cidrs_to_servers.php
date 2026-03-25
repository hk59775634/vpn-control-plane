<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            if (!Schema::hasColumn('servers', 'protocol')) {
                $table->string('protocol', 32)->nullable()->after('role')->comment('协议类型：wireguard/openvpn/other');
            }
            if (!Schema::hasColumn('servers', 'vpn_ip_cidrs')) {
                $table->text('vpn_ip_cidrs')->nullable()->after('protocol')->comment('VPN 内网 IP 范围，逗号分隔 CIDR，例如 10.66.0.0/24,10.66.1.0/24');
            }
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            if (Schema::hasColumn('servers', 'vpn_ip_cidrs')) {
                $table->dropColumn('vpn_ip_cidrs');
            }
            if (Schema::hasColumn('servers', 'protocol')) {
                $table->dropColumn('protocol');
            }
        });
    }
};

