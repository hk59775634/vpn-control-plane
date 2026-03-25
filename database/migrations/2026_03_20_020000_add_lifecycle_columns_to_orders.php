<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'activated_at')) {
                $table->timestamp('activated_at')->nullable()->after('status')->comment('开通时间');
            }
            if (!Schema::hasColumn('orders', 'last_renewed_at')) {
                $table->timestamp('last_renewed_at')->nullable()->after('activated_at')->comment('最后续费时间');
            }
        });

        // 历史数据回填：active/paid 订单默认以创建时间作为开通时间
        DB::table('orders')
            ->whereIn('status', ['active', 'paid'])
            ->whereNull('activated_at')
            ->update(['activated_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'last_renewed_at')) {
                $table->dropColumn('last_renewed_at');
            }
            if (Schema::hasColumn('orders', 'activated_at')) {
                $table->dropColumn('activated_at');
            }
        });
    }
};

