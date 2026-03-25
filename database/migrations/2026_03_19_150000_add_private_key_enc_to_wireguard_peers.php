<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wireguard_peers', function (Blueprint $table) {
            if (!Schema::hasColumn('wireguard_peers', 'private_key_enc')) {
                $table->text('private_key_enc')->nullable()->after('public_key')->comment('客户端私钥（加密存储，可选）');
            }
        });
    }

    public function down(): void
    {
        Schema::table('wireguard_peers', function (Blueprint $table) {
            if (Schema::hasColumn('wireguard_peers', 'private_key_enc')) {
                $table->dropColumn('private_key_enc');
            }
        });
    }
};

