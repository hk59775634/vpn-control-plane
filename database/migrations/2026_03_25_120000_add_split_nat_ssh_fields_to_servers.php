<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('servers', 'split_nat_host')) {
            Schema::table('servers', function (Blueprint $table) {
                $table->string('split_nat_host', 255)->nullable();
            });
        }
        if (!Schema::hasColumn('servers', 'split_nat_ssh_port')) {
            Schema::table('servers', function (Blueprint $table) {
                $table->unsignedInteger('split_nat_ssh_port')->nullable();
            });
        }
        if (!Schema::hasColumn('servers', 'split_nat_ssh_user')) {
            Schema::table('servers', function (Blueprint $table) {
                $table->string('split_nat_ssh_user', 64)->nullable();
            });
        }
        if (!Schema::hasColumn('servers', 'split_nat_ssh_password')) {
            Schema::table('servers', function (Blueprint $table) {
                $table->text('split_nat_ssh_password')->nullable();
            });
        }
        if (!Schema::hasColumn('servers', 'split_nat_hk_public_iface')) {
            Schema::table('servers', function (Blueprint $table) {
                $table->string('split_nat_hk_public_iface', 32)->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn([
                'split_nat_host',
                'split_nat_ssh_port',
                'split_nat_ssh_user',
                'split_nat_ssh_password',
                'split_nat_hk_public_iface',
            ]);
        });
    }
};

