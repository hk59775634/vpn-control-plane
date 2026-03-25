<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('servers')) {
            return;
        }
        if (! Schema::hasColumn('servers', 'agent_deploy_status')) {
            Schema::table('servers', function (Blueprint $table) {
                $table->string('agent_deploy_status', 32)->nullable();
            });
        }
        if (! Schema::hasColumn('servers', 'agent_deploy_message')) {
            Schema::table('servers', function (Blueprint $table) {
                $table->text('agent_deploy_message')->nullable();
            });
        }
        if (! Schema::hasColumn('servers', 'agent_deploy_at')) {
            Schema::table('servers', function (Blueprint $table) {
                $table->timestamp('agent_deploy_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('servers')) {
            return;
        }
        foreach (['agent_deploy_at', 'agent_deploy_message', 'agent_deploy_status'] as $col) {
            if (Schema::hasColumn('servers', $col)) {
                Schema::table('servers', function (Blueprint $table) use ($col) {
                    $table->dropColumn($col);
                });
            }
        }
    }
};
