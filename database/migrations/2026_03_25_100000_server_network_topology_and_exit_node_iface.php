<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('servers', 'nat_topology')) {
            Schema::table('servers', function (Blueprint $table) {
                $table->string('nat_topology', 32)->default('combined');
            });
        }
        foreach ([
            'cn_public_iface' => 32,
            'hk_public_iface' => 32,
            'peer_link_iface' => 32,
            'peer_link_local_ip' => 64,
            'peer_link_remote_ip' => 64,
            'link_tunnel_type' => 32,
        ] as $col => $len) {
            if (!Schema::hasColumn('servers', $col)) {
                Schema::table('servers', function (Blueprint $table) use ($col, $len) {
                    $table->string($col, $len)->nullable();
                });
            }
        }
        if (!Schema::hasColumn('servers', 'paired_server_id')) {
            Schema::table('servers', function (Blueprint $table) {
                $table->foreignId('paired_server_id')->nullable()->constrained('servers')->nullOnDelete();
            });
        }

        if (!Schema::hasColumn('exit_nodes', 'public_iface')) {
            Schema::table('exit_nodes', function (Blueprint $table) {
                $table->string('public_iface', 32)->nullable();
            });
        }
        if (!Schema::hasColumn('exit_nodes', 'notes')) {
            Schema::table('exit_nodes', function (Blueprint $table) {
                $table->text('notes')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('exit_nodes', function (Blueprint $table) {
            $table->dropColumn(['public_iface', 'notes']);
        });

        Schema::table('servers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('paired_server_id');
            $table->dropColumn([
                'nat_topology',
                'cn_public_iface',
                'hk_public_iface',
                'peer_link_iface',
                'peer_link_local_ip',
                'peer_link_remote_ip',
                'link_tunnel_type',
            ]);
        });
    }
};
