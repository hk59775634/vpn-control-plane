<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('servers', 'agent_token_hash')) {
            Schema::table('servers', function (Blueprint $table) {
                $table->string('agent_token_hash', 191)->nullable();
            });
        }
        if (!Schema::hasColumn('servers', 'agent_version')) {
            Schema::table('servers', function (Blueprint $table) {
                $table->string('agent_version', 64)->nullable();
            });
        }
        if (!Schema::hasColumn('servers', 'agent_status')) {
            Schema::table('servers', function (Blueprint $table) {
                $table->string('agent_status', 32)->default('unknown');
            });
        }
        if (!Schema::hasColumn('servers', 'last_seen_at')) {
            Schema::table('servers', function (Blueprint $table) {
                $table->timestamp('last_seen_at')->nullable();
            });
        }
        if (!Schema::hasColumn('servers', 'agent_enabled')) {
            Schema::table('servers', function (Blueprint $table) {
                $table->boolean('agent_enabled')->default(true);
            });
        }
        if (!Schema::hasColumn('servers', 'node_nat_interface')) {
            Schema::table('servers', function (Blueprint $table) {
                $table->string('node_nat_interface', 32)->nullable();
            });
        }
        if (!Schema::hasColumn('servers', 'node_bandwidth_interface')) {
            Schema::table('servers', function (Blueprint $table) {
                $table->string('node_bandwidth_interface', 32)->nullable();
            });
        }

        Schema::create('agent_heartbeats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained('servers')->cascadeOnDelete();
            $table->string('agent_version', 64)->nullable();
            $table->double('cpu_percent')->nullable();
            $table->double('mem_percent')->nullable();
            $table->double('load_1')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['server_id', 'created_at']);
        });

        Schema::create('agent_commands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained('servers')->cascadeOnDelete();
            $table->string('type', 64);
            $table->json('payload')->nullable();
            $table->string('status', 24)->default('pending'); // pending/dispatched/success/failed
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('acked_at')->nullable();
            $table->text('result_message')->nullable();
            $table->json('result_meta')->nullable();
            $table->timestamps();
            $table->index(['server_id', 'status', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_commands');
        Schema::dropIfExists('agent_heartbeats');

        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn([
                'agent_token_hash',
                'agent_version',
                'agent_status',
                'last_seen_at',
                'agent_enabled',
                'node_nat_interface',
                'node_bandwidth_interface',
            ]);
        });
    }
};

