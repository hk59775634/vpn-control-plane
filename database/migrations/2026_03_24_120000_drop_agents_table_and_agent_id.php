<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vpn_users') && Schema::hasColumn('vpn_users', 'agent_id')) {
            Schema::table('vpn_users', function (Blueprint $table) {
                $table->dropConstrainedForeignId('agent_id');
            });
        }
        Schema::dropIfExists('agents');
    }

    public function down(): void
    {
        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reseller_id')->constrained('resellers')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });
        if (Schema::hasTable('vpn_users') && !Schema::hasColumn('vpn_users', 'agent_id')) {
            Schema::table('vpn_users', function (Blueprint $table) {
                $table->foreignId('agent_id')->nullable()->after('reseller_id')->constrained('agents')->nullOnDelete();
                $table->index(['agent_id']);
            });
        }
    }
};
