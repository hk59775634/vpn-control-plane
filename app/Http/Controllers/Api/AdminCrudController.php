<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExitNode;
use App\Models\IpPool;
use App\Models\Order;
use App\Models\Product;
use App\Models\Reseller;
use App\Models\ResellerIncomeRecord;
use App\Models\ResellerApiKey;
use App\Models\ResellerBalanceTransaction;
use App\Models\Server;
use App\Models\User;
use App\Models\ProvisionResourceAuditLog;
use App\Models\VpnUser;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Crypt;

class AdminCrudController extends Controller
{
    /** @var array<string, true>|null */
    private static ?array $serversTableColumnSet = null;

    /**
     * 当 migrations 记录与真实库结构不一致时，避免 INSERT/UPDATE 引用不存在的列（如 agent_enabled）。
     */
    private function filterToServersTableColumns(array $attributes): array
    {
        if (self::$serversTableColumnSet === null) {
            self::$serversTableColumnSet = array_fill_keys(Schema::getColumnListing('servers'), true);
        }

        return array_intersect_key($attributes, self::$serversTableColumnSet);
    }

    public function summary(): JsonResponse
    {
        return response()->json([
            'server_count' => Server::count(),
            'user_count' => User::count(),
            'income_records_count' => ResellerIncomeRecord::count(),
            'income_purchase_count' => ResellerIncomeRecord::where('kind', 'purchase')->count(),
            'income_renew_count' => ResellerIncomeRecord::where('kind', 'renew')->count(),
        ]);
    }

    /**
     * 数据分析总览（供 admin dashboard 页面展示）
     * GET /api/v1/admin/analytics
     */
    public function analytics(): JsonResponse
    {
        $now = now();
        $monthStart = $now->copy()->startOfMonth();
        $daysInMonth = max(1, (int) $monthStart->daysInMonth);
        // 含今天：1号~今天共 daysElapsed 天
        $daysElapsed = max(1, (int) $monthStart->diffInDays($now) + 1);
        // NOTE: 后续“更精确版本”会改成按每台服务器/出口节点的 created_at 分段摊销
        // 这里保留 daysElapsed 只是为了兜底/兼容思路。
        $prorationFactor = $daysInMonth > 0 ? ($daysElapsed / $daysInMonth) : 1.0;

        $serverCount = Server::count();
        $platformUserCount = User::count();

        // 仅统计分销商（B）终端用户；同一邮箱+分销商多订阅多行 vpn_users 只计 1
        $vpnUserCount = $this->countDistinctBResellerVpnUsers();
        $activeVpnUserCount = $this->countDistinctBResellerVpnUsersActive();

        $ordersTotal = Order::count();
        $activeOrders = Order::where('status', 'active')->count();

        $incomeRecordsCount = ResellerIncomeRecord::count();
        $incomePurchaseCount = ResellerIncomeRecord::where('kind', 'purchase')->count();
        $incomeRenewCount = ResellerIncomeRecord::where('kind', 'renew')->count();

        // 现金流/销售口径都按“本月累计（MTD）”
        $rechargeTotalCents = (int) ResellerBalanceTransaction::query()
            ->where('type', 'recharge')
            ->where('created_at', '>=', $monthStart)
            ->where('created_at', '<=', $now)
            ->sum('amount_cents');

        // provision_* 记录时 amount_cents 为负数；取反得到正的“销售金额（扣款口径）”
        $salesSum = (int) ResellerBalanceTransaction::query()
            ->whereIn('type', ['provision_purchase', 'provision_renew'])
            ->where('created_at', '>=', $monthStart)
            ->where('created_at', '<=', $now)
            ->sum('amount_cents');
        $salesTotalCents = -$salesSum;

        // 平台成本：接入服务器 + NAT 服务器
        // 假设 cost_cents 表示“整月成本”（月度成本）。
        // 更精确版本：按每条记录的 created_at 从“当月起算日”开始摊销；
        // - created_at <= 月初：从月初计到今天
        // - 月初 < created_at <= 今天：从 created_at 计到今天
        // - created_at > 今天：本月为 0
        // 兼容：若数据库尚未跑完迁移（cost_cents 列不存在），则按 0 计算，避免页面打开失败。
        $platformCostCentsMtdFloat = 0.0;
        if (Schema::hasColumn('servers', 'cost_cents')) {
            $servers = Server::query()
                ->select(['cost_cents', 'created_at'])
                ->where('cost_cents', '>', 0)
                ->where('created_at', '<=', $now)
                ->get();

            foreach ($servers as $s) {
                $start = $s->created_at instanceof Carbon ? $s->created_at : Carbon::parse((string) $s->created_at);
                $effStart = $start->greaterThan($monthStart) ? $start : $monthStart;
                if ($effStart->greaterThan($now)) {
                    continue;
                }
                $daysUsed = $effStart->diffInDays($now) + 1; // inclusive
                $fraction = $daysInMonth > 0 ? ($daysUsed / $daysInMonth) : 0.0;
                $platformCostCentsMtdFloat += ((int) $s->cost_cents) * $fraction;
            }
        }
        if (Schema::hasColumn('exit_nodes', 'cost_cents')) {
            $exitNodes = ExitNode::query()
                ->select(['cost_cents', 'created_at'])
                ->where('cost_cents', '>', 0)
                ->where('created_at', '<=', $now)
                ->get();

            foreach ($exitNodes as $e) {
                $start = $e->created_at instanceof Carbon ? $e->created_at : Carbon::parse((string) $e->created_at);
                $effStart = $start->greaterThan($monthStart) ? $start : $monthStart;
                if ($effStart->greaterThan($now)) {
                    continue;
                }
                $daysUsed = $effStart->diffInDays($now) + 1; // inclusive
                $fraction = $daysInMonth > 0 ? ($daysUsed / $daysInMonth) : 0.0;
                $platformCostCentsMtdFloat += ((int) $e->cost_cents) * $fraction;
            }
        }

        $platformCostCentsMtd = (int) round($platformCostCentsMtdFloat);

        $profitTotalCents = $salesTotalCents - $platformCostCentsMtd;
        $profitMarginRate = $salesTotalCents > 0 ? ($profitTotalCents / $salesTotalCents) : 0.0;

        // 现金覆盖：充值入账（recharge）- 平台成本（本月累计，按 created_at 摊销）
        $cashCoverageTotalCents = $rechargeTotalCents - $platformCostCentsMtd;

        $topResellers = DB::table('resellers as r')
            ->leftJoin('reseller_balance_transactions as t', 't.reseller_id', '=', 'r.id')
            ->selectRaw(
                'r.id as reseller_id, r.name as reseller_name,' .
                ' COALESCE(SUM(CASE WHEN t.type = "recharge" THEN t.amount_cents ELSE 0 END), 0) as recharge_cents,' .
                ' COALESCE(-SUM(CASE WHEN t.type IN ("provision_purchase","provision_renew") THEN t.amount_cents ELSE 0 END), 0) as sales_cents'
            )
            ->groupBy('r.id', 'r.name')
            ->orderByDesc('sales_cents')
            ->limit(5)
            ->get()
            ->map(function ($row) {
                return [
                    'reseller_id' => (int) $row->reseller_id,
                    'name' => (string) $row->reseller_name,
                    'sales_cents' => (int) $row->sales_cents,
                    'recharge_cents' => (int) $row->recharge_cents,
                ];
            })
            ->values()
            ->all();

        $topProducts = DB::table('orders as o')
            ->join('products as p', 'p.id', '=', 'o.product_id')
            ->selectRaw(
                'p.id as product_id, p.name as product_name,' .
                ' COUNT(*) as open_count,' .
                ' SUM(p.price_cents) as sales_cents'
            )
            ->where('o.status', 'active')
            ->whereNotNull('o.reseller_id')
            ->groupBy('p.id', 'p.name')
            ->orderByDesc('open_count')
            ->limit(5)
            ->get()
            ->map(function ($row) {
                return [
                    'product_id' => (int) $row->product_id,
                    'name' => (string) $row->product_name,
                    'open_count' => (int) $row->open_count,
                    'sales_cents' => (int) $row->sales_cents,
                ];
            })
            ->values()
            ->all();

        return response()->json([
            'stats' => [
                'server_count' => (int) $serverCount,
                'platform_user_count' => (int) $platformUserCount,
                'vpn_user_count' => (int) $vpnUserCount,
                'active_vpn_user_count' => (int) $activeVpnUserCount,
                'orders_total_count' => (int) $ordersTotal,
                'active_orders_count' => (int) $activeOrders,
                'income_records_count' => (int) $incomeRecordsCount,
                'income_purchase_count' => (int) $incomePurchaseCount,
                'income_renew_count' => (int) $incomeRenewCount,
                'platform_cost_cents' => (int) $platformCostCentsMtd,
                'sales_total_cents' => (int) $salesTotalCents,
                'profit_total_cents' => (int) $profitTotalCents,
                'profit_margin_rate' => (float) $profitMarginRate,
                // 现金覆盖同样按本月累计（MTD）口径
                'cash_coverage_total_cents' => (int) $cashCoverageTotalCents,
                'recharge_total_cents' => (int) $rechargeTotalCents,
            ],
            'top_resellers' => $topResellers,
            'top_products' => $topProducts,
        ]);
    }

    /**
     * GET /api/v1/admin/income_records
     * 按 B 站完整业务单号（biz_order_no）同步的收入流水，用于对账与统计
     */
    public function listIncomeRecords(Request $request): JsonResponse
    {
        $stats = [
            'total' => (int) ResellerIncomeRecord::count(),
            'purchase' => (int) ResellerIncomeRecord::where('kind', 'purchase')->count(),
            'renew' => (int) ResellerIncomeRecord::where('kind', 'renew')->count(),
        ];

        $query = ResellerIncomeRecord::query()
            ->with(['reseller:id,name', 'vpnUser:id,email,name'])
            ->orderByDesc('id');

        if ($request->filled('reseller_id')) {
            $query->where('reseller_id', (int) $request->input('reseller_id'));
        }
        if ($request->filled('q')) {
            $term = trim((string) $request->input('q'));
            if ($term !== '') {
                $like = '%'.$term.'%';
                $query->where(function ($q) use ($like) {
                    $q->where('biz_order_no', 'like', $like)
                        ->orWhereHas('vpnUser', fn ($q2) => $q2->where('email', 'like', $like));
                });
            }
        }

        $limit = min(max((int) $request->input('limit', 500), 1), 2000);
        $records = $query->limit($limit)->get();

        return response()->json([
            'stats' => $stats,
            'records' => $records,
        ]);
    }

    public function listServers(): JsonResponse
    {
        return response()->json(Server::orderBy('id')->get());
    }

    /**
     * 聚合各接入服务器最近一次心跳上报的在线会话（扁平列表，供管理端「在线用户」页）。
     */
    public function listOnlineSessions(Request $request): JsonResponse
    {
        if (! Schema::hasTable('servers') || ! Schema::hasColumn('servers', 'online_sessions')) {
            return response()->json([
                'sessions' => [],
                'total' => 0,
                'generated_at' => now()->toIso8601String(),
            ]);
        }

        $serverId = $request->filled('server_id') ? (int) $request->input('server_id') : null;
        $query = Server::query()->orderBy('id');
        if ($serverId !== null && $serverId > 0) {
            $query->where('id', $serverId);
        }

        $servers = $query->get(['id', 'hostname', 'region', 'host', 'protocol', 'online_sessions']);

        $rows = [];
        foreach ($servers as $s) {
            $sessions = $s->online_sessions;
            if (! is_array($sessions)) {
                continue;
            }
            foreach ($sessions as $sess) {
                if (! is_array($sess)) {
                    continue;
                }
                $rows[] = [
                    'server_id' => $s->id,
                    'server_hostname' => $s->hostname,
                    'server_region' => $s->region,
                    'server_host' => $s->host,
                    'server_protocol' => $s->protocol,
                    'username' => $sess['username'] ?? null,
                    'source_ip' => $sess['source_ip'] ?? null,
                    'connected_seconds' => isset($sess['connected_seconds']) ? (int) $sess['connected_seconds'] : null,
                    'rx_bytes' => isset($sess['rx_bytes']) ? (int) $sess['rx_bytes'] : null,
                    'tx_bytes' => isset($sess['tx_bytes']) ? (int) $sess['tx_bytes'] : null,
                    'protocol' => $sess['protocol'] ?? null,
                ];
            }
        }

        return response()->json([
            'sessions' => $rows,
            'total' => count($rows),
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    public function createServer(Request $request): JsonResponse
    {
        $v = $request->validate([
            'hostname' => 'required|string|max:255',
            'region' => 'required|string|max:64',
            'role' => 'nullable|string|max:64',
            'cost_cents' => 'nullable|integer|min:0',
            'protocol' => 'nullable|string|in:wireguard,ocserv',
            'vpn_ip_cidrs' => 'nullable|string|max:4096',
            'wg_public_key' => 'nullable|string|max:191',
            'wg_private_key' => 'nullable|string|max:191',
            'wg_port' => 'nullable|integer|min:1|max:65535',
            'wg_dns' => 'nullable|string|max:191',
            'ocserv_radius_host' => 'nullable|string|max:255',
            'ocserv_radius_auth_port' => 'nullable|integer|min:1|max:65535',
            'ocserv_radius_acct_port' => 'nullable|integer|min:1|max:65535',
            'ocserv_radius_secret' => 'nullable|string|max:2048',
            'ocserv_port' => 'nullable|integer|min:1|max:65535',
            'ocserv_domain' => 'nullable|string|max:255',
            'ocserv_tls_cert_pem' => 'nullable|string|max:65535',
            'ocserv_tls_key_pem' => 'nullable|string|max:65535',
            'host' => 'nullable|string|max:255',
            'ssh_port' => 'nullable|integer|min:1|max:65535',
            'ssh_user' => 'nullable|string|max:64',
            'ssh_password' => 'nullable|string|max:2048',
            'notes' => 'nullable|string|max:2048',
            'agent_enabled' => 'nullable|boolean',
            'node_nat_interface' => 'nullable|string|max:32',
            'node_bandwidth_interface' => 'nullable|string|max:32',
            'nat_topology' => 'nullable|string|in:combined,split_access,split_nat',
            'cn_public_iface' => 'nullable|string|max:32',
            'hk_public_iface' => 'nullable|string|max:32',
            'peer_link_iface' => 'nullable|string|max:32',
            'peer_link_local_ip' => 'nullable|string|max:64',
            'peer_link_remote_ip' => 'nullable|string|max:64',
            'link_tunnel_type' => 'nullable|string|max:32',
            'peer_link_wg_private_key' => 'nullable|string|max:2048',
            'peer_link_wg_peer_public_key' => 'nullable|string|max:191',
            'peer_link_wg_endpoint' => 'nullable|string|max:255',
            'peer_link_wg_allowed_ips' => 'nullable|string|max:255',
            'split_nat_server_id' => 'nullable|integer|exists:servers,id',
            'split_nat_host' => 'nullable|string|max:255',
            'split_nat_ssh_port' => 'nullable|integer|min:1|max:65535',
            'split_nat_ssh_user' => 'nullable|string|max:64',
            'split_nat_ssh_password' => 'nullable|string|max:2048',
            'split_nat_hk_public_iface' => 'nullable|string|max:32',
            'split_nat_multi_public_ip_enabled' => 'nullable|boolean',
        ]);
        $v['ssh_port'] = $v['ssh_port'] ?? 22;
        $v['ssh_user'] = $v['ssh_user'] ?? 'root';
        $v['role'] = $v['role'] ?? 'access';
        $v['agent_enabled'] = array_key_exists('agent_enabled', $v) ? (bool) $v['agent_enabled'] : true;
        $v['node_nat_interface'] = $v['node_nat_interface'] ?? 'eth0';
        $v['node_bandwidth_interface'] = $v['node_bandwidth_interface'] ?? 'eth0';
        $v['nat_topology'] = $v['nat_topology'] ?? 'combined';
        $this->fillProtocolDetails($v, null);
        if ($v['nat_topology'] === 'combined') {
            if (empty($v['cn_public_iface']) || empty($v['hk_public_iface'])) {
                abort(422, '一体模式下必须填写 cn_public_iface 与 hk_public_iface');
            }
            // 一体模式固定走 HK 网卡做 NAT 与限速，不使用手填网卡
            $v['node_nat_interface'] = (string) $v['hk_public_iface'];
            $v['node_bandwidth_interface'] = (string) $v['hk_public_iface'];
        } else {
            if (empty($v['cn_public_iface']) || empty($v['peer_link_iface']) || empty($v['split_nat_host']) || empty($v['split_nat_ssh_user']) || empty($v['split_nat_ssh_password'])) {
                abort(422, '分体模式下必须填写 cn_public_iface、peer_link_iface、NAT服务器 host/ssh_user/ssh_password');
            }
            if (($v['link_tunnel_type'] ?? '') === 'wireguard') {
                if (empty($v['peer_link_wg_private_key']) || empty($v['peer_link_wg_peer_public_key']) || empty($v['peer_link_wg_endpoint']) || empty($v['peer_link_local_ip'])) {
                    abort(422, '分体+WireGuard 互联需填写互联网卡WG私钥、对端公钥、对端地址与本机互联IP');
                }
            }
            $v['split_nat_ssh_port'] = $v['split_nat_ssh_port'] ?? 22;
            $v['split_nat_server_id'] = $this->resolveSplitNatServerId($v, null);
            // 分体接入侧：不执行 NAT，限速落在互联口
            $v['node_nat_interface'] = (string) $v['peer_link_iface'];
            $v['node_bandwidth_interface'] = (string) $v['peer_link_iface'];
        }
        $v['config_revision_ts'] = time();
        $s = Server::create($this->filterToServersTableColumns($v));
        return response()->json($s, 201);
    }

    public function updateServer(Request $request, int $id): JsonResponse
    {
        $s = Server::findOrFail($id);
        $v = $request->validate([
            'hostname' => 'sometimes|string|max:255',
            'region' => 'sometimes|string|max:64',
            'role' => 'sometimes|string|max:64',
            'cost_cents' => 'sometimes|nullable|integer|min:0',
            'protocol' => 'sometimes|nullable|string|in:wireguard,ocserv',
            'vpn_ip_cidrs' => 'sometimes|nullable|string|max:4096',
            'wg_public_key' => 'sometimes|nullable|string|max:191',
            'wg_private_key' => 'sometimes|nullable|string|max:191',
            'wg_port' => 'sometimes|nullable|integer|min:1|max:65535',
            'wg_dns' => 'sometimes|nullable|string|max:191',
            'ocserv_radius_host' => 'sometimes|nullable|string|max:255',
            'ocserv_radius_auth_port' => 'sometimes|nullable|integer|min:1|max:65535',
            'ocserv_radius_acct_port' => 'sometimes|nullable|integer|min:1|max:65535',
            'ocserv_radius_secret' => 'sometimes|nullable|string|max:2048',
            'ocserv_port' => 'sometimes|nullable|integer|min:1|max:65535',
            'ocserv_domain' => 'sometimes|nullable|string|max:255',
            'ocserv_tls_cert_pem' => 'sometimes|nullable|string|max:65535',
            'ocserv_tls_key_pem' => 'sometimes|nullable|string|max:65535',
            'host' => 'nullable|string|max:255',
            'ssh_port' => 'nullable|integer|min:1|max:65535',
            'ssh_user' => 'nullable|string|max:64',
            'ssh_password' => 'nullable|string|max:2048',
            'notes' => 'nullable|string|max:2048',
            'agent_enabled' => 'sometimes|nullable|boolean',
            'node_nat_interface' => 'sometimes|nullable|string|max:32',
            'node_bandwidth_interface' => 'sometimes|nullable|string|max:32',
            'nat_topology' => 'sometimes|nullable|string|in:combined,split_access,split_nat',
            'cn_public_iface' => 'sometimes|nullable|string|max:32',
            'hk_public_iface' => 'sometimes|nullable|string|max:32',
            'peer_link_iface' => 'sometimes|nullable|string|max:32',
            'peer_link_local_ip' => 'sometimes|nullable|string|max:64',
            'peer_link_remote_ip' => 'sometimes|nullable|string|max:64',
            'link_tunnel_type' => 'sometimes|nullable|string|max:32',
            'peer_link_wg_private_key' => 'sometimes|nullable|string|max:2048',
            'peer_link_wg_peer_public_key' => 'sometimes|nullable|string|max:191',
            'peer_link_wg_endpoint' => 'sometimes|nullable|string|max:255',
            'peer_link_wg_allowed_ips' => 'sometimes|nullable|string|max:255',
            'split_nat_host' => 'sometimes|nullable|string|max:255',
            'split_nat_ssh_port' => 'sometimes|nullable|integer|min:1|max:65535',
            'split_nat_ssh_user' => 'sometimes|nullable|string|max:64',
            'split_nat_ssh_password' => 'sometimes|nullable|string|max:2048',
            'split_nat_hk_public_iface' => 'sometimes|nullable|string|max:32',
            'split_nat_multi_public_ip_enabled' => 'sometimes|nullable|boolean',
        ]);
        if (array_key_exists('ssh_password', $v) && $v['ssh_password'] === '') {
            unset($v['ssh_password']);
        }
        if (array_key_exists('split_nat_ssh_password', $v) && $v['split_nat_ssh_password'] === '') {
            unset($v['split_nat_ssh_password']);
        }
        $this->fillProtocolDetails($v, $s);
        $merged = array_merge($s->only([
            'nat_topology',
            'cn_public_iface',
            'hk_public_iface',
            'peer_link_iface',
            'split_nat_host',
            'split_nat_ssh_user',
            'split_nat_ssh_password',
        ]), $v);
        $topo = (string) ($merged['nat_topology'] ?? 'combined');
        if ($topo === 'combined') {
            if (empty($merged['cn_public_iface']) || empty($merged['hk_public_iface'])) {
                abort(422, '一体模式下必须填写 cn_public_iface 与 hk_public_iface');
            }
            $v['node_nat_interface'] = (string) $merged['hk_public_iface'];
            $v['node_bandwidth_interface'] = (string) $merged['hk_public_iface'];
        } else {
            if (empty($merged['cn_public_iface']) || empty($merged['peer_link_iface']) || empty($merged['split_nat_host']) || empty($merged['split_nat_ssh_user']) || empty($merged['split_nat_ssh_password'])) {
                abort(422, '分体模式下必须填写 cn_public_iface、peer_link_iface、NAT服务器 host/ssh_user/ssh_password');
            }
            if (($merged['link_tunnel_type'] ?? '') === 'wireguard') {
                if (empty($merged['peer_link_wg_private_key']) || empty($merged['peer_link_wg_peer_public_key']) || empty($merged['peer_link_wg_endpoint']) || empty($merged['peer_link_local_ip'])) {
                    abort(422, '分体+WireGuard 互联需填写互联网卡WG私钥、对端公钥、对端地址与本机互联IP');
                }
            }
            $v['split_nat_server_id'] = $this->resolveSplitNatServerId($merged, $s);
            $v['node_nat_interface'] = (string) $merged['peer_link_iface'];
            $v['node_bandwidth_interface'] = (string) $merged['peer_link_iface'];
        }
        $v['config_revision_ts'] = time();
        $s->update($this->filterToServersTableColumns($v));
        return response()->json($s);
    }

    private function fillProtocolDetails(array &$v, ?Server $existing): void
    {
        $protocol = (string) ($v['protocol'] ?? ($existing?->protocol ?? ''));
        if ($protocol === 'wireguard') {
            $priv = $v['wg_private_key'] ?? null;
            if (!$priv && !$existing?->wg_private_key_enc) {
                [$genPriv, $genPub] = $this->generateWireguardServerKeypair();
                $v['wg_private_key_enc'] = Crypt::encryptString($genPriv);
                $v['wg_public_key'] = $genPub;
            } elseif ($priv) {
                [$ok, $pub] = $this->deriveWireguardPublicFromPrivate($priv);
                if (!$ok) {
                    abort(422, 'wg_private_key 格式无效');
                }
                $v['wg_private_key_enc'] = Crypt::encryptString((string) $priv);
                $v['wg_public_key'] = $pub;
            } elseif ($existing?->wg_private_key_enc && !array_key_exists('wg_public_key', $v)) {
                $oldPriv = Crypt::decryptString((string) $existing->wg_private_key_enc);
                [$ok, $pub] = $this->deriveWireguardPublicFromPrivate($oldPriv);
                if ($ok) {
                    $v['wg_public_key'] = $pub;
                }
            }
            unset($v['wg_private_key']);
            // wireguard 不要求 radius 字段
        } elseif ($protocol === 'ocserv') {
            $host = $v['ocserv_radius_host'] ?? ($existing?->ocserv_radius_host ?? null);
            $secret = $v['ocserv_radius_secret'] ?? ($existing?->ocserv_radius_secret ?? null);
            if (!$host || !$secret) {
                abort(422, 'ocserv 协议必须填写 RADIUS 认证服务器与密钥');
            }
            $v['ocserv_radius_auth_port'] = $v['ocserv_radius_auth_port'] ?? ($existing?->ocserv_radius_auth_port ?? 1812);
            $v['ocserv_radius_acct_port'] = $v['ocserv_radius_acct_port'] ?? ($existing?->ocserv_radius_acct_port ?? 1813);
            $v['ocserv_port'] = $v['ocserv_port'] ?? ($existing?->ocserv_port ?? 443);
            $v['ocserv_domain'] = $v['ocserv_domain'] ?? ($existing?->ocserv_domain ?? null);
            $v['ocserv_tls_cert_pem'] = $v['ocserv_tls_cert_pem'] ?? ($existing?->ocserv_tls_cert_pem ?? null);
            $v['ocserv_tls_key_pem'] = $v['ocserv_tls_key_pem'] ?? ($existing?->ocserv_tls_key_pem ?? null);
            if (empty($v['ocserv_domain']) || empty($v['ocserv_tls_cert_pem']) || empty($v['ocserv_tls_key_pem'])) {
                abort(422, 'ocserv 协议必须填写服务端口/绑定域名/SSL 证书与私钥');
            }
            unset($v['wg_private_key']);
        }
    }

    private function resolveSplitNatServerId(array $data, ?Server $owner): ?int
    {
        $host = trim((string) ($data['split_nat_host'] ?? ''));
        if ($host === '') {
            return null;
        }
        $q = Server::query()->where('host', $host);
        if ($owner) {
            $q->where('id', '!=', $owner->id);
        }
        $found = $q->orderBy('id')->first();
        if ($found) {
            return (int) $found->id;
        }
        $new = Server::create($this->filterToServersTableColumns([
            'hostname' => ($owner?->hostname ?: 'split-access').'-nat',
            'region' => (string) ($data['region'] ?? ($owner?->region ?? 'default')),
            'role' => 'split_nat',
            'protocol' => 'wireguard',
            'host' => $host,
            'ssh_port' => (int) ($data['split_nat_ssh_port'] ?? 22),
            'ssh_user' => (string) ($data['split_nat_ssh_user'] ?? 'root'),
            'ssh_password' => (string) ($data['split_nat_ssh_password'] ?? ''),
            'nat_topology' => 'split_nat',
            'cn_public_iface' => null,
            'hk_public_iface' => (string) ($data['split_nat_hk_public_iface'] ?? 'eth0'),
            'agent_enabled' => true,
            'node_nat_interface' => (string) ($data['split_nat_hk_public_iface'] ?? 'eth0'),
            'node_bandwidth_interface' => (string) ($data['split_nat_hk_public_iface'] ?? 'eth0'),
            'config_revision_ts' => time(),
        ]));
        return (int) $new->id;
    }

    private function generateWireguardServerKeypair(): array
    {
        $priv = random_bytes(32);
        $priv[0] = chr(ord($priv[0]) & 248);
        $priv[31] = chr((ord($priv[31]) & 127) | 64);
        $pub = sodium_crypto_scalarmult_base($priv);
        return [base64_encode($priv), base64_encode($pub)];
    }

    private function deriveWireguardPublicFromPrivate(string $privateKeyBase64): array
    {
        $raw = base64_decode(trim($privateKeyBase64), true);
        if ($raw === false || strlen($raw) !== 32) {
            return [false, null];
        }
        $raw[0] = chr(ord($raw[0]) & 248);
        $raw[31] = chr((ord($raw[31]) & 127) | 64);
        $pub = sodium_crypto_scalarmult_base($raw);
        return [true, base64_encode($pub)];
    }

    public function deleteServer(int $id): JsonResponse
    {
        Server::findOrFail($id)->delete();
        return response()->json(null, 204);
    }

    public function listUsers(): JsonResponse
    {
        return response()->json(User::orderBy('id')->get(['id', 'email', 'role', 'created_at', 'name']));
    }

    public function updateUserRole(Request $request, int $id): JsonResponse
    {
        $request->validate(['role' => 'required|string|in:admin,user']);
        $u = User::findOrFail($id);
        $u->update(['role' => $request->role]);
        return response()->json($u->only(['id', 'email', 'role']));
    }

    /**
     * POST /api/v1/admin/users
     * 创建后台管理员账户
     */
    public function createUser(Request $request): JsonResponse
    {
        $v = $request->validate([
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'name' => 'nullable|string|max:255',
            'role' => 'nullable|string|in:admin,user',
        ]);

        $u = User::create([
            'email' => $v['email'],
            'password' => Hash::make($v['password']),
            'name' => $v['name'] ?? $v['email'],
            'role' => $v['role'] ?? 'admin',
        ]);

        return response()->json($u->only(['id', 'email', 'role', 'created_at', 'name']), 201);
    }

    /**
     * PUT /api/v1/admin/users/{id}/password
     * 重置用户密码
     */
    public function updateUserPassword(Request $request, int $id): JsonResponse
    {
        $u = User::findOrFail($id);

        // 默认管理员（id=1）密码仅允许本人修改
        if ((int) $u->id === 1 && (int) (Auth::id() ?? 0) !== 1) {
            return response()->json(['message' => '禁止修改默认管理员账号（id=1）的密码'], 403);
        }

        $v = $request->validate([
            'password' => 'required|string|min:6',
        ]);

        $u->update([
            'password' => Hash::make($v['password']),
        ]);

        return response()->json($u->only(['id', 'email', 'role', 'created_at', 'name']));
    }

    /**
     * DELETE /api/v1/admin/users/{id}
     * 删除用户（仅当用户无关联 VPN/订单数据时允许）
     */
    public function deleteUser(int $id): JsonResponse
    {
        $u = User::findOrFail($id);

        // 默认管理员（id=1）禁止删除
        if ((int) $u->id === 1) {
            return response()->json(['message' => '禁止删除默认管理员账号（id=1）'], 409);
        }

        // 禁止删除当前登录账号，避免直接把自己干掉
        if ((int) $id === (int) (Auth::id() ?? 0)) {
            return response()->json(['message' => '禁止删除当前登录的管理员账户'], 409);
        }

        // 禁止删除最后一个管理员
        if (($u->role ?? '') === 'admin') {
            $adminCount = User::query()->where('role', 'admin')->count();
            if ($adminCount <= 1) {
                return response()->json(['message' => '禁止删除最后一个管理员账户'], 409);
            }
        }

        // 保护：若仍有关联 VPN 用户/订单数据则禁止删除，避免破坏数据完整性
        $hasVpnUsers = VpnUser::query()->where('user_id', $id)->exists();
        $hasOrders = Order::query()->where('user_id', $id)->exists();
        if ($hasVpnUsers || $hasOrders) {
            return response()->json(['message' => '该用户存在关联 VPN/订单数据，禁止删除'], 409);
        }

        $u->delete();
        return response()->json(null, 204);
    }

    public function listOrders(): JsonResponse
    {
        return response()->json(
            Order::query()
                ->with([
                    'user:id,email',
                    'vpnUser:id,email,name,region',
                    'product:id,name',
                    'reseller:id,name',
                    'incomeRecords' => static fn ($q) => $q->orderBy('id'),
                ])
                ->withCount('incomeRecords')
                ->orderByDesc('id')
                ->get()
        );
    }

    /**
     * 已购产品（分销）：每条 A 站订阅订单一行，与 B 站「已购产品」按 a_order_id 对齐。
     * GET /api/v1/admin/purchased_products
     */
    public function listPurchasedProductsAdmin(): JsonResponse
    {
        $orders = Order::query()
            ->whereNotNull('reseller_id')
            ->with([
                'vpnUser:id,user_id,email,name,region,radius_username',
                'product:id,name',
                'reseller:id,name',
            ])
            ->withCount('incomeRecords')
            ->orderByDesc('id')
            ->get();

        $list = $orders->map(function (Order $o) {
            $row = $o->toArray();
            $exp = $o->expires_at;
            $row['entitled'] = $exp ? $exp->isFuture() : null;
            return $row;
        });

        return response()->json($list->values());
    }

    /**
     * DELETE /api/v1/admin/orders/{id}
     * 删除订单并在不再拥有任何有效订单时释放 VPN 资源（RADIUS/WireGuard/IP）。
     */
    public function deleteOrder(int $id): JsonResponse
    {
        $order = Order::with('vpnUser:id,email,name,region,radius_username,radius_password,reseller_id')->findOrFail($id);

        // 规则：仅允许删除“无效订单”
        // - 订单状态必须是 pending（未支付）
        // - 必须超过 24 小时未支付
        // 其他有效订单或未到 24 小时的订单不可删除。
        $createdAt = $order->created_at;
        $cutoff = now()->subHours(24);
        $isPending = ($order->status ?? '') === 'pending';
        // Carbon 版本兼容：避免使用少见方法 lessThanOrEqual
        $isOlderThan24h = $createdAt ? ($createdAt->getTimestamp() <= $cutoff->getTimestamp()) : false;
        if (!$isPending || !$isOlderThan24h) {
            return response()->json([
                'message' => '禁止删除：仅允许删除超过 24 小时未支付的订单（状态 pending）。',
            ], 409);
        }

        $vpnUserId = $order->vpn_user_id;
        $userId = $order->user_id;

        $order->delete();

        // 新数据走 vpn_user_id；兼容旧数据走 user_id
        if ($vpnUserId) {
            $vpnUser = VpnUser::find($vpnUserId);
            if ($vpnUser) {
                $stillEntitled = Order::query()
                    ->where('vpn_user_id', $vpnUserId)
                    ->whereIn('status', ['paid', 'active'])
                    ->where('expires_at', '>', now())
                    ->exists();

                if (!$stillEntitled) {
                    // 释放出口公网 IP 绑定
                    IpPool::query()
                        ->where('vpn_user_id', $vpnUserId)
                        ->update([
                            'status' => 'free',
                            'vpn_user_id' => null,
                            'last_unbound_at' => now(),
                        ]);

                    // 释放 WireGuard 内网 IP 与 peer
                    DB::table('vpn_ip_allocations')->where('vpn_user_id', $vpnUserId)->delete();
                    DB::table('wireguard_peers')->where('vpn_user_id', $vpnUserId)->delete();
                }

                app(\App\Services\FreeradiusSyncService::class)->syncVpnUser($vpnUser);
            }
        } elseif ($userId) {
            app(\App\Services\FreeradiusSyncService::class)->syncUserId((int) $userId);
        }

        return response()->json(null, 204);
    }

    public function listOrdersByUser(int $userId): JsonResponse
    {
        return response()->json(Order::where('user_id', $userId)->with('product:id,name')->orderBy('id', 'desc')->get());
    }

    public function createOrder(Request $request, int $userId): JsonResponse
    {
        User::findOrFail($userId);
        $v = $request->validate(['product_id' => 'required|exists:products,id', 'status' => 'nullable|string']);
        $product = Product::findOrFail($v['product_id']);
        $expiresAt = Carbon::now()->addDays($product->duration_days);
        $order = Order::create([
            'user_id' => $userId,
            'product_id' => $product->id,
            'biz_order_no' => (string) Str::ulid(),
            'status' => $v['status'] ?? 'active',
            'expires_at' => $expiresAt,
        ]);
        return response()->json($order, 201);
    }

    public function listVpnUsersByUser(int $userId): JsonResponse
    {
        User::findOrFail($userId);
        return response()->json(
            VpnUser::query()
                ->where('user_id', $userId)
                ->orderBy('id', 'desc')
                ->get()
        );
    }

    public function createVpnUser(Request $request, int $userId): JsonResponse
    {
        User::findOrFail($userId);
        $v = $request->validate([
            'name' => 'required|string|max:255',
            'status' => 'nullable|string|max:32',
            'radius_username' => 'nullable|string|max:64',
            'radius_password' => 'nullable|string|max:191',
        ]);

        $vpnUser = VpnUser::create([
            'user_id' => $userId,
            'name' => $v['name'],
            'status' => $v['status'] ?? 'active',
            'radius_username' => $v['radius_username'] ?? null,
            'radius_password' => $v['radius_password'] ?? null,
        ]);

        return response()->json($vpnUser, 201);
    }

    public function deleteVpnUser(int $userId, int $vpnUserId): JsonResponse
    {
        $vpnUser = $userId > 0
            ? VpnUser::query()->where('user_id', $userId)->findOrFail($vpnUserId)
            : VpnUser::findOrFail($vpnUserId);
        $vpnUser->delete();
        return response()->json(null, 204);
    }

    public function listVpnUsersAdmin(): JsonResponse
    {
        $now = now();

        // 仅展示 B 站（分销商）终端用户；同一邮箱+分销商取 MIN(id) 作为代表行，不按「每单一条 vpn_users」重复列出
        $canonicalVpnUserIds = DB::table('vpn_users')
            ->whereNotNull('reseller_id')
            ->selectRaw('MIN(id) as id')
            ->groupBy(DB::raw('LOWER(TRIM(email))'), 'reseller_id');

        // 同一邮箱+分销商可能有多条 vpn_users（多笔订阅各建一行），订单挂在任意一条上；按组合取全局最新订单 id
        $latestOrderPerEmailReseller = DB::table('orders as o')
            ->join('vpn_users as vu_o', 'vu_o.id', '=', 'o.vpn_user_id')
            ->whereNotNull('o.vpn_user_id')
            ->whereNotNull('o.reseller_id')
            ->whereNotNull('vu_o.reseller_id')
            ->selectRaw('LOWER(TRIM(vu_o.email)) as em, vu_o.reseller_id as rid, MAX(o.id) as max_order_id')
            ->groupBy(DB::raw('LOWER(TRIM(vu_o.email))'), 'vu_o.reseller_id');

        $siblingCountByEmailReseller = DB::table('vpn_users')
            ->whereNotNull('reseller_id')
            ->selectRaw('LOWER(TRIM(email)) as em, reseller_id as rid, COUNT(*) as sibling_count')
            ->groupBy(DB::raw('LOWER(TRIM(email))'), 'reseller_id');

        $rows = DB::table('vpn_users as vu')
            ->joinSub($canonicalVpnUserIds, 'canon', 'canon.id', '=', 'vu.id')
            ->leftJoinSub($latestOrderPerEmailReseller, 'mx', function ($join) {
                $join->whereColumn('vu.reseller_id', 'mx.rid')
                    ->whereRaw('LOWER(TRIM(vu.email)) = mx.em');
            })
            ->leftJoinSub($siblingCountByEmailReseller, 'sc', function ($join) {
                $join->whereColumn('vu.reseller_id', 'sc.rid')
                    ->whereRaw('LOWER(TRIM(vu.email)) = sc.em');
            })
            ->leftJoin('orders as o', 'o.id', '=', 'mx.max_order_id')
            ->leftJoin('vpn_users as vu_ord', 'vu_ord.id', '=', 'o.vpn_user_id')
            ->leftJoin('resellers as r', 'r.id', '=', 'vu.reseller_id')
            ->leftJoin('products as p', 'p.id', '=', 'o.product_id')
            ->select([
                'vu.id as vpn_user_id',
                'vu.user_id',
                'vu.email as user_email',
                'vu.name as user_name',
                'vu.name as vpn_name',
                'vu.status as vpn_status',
                'vu.radius_username',
                'vu.created_at as registered_at',
                DB::raw('COALESCE(NULLIF(TRIM(vu_ord.region), ""), vu.region) as region'),
                'o.id as last_order_id',
                'o.status as last_order_status',
                'o.expires_at as last_expires_at',
                'p.name as last_product_name',
                'r.id as reseller_id',
                'r.name as reseller_name',
                DB::raw('COALESCE(sc.sibling_count, 1) as sibling_count'),
            ])
            ->orderByDesc('vu.id')
            ->get();

        $list = $rows->map(function ($x) use ($now) {
            $x->last_expires_at = $x->last_expires_at ? Carbon::parse($x->last_expires_at)->format('c') : null;
            $x->registered_at = $x->registered_at ? Carbon::parse($x->registered_at)->format('c') : null;
            $x->entitled = $x->last_expires_at ? Carbon::parse($x->last_expires_at)->gt($now) : null;
            return $x;
        });

        return response()->json($list->values());
    }

    public function showVpnUser(int $id): JsonResponse
    {
        $vpnUser = VpnUser::with('reseller:id,name')->findOrFail($id);

        $siblingIds = $this->bResellerSiblingVpnUserIds($vpnUser);
        $lastOrder = Order::query()
            ->whereIn('vpn_user_id', $siblingIds)
            ->whereNotNull('reseller_id')
            ->with('reseller:id,name', 'product:id,name')
            ->orderByDesc('id')
            ->first();

        if (!$lastOrder && $vpnUser->user_id) {
            $lastOrder = Order::query()
                ->where('user_id', $vpnUser->user_id)
                ->whereNotNull('reseller_id')
                ->with('reseller:id,name', 'product:id,name')
                ->orderByDesc('id')
                ->first();
        }

        $user = $vpnUser->user_id ? User::find($vpnUser->user_id) : null;

        $effectiveRegion = $vpnUser->region;
        if ($lastOrder && $lastOrder->vpn_user_id) {
            $ordRegion = VpnUser::query()->whereKey($lastOrder->vpn_user_id)->value('region');
            if ($ordRegion !== null && trim((string) $ordRegion) !== '') {
                $effectiveRegion = $ordRegion;
            }
        }

        $vpnUserPayload = $vpnUser->toArray();
        $vpnUserPayload['region'] = $effectiveRegion;

        return response()->json([
            'vpn_user' => $vpnUserPayload,
            'user' => $user?->only(['id', 'email', 'name', 'role']),
            'reseller' => $vpnUser->reseller ? $vpnUser->reseller->only(['id', 'name']) : ($lastOrder?->reseller?->only(['id', 'name'])),
            'latest_order' => $lastOrder ? [
                'id' => $lastOrder->id,
                'status' => $lastOrder->status,
                'expires_at' => $lastOrder->expires_at?->format('c'),
                'product' => $lastOrder->product ? $lastOrder->product->only(['id', 'name']) : null,
            ] : null,
        ]);
    }

    /**
     * 同一分销商下相同邮箱的所有 vpn_users.id（含当前），订单可能挂在其中任意一行。
     */
    private function bResellerSiblingVpnUserIds(VpnUser $vpnUser): array
    {
        if (!$vpnUser->reseller_id || !is_string($vpnUser->email) || trim($vpnUser->email) === '') {
            return [(int) $vpnUser->id];
        }

        return VpnUser::query()
            ->where('reseller_id', $vpnUser->reseller_id)
            ->whereRaw('LOWER(TRIM(email)) = ?', [strtolower(trim($vpnUser->email))])
            ->pluck('id')
            ->all();
    }

    public function updateVpnUser(Request $request, int $id): JsonResponse
    {
        $vpnUser = VpnUser::findOrFail($id);
        $v = $request->validate([
            'name' => 'sometimes|string|max:255',
            'status' => 'sometimes|string|max:32',
            'region' => 'sometimes|nullable|string|max:64',
            'radius_username' => 'nullable|string|max:64',
            'radius_password' => 'nullable|string|max:191',
        ]);
        $oldRegion = $vpnUser->region;
        $vpnUser->update($v);

        // 若区域改变，重新按区域绑定出口 IP，并在可用条件下更新 wireguard peer 的内网 IP/endpoint
        if (array_key_exists('region', $v) && $v['region'] !== $oldRegion) {
            $this->rebindRegionResources($vpnUser);
        }
        return response()->json($vpnUser);
    }

    private function rebindRegionResources(VpnUser $vpnUser): void
    {
        $region = $vpnUser->region;
        if (!$region) {
            return;
        }

        // 1) 释放旧的 ip_pool 绑定（出口公网 IP），并尝试绑定新的 region IP
        DB::transaction(function () use ($vpnUser, $region) {
            $old = IpPool::query()->where('vpn_user_id', $vpnUser->id)->lockForUpdate()->first();
            if ($old) {
                $old->update([
                    'status' => 'free',
                    'vpn_user_id' => null,
                    'last_unbound_at' => now(),
                ]);
            }

            $new = IpPool::query()
                ->where('region', $region)
                ->where('status', 'free')
                ->whereNull('vpn_user_id')
                ->lockForUpdate()
                ->orderBy('id')
                ->first();
            if ($new) {
                $new->update([
                    'status' => 'used',
                    'vpn_user_id' => $vpnUser->id,
                ]);

                if ($vpnUser->radius_username) {
                    DB::table('radreply')
                        ->where('username', $vpnUser->radius_username)
                        ->where('attribute', 'Framed-IP-Address')
                        ->delete();
                    DB::table('radreply')->insert([
                        'username' => $vpnUser->radius_username,
                        'attribute' => 'Framed-IP-Address',
                        'op' => '=',
                        'value' => $new->ip_address,
                    ]);
                }
            }
        });

        // 2) 若已存在 wireguard peer，则根据新 region 重新分配内网 IP，并更新 peer endpoint/server
        $peer = DB::table('wireguard_peers')->where('vpn_user_id', $vpnUser->id)->first();
        if (!$peer) {
            return;
        }

        $exit = DB::table('exit_nodes')->where('region', $region)->orderBy('id')->first();
        if (!$exit || !isset($exit->server_id, $exit->ip_address)) {
            return;
        }

        $server = DB::table('servers')->where('id', (int) $exit->server_id)->first();
        if (!$server || ($server->protocol ?? null) !== 'wireguard') {
            return;
        }

        // 清理旧内网分配，并重新分配
        DB::table('vpn_ip_allocations')->where('vpn_user_id', $vpnUser->id)->delete();
        $cidrs = (string) ($server->vpn_ip_cidrs ?? '');
        $internalIp = null;
        if ($cidrs) {
            // 简化：复用 ResellerProvisionController 同款 CIDR 分配逻辑（内联实现）
            $cidrList = array_values(array_filter(array_map('trim', preg_split('/[\\s,]+/', $cidrs) ?: [])));
            foreach ($cidrList as $cidr) {
                if (!preg_match('/^([0-9]{1,3}(?:\\.[0-9]{1,3}){3})\\/(\\d{1,2})$/', $cidr, $m)) continue;
                $baseLong = ip2long($m[1]);
                $mask = (int) $m[2];
                if ($baseLong === false || $mask < 16 || $mask > 30) continue;
                $count = 1 << (32 - $mask);
                $start = 2;
                $end = $count - 2;
                for ($i = $start; $i <= $end; $i++) {
                    $candidate = long2ip($baseLong + $i);
                    if (!$candidate) continue;
                    try {
                        DB::table('vpn_ip_allocations')->insert([
                            'server_id' => (int) $exit->server_id,
                            'vpn_user_id' => $vpnUser->id,
                            'ip_address' => $candidate,
                            'region' => $region,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        $internalIp = $candidate;
                        break 2;
                    } catch (\Throwable $e) {
                        continue;
                    }
                }
            }
        }

        if ($internalIp) {
            DB::table('wireguard_peers')->where('vpn_user_id', $vpnUser->id)->update([
                'server_id' => (int) $exit->server_id,
                'allowed_ips' => $internalIp . '/32',
                'endpoint' => (string) $exit->ip_address,
                'updated_at' => now(),
            ]);
        }
    }

    public function listProducts(): JsonResponse
    {
        return response()->json(Product::orderBy('id')->get());
    }

    public function createProduct(Request $request): JsonResponse
    {
        $this->mergeEmptyProductLimitFields($request);
        $v = $request->validate([
            'name' => 'required|string',
            'description' => 'nullable|string',
            'price_cents' => 'required|integer|min:0',
            'currency' => 'nullable|string',
            'duration_days' => 'nullable|integer|min:1',
            'enable_radius' => 'nullable|boolean',
            'enable_wireguard' => 'nullable|boolean',
            'requires_dedicated_public_ip' => 'nullable|boolean',
            'bandwidth_limit_kbps' => 'nullable|integer|min:1|max:100000000',
            'traffic_quota_gb' => 'nullable|numeric|min:0|max:1000000',
        ]);
        $v['currency'] = $v['currency'] ?? 'USD';
        $v['duration_days'] = $v['duration_days'] ?? 30;
        $v['enable_radius'] = array_key_exists('enable_radius', $v) ? (bool) $v['enable_radius'] : true;
        $v['enable_wireguard'] = array_key_exists('enable_wireguard', $v) ? (bool) $v['enable_wireguard'] : true;
        $v['requires_dedicated_public_ip'] = array_key_exists('requires_dedicated_public_ip', $v) ? (bool) $v['requires_dedicated_public_ip'] : false;
        $v['traffic_quota_bytes'] = $this->nullableTrafficQuotaBytesFromGb($v['traffic_quota_gb'] ?? null);
        unset($v['traffic_quota_gb']);
        $p = Product::create($v);
        return response()->json($p, 201);
    }

    public function updateProduct(Request $request, int $id): JsonResponse
    {
        $this->mergeEmptyProductLimitFields($request);
        $p = Product::findOrFail($id);
        $v = $request->validate([
            'name' => 'sometimes|string',
            'description' => 'nullable|string',
            'price_cents' => 'sometimes|integer|min:0',
            'currency' => 'nullable|string',
            'duration_days' => 'sometimes|integer|min:1',
            'enable_radius' => 'nullable|boolean',
            'enable_wireguard' => 'nullable|boolean',
            'requires_dedicated_public_ip' => 'nullable|boolean',
            'bandwidth_limit_kbps' => 'nullable|integer|min:1|max:100000000',
            'traffic_quota_gb' => 'nullable|numeric|min:0|max:1000000',
        ]);
        if (array_key_exists('traffic_quota_gb', $v)) {
            $v['traffic_quota_bytes'] = $this->nullableTrafficQuotaBytesFromGb($v['traffic_quota_gb']);
            unset($v['traffic_quota_gb']);
        }
        $p->update($v);
        return response()->json($p);
    }

    public function deleteProduct(int $id): JsonResponse
    {
        Product::findOrFail($id)->delete();
        return response()->json(null, 204);
    }

    private function mergeEmptyProductLimitFields(Request $request): void
    {
        foreach (['traffic_quota_gb', 'bandwidth_limit_kbps'] as $k) {
            if ($request->has($k) && $request->input($k) === '') {
                $request->merge([$k => null]);
            }
        }
    }

    /** GiB → bytes；空或 0 表示不限制。 */
    private function nullableTrafficQuotaBytesFromGb(null|int|float|string $gb): ?int
    {
        if ($gb === null || $gb === '') {
            return null;
        }
        $f = (float) $gb;
        if ($f <= 0) {
            return null;
        }
        $bytes = (int) round($f * 1073741824);

        return $bytes > 0 ? $bytes : null;
    }

    public function listResellers(): JsonResponse
    {
        return response()->json(
            Reseller::with(['apiKeys' => function ($q) {
                $q->orderByDesc('id');
            }])
                ->orderBy('id')
                ->get()
                ->map(function (Reseller $r) {
                    $latestKey = $r->apiKeys->first();
                    return [
                        'id' => $r->id,
                        'name' => $r->name,
                        'email' => $r->email,
                        'balance_cents' => (int) ($r->balance_cents ?? 0),
                        'balance_enforced' => (bool) ($r->balance_enforced ?? false),
                        'status' => $r->status ?? 'active',
                        'created_at' => $r->created_at,
                        'latest_api_key_preview' => $latestKey ? $this->maskResellerApiKey($latestKey->api_key) : null,
                    ];
                })
        );
    }

    public function createReseller(Request $request): JsonResponse
    {
        $v = $request->validate([
            'name' => 'required|string',
            'email' => 'nullable|string|email|max:255|unique:resellers,email',
            'password' => 'nullable|string|min:8|max:255',
            'balance_cents' => 'nullable|integer|min:0',
            'balance_enforced' => 'nullable|boolean',
            'status' => 'nullable|string|in:active,suspended',
        ]);
        $payload = [
            'name' => $v['name'],
            'email' => $v['email'] ?? null,
            'password' => $v['password'] ?? null,
            'balance_cents' => (int) ($v['balance_cents'] ?? 0),
            'balance_enforced' => (bool) ($v['balance_enforced'] ?? false),
            'status' => $v['status'] ?? 'active',
        ];
        $r = Reseller::create($payload);
        return response()->json($r->makeHidden(['password']), 201);
    }

    public function getReseller(int $id): JsonResponse
    {
        return response()->json(Reseller::findOrFail($id));
    }

    public function updateReseller(Request $request, int $id): JsonResponse
    {
        $r = Reseller::findOrFail($id);
        $v = $request->validate([
            'name' => 'sometimes|string',
            'email' => 'sometimes|nullable|string|email|max:255|unique:resellers,email,' . $id,
            'password' => 'sometimes|nullable|string|min:8|max:255',
            'balance_enforced' => 'sometimes|boolean',
            'status' => 'sometimes|string|in:active,suspended',
        ]);
        if (array_key_exists('password', $v)) {
            if ($v['password'] === null || $v['password'] === '') {
                unset($v['password']);
            }
            // 模型 casts password => hashed，此处传明文即可
        }
        $r->update($v);
        return response()->json($r->fresh()->makeHidden(['password']));
    }

    /**
     * 管理员调整分销商余额（delta 可正可负），并记流水
     */
    public function adjustResellerBalance(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'delta_cents' => 'required|integer',
            'note' => 'nullable|string|max:500',
        ]);

        $reseller = Reseller::findOrFail($id);

        DB::transaction(function () use ($reseller, $data) {
            $locked = Reseller::whereKey($reseller->id)->lockForUpdate()->first();
            $delta = (int) $data['delta_cents'];
            $newBal = (int) $locked->balance_cents + $delta;
            if ($newBal < 0) {
                throw new HttpResponseException(response()->json([
                    'message' => '调整后余额不能为负',
                    'balance_cents' => (int) $locked->balance_cents,
                ], 422));
            }
            $locked->balance_cents = $newBal;
            $locked->save();

            ResellerBalanceTransaction::create([
                'reseller_id' => $locked->id,
                'amount_cents' => $delta,
                'balance_after_cents' => $newBal,
                'type' => 'admin_adjust',
                'meta' => array_filter([
                    'note' => $data['note'] ?? null,
                ]),
            ]);
        });

        return response()->json([
            'balance_cents' => (int) $reseller->fresh()->balance_cents,
        ]);
    }

    public function deleteReseller(int $id): JsonResponse
    {
        Reseller::findOrFail($id)->delete();
        return response()->json(null, 204);
    }

    public function createResellerApiKey(Request $request, int $id): JsonResponse
    {
        $reseller = Reseller::findOrFail($id);
        $name = $request->input('name', '');
        $replaceAll = filter_var($request->input('replace_all', false), FILTER_VALIDATE_BOOLEAN);
        if ($replaceAll) {
            ResellerApiKey::where('reseller_id', $reseller->id)->delete();
        }
        $key = ResellerApiKey::create(['reseller_id' => $reseller->id, 'name' => $name]);
        return response()->json([
            'id' => $key->id,
            'api_key' => $key->api_key,
            'name' => $key->name,
            'replace_all' => $replaceAll,
        ], 201);
    }

    public function listResellerApiKeys(int $id): JsonResponse
    {
        $reseller = Reseller::with(['apiKeys' => function ($q) {
            $q->orderByDesc('id');
        }])->findOrFail($id);

        return response()->json([
            'reseller' => [
                'id' => $reseller->id,
                'name' => $reseller->name,
            ],
            'api_keys' => $reseller->apiKeys->map(function (ResellerApiKey $k) {
                return [
                    'id' => $k->id,
                    'name' => $k->name,
                    'api_key' => $k->api_key,
                    'created_at' => $k->created_at,
                ];
            }),
        ]);
    }

    /**
     * 分销商余额流水（用于 admin 页面：分页 + 搜索）
     * GET /api/v1/admin/resellers/{id}/balance/transactions
     *
     * query:
     * - type: recharge | provision_purchase | provision_renew | admin_adjust (optional)
     * - q: 支持 id 或 meta(备注等) 模糊匹配 (optional)
     * - page, limit
     */
    public function listResellerBalanceTransactions(Request $request, int $id): JsonResponse
    {
        $v = $request->validate([
            'type' => 'nullable|string|in:recharge,provision_purchase,provision_renew,admin_adjust',
            'q' => 'nullable|string|max:255',
            'page' => 'nullable|integer|min:1',
            'limit' => 'nullable|integer|min:1|max:200',
        ]);

        $page = (int) ($v['page'] ?? 1);
        $limit = (int) ($v['limit'] ?? 20);
        $q = trim((string) ($v['q'] ?? ''));

        $base = ResellerBalanceTransaction::query()
            ->where('reseller_id', $id);

        if (!empty($v['type'])) {
            $base->where('type', $v['type']);
        }

        if ($q !== '') {
            $base->where(function ($qq) use ($q) {
                if (ctype_digit($q)) {
                    $qq->orWhere('id', (int) $q);
                }
                // meta 为 json：使用 cast 进行简单模糊匹配（备注 note 等）
                $qq->orWhereRaw('CAST(meta AS CHAR) LIKE ?', ['%' . $q . '%']);
            });
        }

        $total = (int) (clone $base)->count();

        $items = (clone $base)
            ->orderByDesc('id')
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $rows = $items->map(function (ResellerBalanceTransaction $t) {
            $meta = is_array($t->meta) ? $t->meta : [];
            return [
                'id' => $t->id,
                'type' => $t->type,
                'amount_cents' => (int) $t->amount_cents,
                'balance_after_cents' => (int) $t->balance_after_cents,
                'note' => $meta['note'] ?? null,
                'meta' => $t->meta,
                'created_at' => $t->created_at?->format('c'),
            ];
        })->values()->all();

        return response()->json([
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'items' => $rows,
        ]);
    }

    public function listExitNodes(): JsonResponse
    {
        return response()->json(ExitNode::with('server:id,hostname')->orderBy('id')->get());
    }

    public function createExitNode(Request $request): JsonResponse
    {
        $v = $request->validate([
            'server_id' => 'required|exists:servers,id',
            'ip_address' => 'required|string',
            'public_iface' => 'nullable|string|max:32',
            'region' => 'required|string',
            'notes' => 'nullable|string|max:4096',
            'cost_cents' => 'nullable|integer|min:0',
        ]);
        $e = ExitNode::create($v);
        return response()->json($e, 201);
    }

    public function updateExitNode(Request $request, int $id): JsonResponse
    {
        $e = ExitNode::findOrFail($id);
        $v = $request->validate([
            'server_id' => 'required|exists:servers,id',
            'ip_address' => 'required|string',
            'public_iface' => 'nullable|string|max:32',
            'region' => 'required|string',
            'notes' => 'nullable|string|max:4096',
            'cost_cents' => 'nullable|integer|min:0',
        ]);
        $e->update($v);
        return response()->json($e);
    }

    public function deleteExitNode(int $id): JsonResponse
    {
        ExitNode::findOrFail($id)->delete();
        return response()->json(null, 204);
    }

    public function listIpPool(): JsonResponse
    {
        $list = IpPool::with(['creator:id,email', 'vpnUser:id,name', 'server:id,hostname,region'])
            ->orderBy('id')
            ->get();
        return response()->json($list);
    }

    public function listSnatMaps(Request $request): JsonResponse
    {
        $status = trim((string) $request->input('status', ''));
        $qText = trim((string) $request->input('q', ''));
        $vpnUserId = (int) $request->input('vpn_user_id', 0);
        $serverId = (int) $request->input('server_id', 0);
        $page = max(1, (int) $request->input('page', 1));
        $perPage = min(max((int) $request->input('per_page', 50), 1), 200);

        $base = DB::table('user_public_ip_snat_maps as m')
            ->leftJoin('vpn_users as vu', 'vu.id', '=', 'm.vpn_user_id')
            ->leftJoin('servers as s', 's.id', '=', 'm.server_id');

        if ($status !== '') {
            $base->where('m.status', $status);
        }
        if ($vpnUserId > 0) {
            $base->where('m.vpn_user_id', $vpnUserId);
        }
        if ($serverId > 0) {
            $base->where('m.server_id', $serverId);
        }
        if ($qText !== '') {
            $like = '%'.$qText.'%';
            $base->where(function ($w) use ($like) {
                $w->where('vu.email', 'like', $like)
                    ->orWhere('m.source_ip', 'like', $like)
                    ->orWhere('m.public_ip', 'like', $like);
            });
        }

        $total = (clone $base)->count('m.id');

        $rows = (clone $base)
            ->select([
                'm.id',
                'm.vpn_user_id',
                'm.server_id',
                'm.nat_interface',
                'm.source_ip',
                'm.public_ip',
                'm.status',
                'm.applied_at',
                'm.released_at',
                'm.created_at',
                'vu.email as vpn_user_email',
                'vu.name as vpn_user_name',
                's.hostname as server_hostname',
                's.region as server_region',
            ])
            ->orderByDesc('m.id')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        return response()->json([
            'data' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ]);
    }

    public function listProvisionAuditLogs(Request $request): JsonResponse
    {
        $event = trim((string) $request->input('event', ''));
        $vpnUserId = (int) $request->input('vpn_user_id', 0);
        $orderId = (int) $request->input('order_id', 0);
        $page = max(1, (int) $request->input('page', 1));
        $perPage = min(max((int) $request->input('per_page', 50), 1), 200);

        $base = DB::table('provision_resource_audit_logs as l')
            ->leftJoin('vpn_users as vu', 'vu.id', '=', 'l.vpn_user_id')
            ->leftJoin('orders as o', 'o.id', '=', 'l.order_id')
            ->leftJoin('products as p', 'p.id', '=', 'l.product_id');

        if ($event !== '') {
            $base->where('l.event', $event);
        }
        if ($vpnUserId > 0) {
            $base->where('l.vpn_user_id', $vpnUserId);
        }
        if ($orderId > 0) {
            $base->where('l.order_id', $orderId);
        }

        $total = (clone $base)->count('l.id');

        $rows = (clone $base)
            ->select([
                'l.id',
                'l.event',
                'l.vpn_user_id',
                'l.order_id',
                'l.product_id',
                'l.reseller_id',
                'l.meta',
                'l.created_at',
                'vu.email as vpn_user_email',
                'o.biz_order_no',
                'p.name as product_name',
            ])
            ->orderByDesc('l.id')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        return response()->json([
            'data' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ]);
    }

    public function createIpPool(Request $request): JsonResponse
    {
        $v = $request->validate([
            'ip_address' => 'required|string',
            'region' => 'required|string',
            'status' => 'nullable|string',
            'server_id' => 'nullable|integer|exists:servers,id',
        ]);
        $v['status'] = $v['status'] ?? 'free';
        // 避免批量导入时因唯一索引报错：同一个 IP 已存在则更新区域/状态，不再抛异常
        $defaults = [
            'region' => $v['region'],
            'status' => $v['status'],
            'server_id' => $v['server_id'] ?? null,
        ];
        if (Auth::check()) {
            $defaults['created_by'] = Auth::id();
        }
        $ip = IpPool::firstOrCreate(
            ['ip_address' => $v['ip_address']],
            $defaults
        );
        if (! $ip->wasRecentlyCreated) {
            $ip->update([
                'region' => $v['region'],
                'status' => $v['status'],
                'server_id' => $v['server_id'] ?? null,
            ]);
        }
        return response()->json($ip, $ip->wasRecentlyCreated ? 201 : 200);
    }

    public function releaseIpPool(int $id): JsonResponse
    {
        $ip = IpPool::findOrFail($id);
        $vpnUserId = $ip->vpn_user_id ? (int) $ip->vpn_user_id : 0;
        $publicIp = (string) $ip->ip_address;
        $ipPoolId = (int) $ip->id;
        $ip->update([
            'status' => 'free',
            'vpn_user_id' => null,
            'last_unbound_at' => now(),
        ]);
        if ($vpnUserId > 0) {
            ProvisionResourceAuditLog::record(
                'ip_pool_admin_release',
                $vpnUserId,
                null,
                null,
                null,
                [
                    'ip_pool_id' => $ipPoolId,
                    'public_ip' => $publicIp,
                ]
            );
            $this->releaseSnatMappingForVpnUser($vpnUserId);
        }
        return response()->json($ip);
    }

    private function releaseSnatMappingForVpnUser(int $vpnUserId): void
    {
        $maps = DB::table('user_public_ip_snat_maps')
            ->where('vpn_user_id', $vpnUserId)
            ->where('status', 'active')
            ->get();
        foreach ($maps as $m) {
            ProvisionResourceAuditLog::record(
                'snat_admin_remove',
                $vpnUserId,
                null,
                null,
                null,
                [
                    'map_id' => (int) $m->id,
                    'server_id' => (int) $m->server_id,
                    'nat_interface' => (string) $m->nat_interface,
                    'source_ip' => (string) $m->source_ip,
                    'public_ip' => (string) $m->public_ip,
                ]
            );
            DB::table('agent_commands')->insert([
                'server_id' => (int) $m->server_id,
                'type' => 'remove_snat_map',
                'payload' => json_encode([
                    'interface' => (string) $m->nat_interface,
                    'source_ip' => (string) $m->source_ip,
                    'public_ip' => (string) $m->public_ip,
                ], JSON_UNESCAPED_UNICODE),
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        DB::table('user_public_ip_snat_maps')
            ->where('vpn_user_id', $vpnUserId)
            ->where('status', 'active')
            ->update([
                'status' => 'released',
                'released_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function deleteIpPool(int $id): JsonResponse
    {
        $ip = IpPool::findOrFail($id);
        $ip->delete();
        return response()->json(null, 204);
    }

    public function batchDeleteIpPool(Request $request): JsonResponse
    {
        $ids = $request->input('ids', []);
        if (! is_array($ids) || empty($ids)) {
            return response()->json(['deleted' => 0], 200);
        }
        $count = IpPool::whereIn('id', $ids)->count();
        IpPool::whereIn('id', $ids)->delete();
        return response()->json(['deleted' => $count], 200);
    }

    /**
     * 分销商（B）终端注册用户数：vpn_users 中 reseller_id 非空，按 LOWER(email)+reseller_id 去重。
     */
    private function countDistinctBResellerVpnUsers(): int
    {
        $row = DB::selectOne(
            'SELECT COUNT(*) AS c FROM (
                SELECT 1
                FROM vpn_users
                WHERE reseller_id IS NOT NULL
                GROUP BY LOWER(TRIM(email)), reseller_id
            ) t'
        );

        return (int) ($row->c ?? 0);
    }

    /**
     * 去重后的「至少有一条 vpn_users 为 active」的 B 终端用户数。
     */
    private function countDistinctBResellerVpnUsersActive(): int
    {
        $row = DB::selectOne(
            'SELECT COUNT(*) AS c FROM (
                SELECT LOWER(TRIM(email)) AS e, reseller_id
                FROM vpn_users
                WHERE reseller_id IS NOT NULL
                GROUP BY LOWER(TRIM(email)), reseller_id
                HAVING SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) > 0
            ) t',
            ['active']
        );

        return (int) ($row->c ?? 0);
    }

    private function maskResellerApiKey(string $apiKey): string
    {
        if (strlen($apiKey) <= 12) {
            return substr($apiKey, 0, 4) . '…';
        }

        return substr($apiKey, 0, 6) . '…' . substr($apiKey, -4);
    }
}
