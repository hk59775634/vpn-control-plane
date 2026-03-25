<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vpn_users', function (Blueprint $table) {
            if (!Schema::hasColumn('vpn_users', 'radius_username')) {
                $table->string('radius_username', 64)->nullable()->after('name');
            }
            if (!Schema::hasColumn('vpn_users', 'radius_password')) {
                $table->string('radius_password', 191)->nullable()->after('radius_username');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vpn_users', function (Blueprint $table) {
            if (Schema::hasColumn('vpn_users', 'radius_password')) {
                $table->dropColumn('radius_password');
            }
            if (Schema::hasColumn('vpn_users', 'radius_username')) {
                $table->dropColumn('radius_username');
            }
        });
    }
};

