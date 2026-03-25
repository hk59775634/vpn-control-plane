<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vpn_users', function (Blueprint $table) {
            if (!Schema::hasColumn('vpn_users', 'agent_id')) {
                $table->foreignId('agent_id')->nullable()->after('reseller_id')->constrained('agents')->nullOnDelete();
                $table->index(['agent_id']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('vpn_users', function (Blueprint $table) {
            if (Schema::hasColumn('vpn_users', 'agent_id')) {
                $table->dropConstrainedForeignId('agent_id');
            }
        });
    }
};

