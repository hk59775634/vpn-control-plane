<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 同一邮箱在同一分销商下可对应多条 vpn_users（每已购产品/订单一条），以便每订阅独立 WireGuard。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vpn_users', function (Blueprint $table) {
            $table->dropUnique('vpn_users_email_reseller_id_unique');
        });
        Schema::table('vpn_users', function (Blueprint $table) {
            $table->index(['email', 'reseller_id'], 'vpn_users_email_reseller_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('vpn_users', function (Blueprint $table) {
            $table->dropIndex('vpn_users_email_reseller_id_index');
        });
        Schema::table('vpn_users', function (Blueprint $table) {
            $table->unique(['email', 'reseller_id'], 'vpn_users_email_reseller_id_unique');
        });
    }
};
