<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('user_public_ip_snat_maps')) {
            Schema::create('user_public_ip_snat_maps', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('vpn_user_id');
                $table->unsignedBigInteger('server_id');
                $table->string('nat_interface', 32);
                $table->string('source_ip', 64);
                $table->string('public_ip', 45);
                $table->string('status', 16)->default('active');
                $table->timestamp('applied_at')->nullable();
                $table->timestamp('released_at')->nullable();
                $table->timestamps();
                $table->index(['vpn_user_id', 'status']);
                $table->index(['server_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_public_ip_snat_maps');
    }
};

