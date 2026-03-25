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
            if (!Schema::hasColumn('orders', 'biz_order_no')) {
                $table->string('biz_order_no', 64)->nullable()->after('id')->comment('业务订单号（可读+可追溯+跨平台一致）');
                $table->unique('biz_order_no', 'orders_biz_order_no_unique');
            }
        });

        DB::table('orders')->orderBy('id')->select('id')->chunk(500, function ($rows) {
            foreach ($rows as $r) {
                DB::table('orders')
                    ->where('id', $r->id)
                    ->whereNull('biz_order_no')
                    ->update(['biz_order_no' => 'AORD-' . (int) $r->id]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'biz_order_no')) {
                $table->dropUnique('orders_biz_order_no_unique');
                $table->dropColumn('biz_order_no');
            }
        });
    }
};

