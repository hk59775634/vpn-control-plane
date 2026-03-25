<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('servers', 'wg_private_key_enc')) {
            Schema::table('servers', function (Blueprint $table) {
                $table->text('wg_private_key_enc')->nullable();
            });
        }
        if (!Schema::hasColumn('servers', 'ocserv_radius_host')) {
            Schema::table('servers', function (Blueprint $table) {
                $table->string('ocserv_radius_host', 255)->nullable();
            });
        }
        if (!Schema::hasColumn('servers', 'ocserv_radius_auth_port')) {
            Schema::table('servers', function (Blueprint $table) {
                $table->unsignedInteger('ocserv_radius_auth_port')->nullable();
            });
        }
        if (!Schema::hasColumn('servers', 'ocserv_radius_acct_port')) {
            Schema::table('servers', function (Blueprint $table) {
                $table->unsignedInteger('ocserv_radius_acct_port')->nullable();
            });
        }
        if (!Schema::hasColumn('servers', 'ocserv_radius_secret')) {
            Schema::table('servers', function (Blueprint $table) {
                $table->text('ocserv_radius_secret')->nullable();
            });
        }
        if (!Schema::hasColumn('servers', 'split_nat_server_id')) {
            Schema::table('servers', function (Blueprint $table) {
                $table->unsignedBigInteger('split_nat_server_id')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn([
                'wg_private_key_enc',
                'ocserv_radius_host',
                'ocserv_radius_auth_port',
                'ocserv_radius_acct_port',
                'ocserv_radius_secret',
                'split_nat_server_id',
            ]);
        });
    }
};

