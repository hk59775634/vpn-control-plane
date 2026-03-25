<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 对应 1.0 0006_server_bandwidth.sql
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('server_bandwidth', function (Blueprint $table) {
            $table->foreignId('server_id')->primary()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('rate_kbps')->default(0);
            $table->timestamp('updated_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_bandwidth');
    }
};
