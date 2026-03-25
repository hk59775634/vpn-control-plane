<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('servers', 'peer_link_wg_private_key')) {
            Schema::table('servers', function (Blueprint $table) {
                $table->text('peer_link_wg_private_key')->nullable();
            });
        }
        if (!Schema::hasColumn('servers', 'peer_link_wg_peer_public_key')) {
            Schema::table('servers', function (Blueprint $table) {
                $table->string('peer_link_wg_peer_public_key', 191)->nullable();
            });
        }
        if (!Schema::hasColumn('servers', 'peer_link_wg_endpoint')) {
            Schema::table('servers', function (Blueprint $table) {
                $table->string('peer_link_wg_endpoint', 255)->nullable();
            });
        }
        if (!Schema::hasColumn('servers', 'peer_link_wg_allowed_ips')) {
            Schema::table('servers', function (Blueprint $table) {
                $table->string('peer_link_wg_allowed_ips', 255)->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $drop = [];
            foreach ([
                'peer_link_wg_private_key',
                'peer_link_wg_peer_public_key',
                'peer_link_wg_endpoint',
                'peer_link_wg_allowed_ips',
            ] as $c) {
                if (Schema::hasColumn('servers', $c)) {
                    $drop[] = $c;
                }
            }
            if ($drop) {
                $table->dropColumn($drop);
            }
        });
    }
};

