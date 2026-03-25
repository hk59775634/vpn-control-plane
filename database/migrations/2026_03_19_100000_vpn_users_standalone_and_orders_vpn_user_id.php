<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE vpn_users MODIFY user_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE orders MODIFY user_id BIGINT UNSIGNED NULL');

        Schema::table('vpn_users', function (Blueprint $table) {
            if (!Schema::hasColumn('vpn_users', 'email')) {
                $table->string('email', 255)->nullable()->after('user_id');
            }
            if (!Schema::hasColumn('vpn_users', 'reseller_id')) {
                $table->foreignId('reseller_id')->nullable()->after('email')->constrained('resellers')->nullOnDelete();
            }
        });

        Schema::table('vpn_users', function (Blueprint $table) {
            $table->unique(['email', 'reseller_id'], 'vpn_users_email_reseller_id_unique');
        });

        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'vpn_user_id')) {
                $table->foreignId('vpn_user_id')->nullable()->after('user_id')->constrained('vpn_users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'vpn_user_id')) {
                $table->dropConstrainedForeignId('vpn_user_id');
            }
        });

        Schema::table('vpn_users', function (Blueprint $table) {
            $table->dropUnique('vpn_users_email_reseller_id_unique');
            if (Schema::hasColumn('vpn_users', 'reseller_id')) {
                $table->dropConstrainedForeignId('reseller_id');
            }
            if (Schema::hasColumn('vpn_users', 'email')) {
                $table->dropColumn('email');
            }
        });

        DB::statement('ALTER TABLE orders MODIFY user_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE vpn_users MODIFY user_id BIGINT UNSIGNED NOT NULL');
    }
};
