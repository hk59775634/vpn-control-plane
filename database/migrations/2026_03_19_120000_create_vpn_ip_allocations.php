<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vpn_ip_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained('servers')->cascadeOnDelete();
            $table->foreignId('vpn_user_id')->unique()->constrained('vpn_users')->cascadeOnDelete();
            $table->string('ip_address', 45);
            $table->string('region', 64)->nullable();
            $table->timestamps();

            $table->unique(['server_id', 'ip_address'], 'vpn_ip_alloc_server_ip_unique');
            $table->index(['server_id', 'region']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vpn_ip_allocations');
    }
};

