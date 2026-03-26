<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('servers') || Schema::hasColumn('servers', 'online_sessions')) {
            return;
        }
        Schema::table('servers', function (Blueprint $table) {
            $table->json('online_sessions')->nullable()->after('online_users');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('servers') || ! Schema::hasColumn('servers', 'online_sessions')) {
            return;
        }
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn('online_sessions');
        });
    }
};

