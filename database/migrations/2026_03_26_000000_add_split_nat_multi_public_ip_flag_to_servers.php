<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('servers', 'split_nat_multi_public_ip_enabled')) {
            Schema::table('servers', function (Blueprint $table) {
                $table->boolean('split_nat_multi_public_ip_enabled')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('servers', 'split_nat_multi_public_ip_enabled')) {
            Schema::table('servers', function (Blueprint $table) {
                $table->dropColumn('split_nat_multi_public_ip_enabled');
            });
        }
    }
};

