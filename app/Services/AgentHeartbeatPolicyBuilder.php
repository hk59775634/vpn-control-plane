<?php

namespace App\Services;

use App\Models\Server;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 心跳下发策略：一体拓扑 NAT、WireGuard 每用户 tc、超流量剔除 peer。
 */
final class AgentHeartbeatPolicyBuilder
{
    public function build(Server $server): array
    {
        $topo = (string) ($server->nat_topology ?: 'combined');
        $wan = (string) ($server->hk_public_iface ?: 'eth0');
        $cidrs = $this->parseVpnIpCidrs((string) ($server->vpn_ip_cidrs ?? ''));

        $policy = [
            'combined_nat' => null,
            'wg_shaping' => null,
            'wg_remove_peer_public_keys' => [],
        ];

        if ($topo === 'combined' && $cidrs !== []) {
            $policy['combined_nat'] = [
                'enabled' => true,
                'wan_interface' => $wan,
                'source_cidrs' => $cidrs,
                'persist_sysctl' => true,
                'persist_iptables' => true,
                'wg_interface' => 'wg0',
            ];
        }

        $proto = strtolower(trim((string) ($server->protocol ?? '')));
        if ($proto !== 'wireguard' && $proto !== '') {
            return $policy;
        }

        $wgIface = 'wg0';
        if ($policy['combined_nat'] !== null && isset($policy['combined_nat']['wg_interface'])) {
            $wgIface = (string) $policy['combined_nat']['wg_interface'];
        }

        $rows = $this->loadWireguardPeerRows((int) $server->id);
        $shapingPeers = [];
        $revokeKeys = [];

        foreach ($rows as $row) {
            $pk = (string) ($row->public_key ?? '');
            if ($pk === '') {
                continue;
            }
            $clientIp = $this->firstIpv4FromAllowedIps((string) ($row->allowed_ips ?? ''));
            $bw = isset($row->bandwidth_limit_kbps) && $row->bandwidth_limit_kbps !== null
                ? (int) $row->bandwidth_limit_kbps
                : 0;
            if ($clientIp !== null && $bw > 0) {
                $shapingPeers[] = [
                    'client_ip' => $clientIp,
                    'rate_kbps' => $bw,
                ];
            }

            $quota = isset($row->traffic_quota_bytes) && $row->traffic_quota_bytes !== null
                ? (int) $row->traffic_quota_bytes
                : 0;
            if ($quota > 0) {
                $since = $this->orderPeriodStart($row);
                $used = $this->sumTrafficSince((int) $row->vpn_user_id, (int) $server->id, $since);
                if ($used >= $quota) {
                    $revokeKeys[] = $pk;
                }
            }
        }

        if ($shapingPeers !== []) {
            $policy['wg_shaping'] = [
                'interface' => $wgIface,
                'peers' => $shapingPeers,
            ];
        }
        $policy['wg_remove_peer_public_keys'] = array_values(array_unique($revokeKeys));

        return $policy;
    }

    /**
     * @return list<string>
     */
    private function parseVpnIpCidrs(string $raw): array
    {
        $raw = str_replace(["\r\n", "\r"], "\n", trim($raw));
        $parts = preg_split('/[\s,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
        if (! is_array($parts)) {
            return [];
        }
        $out = [];
        foreach ($parts as $p) {
            $p = trim((string) $p);
            if ($p !== '' && str_contains($p, '/')) {
                $out[] = $p;
            }
        }

        return array_values(array_unique($out));
    }

    private function firstIpv4FromAllowedIps(string $allowed): ?string
    {
        $chunks = preg_split('/\s*,\s*/', trim($allowed)) ?: [];
        foreach ($chunks as $part) {
            $part = trim((string) $part);
            if ($part === '') {
                continue;
            }
            if (preg_match('/^(\d{1,3}(?:\.\d{1,3}){3})(?:\/\d+)?$/', $part, $m)) {
                return $m[1];
            }
        }

        return null;
    }

    /**
     * @return list<object>
     */
    private function loadWireguardPeerRows(int $serverId): array
    {
        if (! Schema::hasTable('wireguard_peers') || ! Schema::hasTable('orders') || ! Schema::hasTable('products')) {
            return [];
        }

        $q = DB::table('wireguard_peers as wp')
            ->joinSub(
                DB::table('orders')
                    ->select('vpn_user_id', DB::raw('MAX(id) as last_order_id'))
                    ->where('status', 'active')
                    ->where('expires_at', '>', now())
                    ->whereNotNull('product_id')
                    ->groupBy('vpn_user_id'),
                'lo',
                'lo.vpn_user_id',
                '=',
                'wp.vpn_user_id'
            )
            ->join('orders as o', 'o.id', '=', 'lo.last_order_id')
            ->join('products as p', 'p.id', '=', 'o.product_id')
            ->where('wp.server_id', $serverId)
            ->orderBy('wp.id');

        $cols = ['wp.public_key', 'wp.allowed_ips', 'wp.vpn_user_id', 'o.activated_at', 'o.created_at'];
        if (Schema::hasColumn('products', 'bandwidth_limit_kbps')) {
            $cols[] = 'p.bandwidth_limit_kbps';
        }
        if (Schema::hasColumn('products', 'traffic_quota_bytes')) {
            $cols[] = 'p.traffic_quota_bytes';
        }

        return $q->get($cols)->all();
    }

    private function orderPeriodStart(object $row): Carbon
    {
        $a = $row->activated_at ?? null;
        if ($a) {
            return Carbon::parse((string) $a);
        }
        $c = $row->created_at ?? null;
        if ($c) {
            return Carbon::parse((string) $c);
        }

        return Carbon::createFromTimestamp(0);
    }

    private function sumTrafficSince(int $vpnUserId, int $serverId, Carbon $since): int
    {
        if (! Schema::hasTable('traffic_logs')) {
            return 0;
        }

        $v = DB::table('traffic_logs')
            ->where('vpn_user_id', $vpnUserId)
            ->where('server_id', $serverId)
            ->where('recorded_at', '>=', $since)
            ->selectRaw('COALESCE(SUM(bytes_up + bytes_down), 0) as t')
            ->value('t');

        return (int) $v;
    }
}
