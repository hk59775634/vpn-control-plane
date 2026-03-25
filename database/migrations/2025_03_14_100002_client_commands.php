<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 对应 1.0 0004_client_commands.sql
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_commands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vpn_user_id')->constrained()->cascadeOnDelete();
            $table->string('command_type');
            // Keep MySQL-compatible: use NULL as default and handle '{}' in app layer.
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('consumed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_commands');
    }
};
