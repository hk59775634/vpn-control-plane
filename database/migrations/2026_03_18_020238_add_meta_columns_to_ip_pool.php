<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ip_pool', function (Blueprint $table) {
            // 创建人（管理员），nullable，删除用户时置空
            $table->foreignId('created_by')
                ->nullable()
                ->after('status')
                ->constrained('users')
                ->nullOnDelete();

            // 当前绑定的 VPN 账号（若有）
            $table->foreignId('vpn_user_id')
                ->nullable()
                ->after('created_by')
                ->constrained('vpn_users')
                ->nullOnDelete();

            // 上次解除绑定时间
            $table->timestamp('last_unbound_at')->nullable()->after('vpn_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ip_pool', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropForeign(['vpn_user_id']);
            $table->dropColumn(['created_by', 'vpn_user_id', 'last_unbound_at']);
        });
    }
};
