<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            if (!Schema::hasColumn('servers', 'cost_cents')) {
                $table->unsignedBigInteger('cost_cents')->default(0)->after('role');
            }
        });

        Schema::table('exit_nodes', function (Blueprint $table) {
            if (!Schema::hasColumn('exit_nodes', 'cost_cents')) {
                $table->unsignedBigInteger('cost_cents')->default(0)->after('region');
            }
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            if (Schema::hasColumn('servers', 'cost_cents')) {
                $table->dropColumn('cost_cents');
            }
        });

        Schema::table('exit_nodes', function (Blueprint $table) {
            if (Schema::hasColumn('exit_nodes', 'cost_cents')) {
                $table->dropColumn('cost_cents');
            }
        });
    }
};

