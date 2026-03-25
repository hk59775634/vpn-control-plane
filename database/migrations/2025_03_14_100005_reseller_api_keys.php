<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 对应 1.0 0007_reseller_api_keys.sql
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reseller_api_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reseller_id')->constrained()->cascadeOnDelete();
            $table->string('api_key')->unique();
            $table->string('name')->default('');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reseller_api_keys');
    }
};
