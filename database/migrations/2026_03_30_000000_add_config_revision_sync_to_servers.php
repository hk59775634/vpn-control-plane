<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('servers')) {
            return;
        }
        if (! Schema::hasColumn('servers', 'config_revision_ts')) {
            Schema::table('servers', function (Blueprint $table) {
                $table->unsignedBigInteger('config_revision_ts')->default(0);
            });
        }
        if (! Schema::hasColumn('servers', 'agent_reported_config_ts')) {
            Schema::table('servers', function (Blueprint $table) {
                $table->unsignedBigInteger('agent_reported_config_ts')->nullable();
            });
        }

        if (Schema::hasColumn('servers', 'config_revision_ts') && DB::getDriverName() === 'mysql') {
            DB::statement('UPDATE servers SET config_revision_ts = UNIX_TIMESTAMP(updated_at) WHERE config_revision_ts = 0');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('servers')) {
            return;
        }
        foreach (['agent_reported_config_ts', 'config_revision_ts'] as $col) {
            if (Schema::hasColumn('servers', $col)) {
                Schema::table('servers', function (Blueprint $table) use ($col) {
                    $table->dropColumn($col);
                });
            }
        }
    }
};
