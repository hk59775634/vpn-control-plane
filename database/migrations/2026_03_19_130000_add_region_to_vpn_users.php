<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vpn_users', function (Blueprint $table) {
            if (!Schema::hasColumn('vpn_users', 'region')) {
                $table->string('region', 64)->nullable()->after('reseller_id')->comment('用户选择的区域/线路，例如 CN-HK');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vpn_users', function (Blueprint $table) {
            if (Schema::hasColumn('vpn_users', 'region')) {
                $table->dropColumn('region');
            }
        });
    }
};

