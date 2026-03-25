<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 部分环境 migrations 表已记录 2026_03_24_200000 但 servers 未成功加上 agent_enabled（MySQL DDL 非事务等），
 * 导致创建接入服务器时报 Unknown column 'agent_enabled'。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('servers') && ! Schema::hasColumn('servers', 'agent_enabled')) {
            Schema::table('servers', function (Blueprint $table) {
                $table->boolean('agent_enabled')->default(true);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('servers') && Schema::hasColumn('servers', 'agent_enabled')) {
            Schema::table('servers', function (Blueprint $table) {
                $table->dropColumn('agent_enabled');
            });
        }
    }
};
