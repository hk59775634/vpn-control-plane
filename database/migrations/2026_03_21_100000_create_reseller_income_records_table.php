<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B 站每笔收入对应一条记录；与 A 站「订阅订单」orders 分离，仅做财务/对账追溯
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reseller_income_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reseller_id')->constrained('resellers')->cascadeOnDelete();
            $table->foreignId('vpn_user_id')->nullable()->constrained('vpn_users')->nullOnDelete();
            $table->unsignedBigInteger('a_order_id')->comment('被开通/续费的 A 站订阅订单 id');
            $table->string('biz_order_no', 64)->unique();
            $table->string('kind', 16)->comment('purchase | renew');
            $table->timestamps();
            $table->index(['reseller_id', 'a_order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reseller_income_records');
    }
};
