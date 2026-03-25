<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'enable_radius')) {
                $table->boolean('enable_radius')->default(true)->after('duration_days')->comment('是否开通 FreeRADIUS');
            }
            if (!Schema::hasColumn('products', 'enable_wireguard')) {
                $table->boolean('enable_wireguard')->default(true)->after('enable_radius')->comment('是否开通 WireGuard');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'enable_wireguard')) {
                $table->dropColumn('enable_wireguard');
            }
            if (Schema::hasColumn('products', 'enable_radius')) {
                $table->dropColumn('enable_radius');
            }
        });
    }
};

