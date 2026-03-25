<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reseller_payment_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reseller_id')->constrained('resellers')->cascadeOnDelete();
            $table->string('out_trade_no', 64)->unique();
            $table->unsignedInteger('amount_cents');
            $table->string('status', 24)->default('pending'); // pending, paid
            $table->string('trade_no', 128)->nullable();
            $table->string('pay_type', 32)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reseller_payment_orders');
    }
};
