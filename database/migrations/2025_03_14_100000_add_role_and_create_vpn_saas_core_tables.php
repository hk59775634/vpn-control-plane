<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * VPN SaaS 核心表（对应 1.0 backend/migrations/0001_init.sql）
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'role')) {
                $table->string('role')->default('user')->after('password');
            }
        });

        Schema::create('vpn_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('servers', function (Blueprint $table) {
            $table->id();
            $table->string('hostname')->unique();
            $table->string('region');
            $table->string('role');
            $table->timestamps();
        });

        Schema::create('exit_nodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->string('ip_address', 45);
            $table->string('region');
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('price_cents');
            $table->string('currency')->default('USD');
            $table->unsignedInteger('duration_days')->default(30);
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('status');
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        Schema::create('traffic_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vpn_user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('bytes_up')->default(0);
            $table->unsignedBigInteger('bytes_down')->default(0);
            $table->timestamp('recorded_at')->useCurrent();
        });

        Schema::create('ip_pool', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address', 45)->unique();
            $table->string('region');
            $table->string('status')->default('free');
            $table->timestamps();
        });

        Schema::create('resellers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reseller_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('anti_block_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('wireguard_obfs')->default(false);
            $table->boolean('tls_camouflage')->default(false);
            $table->boolean('domain_fronting')->default(false);
            $table->boolean('port_rotation')->default(false);
            // MySQL does not allow default values on TEXT; treat NULL as empty string in app layer.
            $table->text('custom_profile')->nullable();
        });

        Schema::create('wireguard_peers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vpn_user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->string('public_key');
            $table->string('allowed_ips');
            $table->string('endpoint');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wireguard_peers');
        Schema::dropIfExists('anti_block_policies');
        Schema::dropIfExists('agents');
        Schema::dropIfExists('resellers');
        Schema::dropIfExists('ip_pool');
        Schema::dropIfExists('traffic_logs');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('products');
        Schema::dropIfExists('exit_nodes');
        Schema::dropIfExists('servers');
        Schema::dropIfExists('vpn_users');
        if (Schema::hasColumn('users', 'role')) {
            Schema::table('users', fn (Blueprint $t) => $t->dropColumn('role'));
        }
    }
};
