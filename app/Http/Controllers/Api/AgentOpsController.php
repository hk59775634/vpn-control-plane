<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\EvaluateServerHealthJob;
use App\Jobs\InstallAgentViaSshJob;
use App\Models\Server;
use App\Services\AgentHeartbeatPolicyBuilder;
use App\Services\AgentInstallManifestBuilder;
use App\Support\AgentPackageSource;
use App\Support\AgentPackageTarGz;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AgentOpsController extends Controller
{
    /**
     * 供节点 SSH 安装时 curl/wget 拉取：gzip tar，根目录即 agent 源码（含 run_agent.py）。
     * 鉴权与注册接口一致：X-Agent-Bootstrap-Token 或查询参数 bootstrap_token（便于 wget）。
     */
    public function downloadAgentPackage(Request $request): BinaryFileResponse
    {
        try {
            $serverIdQuery = $request->query('server_id');
            $clientIpNorm = $this->normalizeClientIp((string) $request->ip());
            $authorized = $this->bootstrapTokenOk($request)
                || ($serverIdQuery !== null && $serverIdQuery !== ''
                    && $this->installClientIpMatchesServer($clientIpNorm, (int) $serverIdQuery));
            if (! $authorized) {
                abort(401, 'agent bootstrap token 无效，或请附带 server_id 且当前源地址与服务器 SSH 信息一致');
            }

            try {
                $dir = AgentPackageSource::resolvePath();
            } catch (\RuntimeException $e) {
                Log::warning('agent package: resolve source failed', ['message' => $e->getMessage()]);

                abort(503, $e->getMessage());
            }

            if (! is_readable($dir)) {
                abort(503, 'Agent 源码目录不可读：'.$dir);
            }

            $tmp = tempnam(sys_get_temp_dir(), 'vpn-agent-pkg-');
            if ($tmp === false) {
                abort(503, '无法创建临时文件');
            }
            @unlink($tmp);
            $archive = $tmp.'.tar.gz';

            try {
                AgentPackageTarGz::buildFromDirectory($dir, $archive);
            } catch (\Throwable $e) {
                @unlink($archive);
                Log::error('agent package: build failed', ['dir' => $dir, 'exception' => $e->getMessage()]);

                abort(503, '打包 agent 失败：'.$e->getMessage());
            }

            return response()->download($archive, 'vpn-node-agent.tar.gz', [
                'Content-Type' => 'application/gzip',
            ])->deleteFileAfterSend(true);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('agent package: unexpected error', ['exception' => $e]);

            abort(503, '打包或输出失败：'.$e->getMessage());
        }
    }

    /**
     * 节点本地安装脚本拉取：env、package URL、systemd 片段（与 InstallAgentViaSshJob 同源）。
     * 鉴权：X-Agent-Bootstrap-Token（与 /v1/agent/package 相同）。
     */
    public function installManifest(Request $request, AgentInstallManifestBuilder $builder): JsonResponse
    {
        $this->assertAgentBootstrapToken($request);
        $v = $request->validate([
            'server_id' => 'required|integer|exists:servers,id',
        ]);

        $payload = $builder->build((int) $v['server_id']);

        return response()->json([
            'success' => true,
            'code' => 'OK',
            'message' => 'OK',
            'data' => $payload,
        ]);
    }

    /**
     * 安装前自检：返回 A 站看到的源 IP、归一化后 IP、是否已匹配 server_id（便于对照 curl --interface 与 servers.host）。
     */
    public function installSourceDebug(Request $request): JsonResponse
    {
        $raw = (string) $request->ip();
        $normalized = $this->normalizeClientIp($raw);
        $matched = $this->resolveServerIdByConnectingIp($normalized);

        return response()->json([
            'success' => true,
            'code' => 'OK',
            'message' => 'OK',
            'data' => [
                'client_ip' => $raw,
                'client_ip_normalized' => $normalized,
                'matched_server_id' => $matched,
                'hint' => '脚本第2参数（curl --interface）应使上述 IP 与 A 站「服务器」里 SSH host 一致；若此处为内网 IP，请配置反代 TrustProxies / X-Forwarded-For。',
                'x_forwarded_for' => $request->header('X-Forwarded-For'),
                'x_real_ip' => $request->header('X-Real-IP'),
            ],
        ]);
    }

    /**
     * 按「当前请求的源地址」匹配 servers.host / split_nat_host，返回与 install-manifest 相同结构的 data（无需 token）。
     * 须正确配置反向代理与 TrustProxies，使 request->ip() 为节点访问 A 站时的公网源地址。
     */
    public function installContextBySourceIp(Request $request, AgentInstallManifestBuilder $builder): JsonResponse
    {
        $raw = (string) $request->ip();
        $clientIp = $this->normalizeClientIp($raw);
        $sid = $this->resolveServerIdByConnectingIp($clientIp);
        if ($sid === null) {
            abort(422, '未匹配服务器：A 站感知源 IP 为 '.$clientIp.'（原始 request->ip(): '.$raw.'）。'
                .'请核对 A 站 servers.host / split_nat_host 是否与该 IP 一致；'
                .'脚本第2参数应为本机用于访问 A 的出口地址（与 curl --interface 一致）。'
                .'可先请求 GET /api/v1/agent/install-source-debug 查看详情。');
        }

        $payload = $builder->build($sid);

        return response()->json([
            'success' => true,
            'code' => 'OK',
            'message' => 'OK',
            'data' => $payload,
        ]);
    }

    /**
     * 节点安装脚本上报进度，写入 servers.agent_deploy_*，管理台与队列 SSH 安装共用展示。
     */
    public function reportInstallProgress(Request $request): JsonResponse
    {
        if (! Schema::hasColumn('servers', 'agent_deploy_status')) {
            abort(503, '数据库未迁移 agent_deploy 字段');
        }

        $v = $request->validate([
            'server_id' => 'required|integer|exists:servers,id',
            'deploy_status' => 'required|string|in:queued,running,success,failed',
            'deploy_message' => 'nullable|string|max:12000',
        ]);

        $clientIpNorm = $this->normalizeClientIp((string) $request->ip());
        if (! $this->bootstrapTokenOk($request) && ! $this->installClientIpMatchesServer($clientIpNorm, (int) $v['server_id'])) {
            abort(401, 'agent bootstrap token 无效或源 IP 与 server_id 不匹配');
        }

        DB::table('servers')->where('id', (int) $v['server_id'])->update([
            'agent_deploy_status' => $v['deploy_status'],
            'agent_deploy_message' => (string) ($v['deploy_message'] ?? ''),
            'agent_deploy_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'code' => 'OK',
            'message' => 'OK',
            'data' => ['server_id' => (int) $v['server_id']],
        ]);
    }

    private function bootstrapTokenOk(Request $request): bool
    {
        $expected = trim((string) config('app.agent_bootstrap_token', ''));
        if ($expected === '') {
            return false;
        }
        $provided = trim((string) $request->header('X-Agent-Bootstrap-Token', ''));
        if ($provided === '') {
            $provided = trim((string) $request->query('bootstrap_token', ''));
        }

        return hash_equals($expected, $provided);
    }

    private function assertAgentBootstrapToken(Request $request): void
    {
        if (! $this->bootstrapTokenOk($request)) {
            abort(401, 'agent bootstrap token invalid');
        }
    }

    /**
     * 节点访问 A 站时的源 IP 是否与该 server_id 对应 SSH 信息一致（含分体 NAT：匹配接入机上的 split_nat_host）。
     */
    private function normalizeClientIp(string $ip): string
    {
        $ip = trim($ip);
        if (str_starts_with($ip, '::ffff:')) {
            return substr($ip, 7);
        }

        return $ip;
    }

    private function installClientIpMatchesServer(string $clientIp, int $serverId): bool
    {
        $clientIp = $this->normalizeClientIp($clientIp);
        if ($clientIp === '') {
            return false;
        }
        $server = Server::query()->find($serverId);
        if (! $server) {
            return false;
        }
        if ($this->ipMatchesStoredHost($clientIp, (string) $server->host)) {
            return true;
        }
        $parent = Server::query()->where('split_nat_server_id', $serverId)->first();
        if ($parent && $this->ipMatchesStoredHost($clientIp, (string) $parent->split_nat_host)) {
            return true;
        }

        return false;
    }

    private function resolveServerIdByConnectingIp(string $clientIp): ?int
    {
        $clientIp = $this->normalizeClientIp($clientIp);
        if ($clientIp === '') {
            return null;
        }
        foreach (Server::query()->cursor() as $s) {
            if ($this->ipMatchesStoredHost($clientIp, (string) $s->host)) {
                return (int) $s->id;
            }
        }
        foreach (Server::query()->cursor() as $p) {
            if (! $p->split_nat_server_id) {
                continue;
            }
            if ($this->ipMatchesStoredHost($clientIp, (string) $p->split_nat_host)) {
                return (int) $p->split_nat_server_id;
            }
        }

        return null;
    }

    private function ipMatchesStoredHost(string $ip, string $stored): bool
    {
        $ip = $this->normalizeClientIp($ip);
        $stored = trim($stored);
        if ($stored === '') {
            return false;
        }
        if (filter_var($stored, FILTER_VALIDATE_IP)) {
            return $this->normalizeClientIp($stored) === $ip;
        }
        if ($stored === $ip) {
            return true;
        }
        $resolved = @gethostbyname($stored);
        if ($resolved !== false && $resolved !== $stored && $this->normalizeClientIp($resolved) === $ip) {
            return true;
        }

        return false;
    }

    public function register(Request $request): JsonResponse
    {
        $bootstrapToken = trim((string) config('app.agent_bootstrap_token', ''));
        $providedToken = trim((string) $request->header('X-Agent-Bootstrap-Token', ''));
        if ($bootstrapToken === '' || ! hash_equals($bootstrapToken, $providedToken)) {
            abort(401, 'agent bootstrap token invalid');
        }

        $v = $request->validate([
            'server_id' => 'required|integer|exists:servers,id',
            'hostname' => 'nullable|string|max:255',
            'region' => 'nullable|string|max:64',
            'role' => 'nullable|string|max:64',
            'agent_version' => 'nullable|string|max:64',
            'config_revision_ts' => 'nullable|integer|min:0',
        ]);

        $server = Server::findOrFail((int) $v['server_id']);
        $plainToken = Str::random(64);
        $fill = [
            'agent_token_hash' => hash('sha256', $plainToken),
            'agent_version' => $v['agent_version'] ?? $server->agent_version,
            'agent_status' => 'online',
            'last_seen_at' => now(),
            'last_heartbeat_at' => now(),
            'hostname' => $v['hostname'] ?? $server->hostname,
            'region' => $v['region'] ?? $server->region,
            'role' => $v['role'] ?? $server->role,
        ];
        if (Schema::hasColumn('servers', 'agent_reported_config_ts') && array_key_exists('config_revision_ts', $v) && $v['config_revision_ts'] !== null) {
            $fill['agent_reported_config_ts'] = max(0, (int) $v['config_revision_ts']);
        }
        $server->forceFill($fill)->save();
        $this->clearAgentDeployProgress($server->id);

        return response()->json([
            'server_id' => $server->id,
            'agent_token' => $plainToken,
            'heartbeat_interval_sec' => 15,
        ]);
    }

    public function heartbeat(Request $request, AgentHeartbeatPolicyBuilder $policyBuilder): JsonResponse
    {
        $server = $this->resolveServerFromAgentToken($request);
        $v = $request->validate([
            'agent_version' => 'nullable|string|max:64',
            'metrics' => 'nullable|array',
            'metrics.cpu_percent' => 'nullable|numeric',
            'metrics.mem_percent' => 'nullable|numeric',
            'metrics.load_1' => 'nullable|numeric',
            'meta' => 'nullable|array',
            'meta.config_revision_ts' => 'nullable|integer|min:0',
            'pull_limit' => 'nullable|integer|min:1|max:100',
            'wg_peer_transfer' => 'nullable|array|max:2000',
            'wg_peer_transfer.*.public_key' => 'required|string|max:255',
            'wg_peer_transfer.*.rx_bytes' => 'required|integer|min:0',
            'wg_peer_transfer.*.tx_bytes' => 'required|integer|min:0',
        ]);

        $this->ingestWireguardPeerTransfer($server, $v['wg_peer_transfer'] ?? null);

        $metrics = $v['metrics'] ?? [];
        $meta = $v['meta'] ?? [];
        $fill = [
            'agent_version' => $v['agent_version'] ?? $server->agent_version,
            'cpu_percent' => array_key_exists('cpu_percent', $metrics) ? (float) $metrics['cpu_percent'] : $server->cpu_percent,
            'mem_percent' => array_key_exists('mem_percent', $metrics) ? (float) $metrics['mem_percent'] : $server->mem_percent,
            'load_1' => array_key_exists('load_1', $metrics) ? (float) $metrics['load_1'] : $server->load_1,
            'last_seen_at' => now(),
            'last_heartbeat_at' => now(),
            'agent_status' => 'online',
        ];
        if (Schema::hasColumn('servers', 'agent_reported_config_ts') && array_key_exists('config_revision_ts', $meta)) {
            $fill['agent_reported_config_ts'] = max(0, (int) $meta['config_revision_ts']);
        }
        $server->forceFill($fill)->save();
        $this->clearAgentDeployProgress($server->id);

        DB::table('agent_heartbeats')->insert([
            'server_id' => $server->id,
            'agent_version' => $v['agent_version'] ?? null,
            'cpu_percent' => $metrics['cpu_percent'] ?? null,
            'mem_percent' => $metrics['mem_percent'] ?? null,
            'load_1' => $metrics['load_1'] ?? null,
            'meta' => isset($v['meta']) ? json_encode($v['meta'], JSON_UNESCAPED_UNICODE) : null,
            'created_at' => now(),
        ]);

        EvaluateServerHealthJob::dispatch($server->id)->onQueue('maintenance');

        $pullLimit = (int) ($v['pull_limit'] ?? 20);
        $commands = DB::table('agent_commands')
            ->where('server_id', $server->id)
            ->where('status', 'pending')
            ->orderBy('id')
            ->limit($pullLimit)
            ->get(['id', 'type', 'payload', 'created_at']);

        if ($commands->isNotEmpty()) {
            DB::table('agent_commands')
                ->whereIn('id', $commands->pluck('id')->all())
                ->update([
                    'status' => 'dispatched',
                    'dispatched_at' => now(),
                    'updated_at' => now(),
                ]);
        }

        $policy = $policyBuilder->build($server);

        return response()->json([
            'server_id' => $server->id,
            'commands' => $commands->map(function ($c) {
                return [
                    'id' => (int) $c->id,
                    'type' => (string) $c->type,
                    'payload' => $c->payload ? json_decode((string) $c->payload, true) : null,
                    'created_at' => $c->created_at,
                ];
            })->values(),
            'policy' => $policy,
        ]);
    }

    /**
     * @param  list<array{public_key: string, rx_bytes: int, tx_bytes: int}>|null  $rows
     */
    private function ingestWireguardPeerTransfer(Server $server, ?array $rows): void
    {
        if ($rows === null || $rows === [] || ! Schema::hasTable('agent_peer_transfer_state')) {
            return;
        }
        if (! Schema::hasTable('wireguard_peers') || ! Schema::hasTable('traffic_logs')) {
            return;
        }

        $sid = (int) $server->id;
        $now = now();

        foreach ($rows as $item) {
            $pk = trim((string) ($item['public_key'] ?? ''));
            if ($pk === '') {
                continue;
            }
            $peer = DB::table('wireguard_peers')
                ->where('server_id', $sid)
                ->where('public_key', $pk)
                ->first(['vpn_user_id']);
            if ($peer === null) {
                continue;
            }
            $uid = (int) $peer->vpn_user_id;
            $rx = (int) $item['rx_bytes'];
            $tx = (int) $item['tx_bytes'];

            $st = DB::table('agent_peer_transfer_state')
                ->where('server_id', $sid)
                ->where('public_key', $pk)
                ->first(['last_rx', 'last_tx']);

            $lastRx = $st ? (int) $st->last_rx : 0;
            $lastTx = $st ? (int) $st->last_tx : 0;

            if ($rx < $lastRx || $tx < $lastTx) {
                $dUp = max(0, $rx);
                $dDown = max(0, $tx);
            } else {
                $dUp = max(0, $rx - $lastRx);
                $dDown = max(0, $tx - $lastTx);
            }

            if ($dUp + $dDown > 0) {
                DB::table('traffic_logs')->insert([
                    'vpn_user_id' => $uid,
                    'server_id' => $sid,
                    'bytes_up' => $dUp,
                    'bytes_down' => $dDown,
                    'recorded_at' => $now,
                ]);
            }

            $exists = DB::table('agent_peer_transfer_state')
                ->where('server_id', $sid)
                ->where('public_key', $pk)
                ->exists();

            if ($exists) {
                DB::table('agent_peer_transfer_state')
                    ->where('server_id', $sid)
                    ->where('public_key', $pk)
                    ->update([
                        'last_rx' => $rx,
                        'last_tx' => $tx,
                        'updated_at' => $now,
                    ]);
            } else {
                DB::table('agent_peer_transfer_state')->insert([
                    'server_id' => $sid,
                    'public_key' => $pk,
                    'last_rx' => $rx,
                    'last_tx' => $tx,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function ack(Request $request): JsonResponse
    {
        $server = $this->resolveServerFromAgentToken($request);
        $v = $request->validate([
            'results' => 'required|array|min:1|max:200',
            'results.*.command_id' => 'required|integer',
            'results.*.ok' => 'required|boolean',
            'results.*.message' => 'nullable|string|max:5000',
            'results.*.meta' => 'nullable|array',
        ]);

        $updated = 0;
        foreach ($v['results'] as $r) {
            $count = DB::table('agent_commands')
                ->where('id', (int) $r['command_id'])
                ->where('server_id', $server->id)
                ->whereIn('status', ['dispatched', 'pending'])
                ->update([
                    'status' => $r['ok'] ? 'success' : 'failed',
                    'acked_at' => now(),
                    'result_message' => $r['message'] ?? null,
                    'result_meta' => isset($r['meta']) ? json_encode($r['meta'], JSON_UNESCAPED_UNICODE) : null,
                    'updated_at' => now(),
                ]);
            $updated += $count;
        }

        return response()->json(['updated' => $updated]);
    }

    public function enqueueCommand(Request $request, int $serverId): JsonResponse
    {
        Server::findOrFail($serverId);
        $v = $request->validate([
            'type' => 'required|string|max:64',
            'payload' => 'nullable|array',
        ]);

        $id = DB::table('agent_commands')->insertGetId([
            'server_id' => $serverId,
            'type' => $v['type'],
            'payload' => isset($v['payload']) ? json_encode($v['payload'], JSON_UNESCAPED_UNICODE) : null,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['id' => $id], 201);
    }

    public function listCommands(Request $request, int $serverId): JsonResponse
    {
        Server::findOrFail($serverId);
        $limit = min(max((int) $request->input('limit', 100), 1), 500);
        $rows = DB::table('agent_commands')
            ->where('server_id', $serverId)
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return response()->json($rows->map(function ($r) {
            $r->payload = $r->payload ? json_decode((string) $r->payload, true) : null;
            $r->result_meta = $r->result_meta ? json_decode((string) $r->result_meta, true) : null;
            return $r;
        })->values());
    }

    public function installAgent(int $serverId): JsonResponse
    {
        $server = Server::findOrFail($serverId);
        if (!$server->host || !$server->ssh_user || !$server->ssh_password) {
            abort(422, '服务器缺少 host/ssh_user/ssh_password，无法自动安装 agent');
        }

        if (Schema::hasColumn('servers', 'agent_deploy_status')) {
            DB::table('servers')->where('id', $server->id)->update([
                'agent_deploy_status' => 'queued',
                'agent_deploy_message' => '已加入 maintenance 队列，等待队列 Worker 执行',
                'agent_deploy_at' => now(),
                'updated_at' => now(),
            ]);
        }

        InstallAgentViaSshJob::dispatch($server->id)->onQueue('maintenance');
        return response()->json(['queued' => true]);
    }

    /** SSH 部署结束后由心跳/注册接管；清除「部署进度」展示。 */
    private function clearAgentDeployProgress(int $serverId): void
    {
        if (! Schema::hasColumn('servers', 'agent_deploy_status')) {
            return;
        }
        DB::table('servers')->where('id', $serverId)->update([
            'agent_deploy_status' => null,
            'agent_deploy_message' => null,
            'agent_deploy_at' => null,
            'updated_at' => now(),
        ]);
    }

    private function resolveServerFromAgentToken(Request $request): Server
    {
        $token = (string) $request->bearerToken();
        if ($token === '') {
            abort(401, 'agent token required');
        }

        $hash = hash('sha256', $token);
        $server = Server::query()->where('agent_token_hash', $hash)->first();
        if (!$server) {
            abort(401, 'agent token invalid');
        }

        return $server;
    }
}

