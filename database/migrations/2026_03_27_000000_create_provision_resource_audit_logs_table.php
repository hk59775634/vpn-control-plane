<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provision_resource_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vpn_user_id')->nullable()->index();
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->unsignedBigInteger('product_id')->nullable()->index();
            $table->unsignedBigInteger('reseller_id')->nullable()->index();
            $table->string('event', 64)->index();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['created_at', 'event']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provision_resource_audit_logs');
    }
};
