<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 对应 1.0 0002_server_heartbeat.sql + 0003_server_metrics.sql
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->timestamp('last_heartbeat_at')->nullable()->after('updated_at');
            $table->double('cpu_percent')->nullable();
            $table->double('mem_percent')->nullable();
            $table->double('load_1')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn(['last_heartbeat_at', 'cpu_percent', 'mem_percent', 'load_1']);
        });
    }
};
