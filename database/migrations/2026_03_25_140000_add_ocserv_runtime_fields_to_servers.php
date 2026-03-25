<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('servers', 'ocserv_port')) {
            Schema::table('servers', function (Blueprint $table) {
                $table->unsignedInteger('ocserv_port')->nullable();
            });
        }
        if (!Schema::hasColumn('servers', 'ocserv_domain')) {
            Schema::table('servers', function (Blueprint $table) {
                $table->string('ocserv_domain', 255)->nullable();
            });
        }
        if (!Schema::hasColumn('servers', 'ocserv_tls_cert_pem')) {
            Schema::table('servers', function (Blueprint $table) {
                $table->text('ocserv_tls_cert_pem')->nullable();
            });
        }
        if (!Schema::hasColumn('servers', 'ocserv_tls_key_pem')) {
            Schema::table('servers', function (Blueprint $table) {
                $table->text('ocserv_tls_key_pem')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn([
                'ocserv_port',
                'ocserv_domain',
                'ocserv_tls_cert_pem',
                'ocserv_tls_key_pem',
            ]);
        });
    }
};

