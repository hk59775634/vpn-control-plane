<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 清空 A 站分销商侧：收入流水、订阅订单、分销商终端 VPN 账号及 WG/RADIUS/IP 等配置。
 * 不影响管理员、服务器、产品、分销商主表等（除非使用 --with-balance-ledger）。
 */
class PurgePurchasesDataCommand extends Command
{
    protected $signature = 'vpn:purge-purchases
                            {--force : 不询问直接执行}
                            {--with-balance-ledger : 同时删除分销商余额流水 reseller_balance_transactions}';

    protected $description = '清空分销商侧已购相关：收入流水、A 站订阅订单、vpn_users(分销商)、WireGuard/RADIUS/IP 绑定等';

    public function handle(): int
    {
        if (!$this->option('force')) {
            if (!$this->confirm('将删除分销商侧全部订阅订单、终端 VPN 账号及 WG/RADIUS 等配置，是否继续？')) {
                $this->info('已取消。');
                return self::SUCCESS;
            }
        }

        $driver = DB::getDriverName();
        $disableFk = $driver === 'mysql' || $driver === 'mariadb';

        DB::transaction(function () use ($disableFk): void {
            if ($disableFk) {
                DB::statement('SET FOREIGN_KEY_CHECKS=0');
            }
            try {
                if (DB::getSchemaBuilder()->hasTable('reseller_income_records')) {
                    DB::table('reseller_income_records')->delete();
                }

                if ($this->option('with-balance-ledger') && DB::getSchemaBuilder()->hasTable('reseller_balance_transactions')) {
                    DB::table('reseller_balance_transactions')->delete();
                }

                $vpnIds = DB::table('vpn_users')
                    ->whereNotNull('reseller_id')
                    ->pluck('id');

                $usernames = DB::table('vpn_users')
                    ->whereNotNull('reseller_id')
                    ->whereNotNull('radius_username')
                    ->pluck('radius_username')
                    ->filter()
                    ->unique()
                    ->values();

                if (DB::getSchemaBuilder()->hasTable('orders')) {
                    DB::table('orders')->whereNotNull('reseller_id')->delete();
                }

                foreach ($usernames as $username) {
                    if (DB::getSchemaBuilder()->hasTable('radcheck')) {
                        DB::table('radcheck')->where('username', $username)->delete();
                    }
                    if (DB::getSchemaBuilder()->hasTable('radreply')) {
                        DB::table('radreply')->where('username', $username)->delete();
                    }
                }

                if (DB::getSchemaBuilder()->hasTable('ip_pool') && $vpnIds->isNotEmpty()) {
                    DB::table('ip_pool')->whereIn('vpn_user_id', $vpnIds)->update([
                        'vpn_user_id' => null,
                        'status' => 'free',
                        'last_unbound_at' => now(),
                    ]);
                }

                if (DB::getSchemaBuilder()->hasTable('wireguard_peers') && $vpnIds->isNotEmpty()) {
                    DB::table('wireguard_peers')->whereIn('vpn_user_id', $vpnIds)->delete();
                }

                if (DB::getSchemaBuilder()->hasTable('vpn_ip_allocations') && $vpnIds->isNotEmpty()) {
                    DB::table('vpn_ip_allocations')->whereIn('vpn_user_id', $vpnIds)->delete();
                }

                if (DB::getSchemaBuilder()->hasTable('vpn_users')) {
                    DB::table('vpn_users')->whereNotNull('reseller_id')->delete();
                }
            } finally {
                if ($disableFk) {
                    DB::statement('SET FOREIGN_KEY_CHECKS=1');
                }
            }
        });

        $this->info('A 站分销商侧已购、订单与 VPN 配置已清空。');
        if (!$this->option('with-balance-ledger')) {
            $this->comment('提示：未删除 reseller_balance_transactions；若需一并清空请加 --with-balance-ledger');
        }

        return self::SUCCESS;
    }
}
