<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('servers') || Schema::hasColumn('servers', 'online_users')) {
            return;
        }
        Schema::table('servers', function (Blueprint $table) {
            $table->unsignedInteger('online_users')->nullable()->after('load_1');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('servers') || ! Schema::hasColumn('servers', 'online_users')) {
            return;
        }
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn('online_users');
        });
    }
};

