<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 对应 1.0 0005_access_exit_routes.sql
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_exit_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('access_server_id')->unique()->constrained('servers')->cascadeOnDelete();
            $table->foreignId('exit_node_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_exit_routes');
    }
};
