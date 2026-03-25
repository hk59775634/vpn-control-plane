<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('products', 'requires_dedicated_public_ip')) {
            Schema::table('products', function (Blueprint $table) {
                $table->boolean('requires_dedicated_public_ip')
                    ->default(false)
                    ->comment('是否需要独立公网IP（从IP池分配）');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('products', 'requires_dedicated_public_ip')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('requires_dedicated_public_ip');
            });
        }
    }
};

