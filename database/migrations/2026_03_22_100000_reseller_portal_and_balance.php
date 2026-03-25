<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resellers', function (Blueprint $table) {
            $table->string('email')->nullable()->unique()->after('name');
            $table->string('password')->nullable()->after('email');
            $table->rememberToken()->nullable()->after('password');
            $table->unsignedBigInteger('balance_cents')->default(0)->after('remember_token');
            $table->boolean('balance_enforced')->default(false)->after('balance_cents');
            $table->string('status')->default('active')->after('balance_enforced'); // active, suspended
        });
    }

    public function down(): void
    {
        Schema::table('resellers', function (Blueprint $table) {
            $table->dropColumn(['email', 'password', 'remember_token', 'balance_cents', 'balance_enforced', 'status']);
        });
    }
};
