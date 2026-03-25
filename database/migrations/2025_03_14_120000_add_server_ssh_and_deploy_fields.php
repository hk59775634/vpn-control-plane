<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->string('host', 255)->nullable()->after('role')->comment('域名或IP');
            $table->unsignedSmallInteger('ssh_port')->default(22)->after('host');
            $table->string('ssh_user', 64)->default('root')->after('ssh_port')->comment('SSH 登录账号');
            $table->text('ssh_password')->nullable()->after('ssh_user')->comment('SSH 密码，加密存储');
            $table->text('notes')->nullable()->after('ssh_password');
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn(['host', 'ssh_port', 'ssh_user', 'ssh_password', 'notes']);
        });
    }
};
