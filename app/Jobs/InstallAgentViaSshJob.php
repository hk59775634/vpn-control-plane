<?php

namespace App\Jobs;

use App\Models\Server;
use App\Support\AgentPackageSource;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;
use Throwable;

class InstallAgentViaSshJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $serverId)
    {
    }

    public function handle(): void
    {
        $server = Server::find($this->serverId);
        if (! $server || ! $server->host || ! $server->ssh_user || ! $server->ssh_password) {
            return;
        }

        $baseUrl = rtrim((string) config('app.url', ''), '/');
        $bootstrapToken = trim((string) config('app.agent_bootstrap_token', ''));
        if ($baseUrl === '' || $bootstrapToken === '') {
            $this->setDeploy($server->id, 'failed', 'A 站未配置 APP_URL 或 AGENT_BOOTSTRAP_TOKEN');

            return;
        }

        try {
            AgentPackageSource::resolvePath();
        } catch (\RuntimeException $e) {
            $this->setDeploy($server->id, 'failed', $e->getMessage());

            return;
        }

        $topo = (string) ($server->nat_topology ?: 'combined');

        $this->setDeploy($server->id, 'running', '正在通过 SSH 连接接入服务器…');

        try {
            $this->installOnNode(
                deployServerId: (int) $server->id,
                host: (string) $server->host,
                port: (int) ($server->ssh_port ?: 22),
                user: (string) $server->ssh_user,
                pass: (string) $server->ssh_password,
                baseUrl: $baseUrl,
                stageLabel: '接入机',
            );

            if ($topo === 'split_access' && $server->split_nat_host && $server->split_nat_ssh_user && $server->split_nat_ssh_password) {
                $natServerId = (int) ($server->split_nat_server_id ?: 0);
                if ($natServerId <= 0) {
                    throw new \RuntimeException('分体拓扑未解析到 NAT 服务器记录（split_nat_server_id）');
                }

                $this->setDeploy($server->id, 'running', '接入机已完成；正在部署 NAT 节点…');
                $this->setDeploy($natServerId, 'running', '正在连接 NAT 服务器并下发 Agent…');
                $this->installOnNode(
                    deployServerId: $natServerId,
                    host: (string) $server->split_nat_host,
                    port: (int) ($server->split_nat_ssh_port ?: 22),
                    user: (string) $server->split_nat_ssh_user,
                    pass: (string) $server->split_nat_ssh_password,
                    baseUrl: $baseUrl,
                    stageLabel: 'NAT 机',
                );
                $this->setDeploy($natServerId, 'success', 'SSH 安装已完成，等待 Agent 首次连接');
            }

            $this->setDeploy($server->id, 'success', 'SSH 安装已完成，等待 Agent 首次注册与心跳');
        } catch (Throwable $e) {
            Log::error('InstallAgentViaSshJob failed', [
                'server_id' => $server->id,
                'message' => $e->getMessage(),
            ]);
            $this->setDeploy($server->id, 'failed', $this->formatError($e));
            if ($topo === 'split_access') {
                $natServerId = (int) ($server->split_nat_server_id ?: 0);
                if ($natServerId > 0) {
                    $cur = DB::table('servers')->where('id', $natServerId)->value('agent_deploy_status');
                    if ($cur !== 'success') {
                        $this->setDeploy($natServerId, 'failed', '安装中断：'.$this->formatError($e));
                    }
                }
            }
            throw $e;
        }
    }

    private function formatError(Throwable $e): string
    {
        $m = trim($e->getMessage());
        // agent_deploy_message 为 text；保留较长 SSH/HTTP 诊断（含响应体预览）
        if (mb_strlen($m) > 12000) {
            $m = mb_substr($m, 0, 12000).'…';
        }

        return $m !== '' ? $m : 'unknown error';
    }

    private function setDeploy(int $serverId, ?string $status, ?string $message): void
    {
        if (! Schema::hasColumn('servers', 'agent_deploy_status')) {
            return;
        }
        DB::table('servers')->where('id', $serverId)->update([
            'agent_deploy_status' => $status,
            'agent_deploy_message' => $message,
            'agent_deploy_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * 与手动安装一致：bash install_agent.sh "<A站域名或根URL>" "<SSH登录IP>"。
     */
    private function installOnNode(
        int $deployServerId,
        string $host,
        int $port,
        string $user,
        string $pass,
        string $baseUrl,
        string $stageLabel
    ): void {
        $target = "{$user}@{$host}";
        $sshOpts = self::openSshClientOptions();
        $base = rtrim($baseUrl, '/');
        $scriptUrl = $base.'/install_agent.sh';

        $this->setDeploy($deployServerId, 'running', $stageLabel.'：SSH 执行 wget install_agent.sh 并完成安装…');

        $remoteInner = sprintf(
            'cd /tmp && rm -f install_agent.sh && ( wget -qO install_agent.sh %1$s || curl -fsSL -o install_agent.sh %1$s ) && bash install_agent.sh %2$s %3$s',
            escapeshellarg($scriptUrl),
            escapeshellarg($base),
            escapeshellarg((string) $host)
        );

        $this->run(array_merge([
            'sshpass', '-p', $pass, 'ssh', '-p', (string) $port,
        ], $sshOpts, [
            $target,
            'bash -lc '.escapeshellarg($remoteInner),
        ]), 900);
    }

    /**
     * 降低「Warning: Permanently added … to the list of known hosts」干扰；失败信息里再剥一遍该行。
     *
     * @return list<string>
     */
    private static function openSshClientOptions(): array
    {
        return [
            '-o', 'StrictHostKeyChecking=no',
            '-o', 'UserKnownHostsFile=/dev/null',
            '-o', 'GlobalKnownHostsFile=/dev/null',
            '-o', 'LogLevel=ERROR',
        ];
    }

    private function run(array $cmd, int $timeoutSeconds): void
    {
        $p = new Process($cmd);
        $p->setTimeout($timeoutSeconds);
        $p->run();
        if (! $p->isSuccessful()) {
            $raw = trim($p->getErrorOutput()."\n".$p->getOutput());
            $err = $this->stripBenignSshStderr($raw);
            throw new \RuntimeException($err !== '' ? $err : 'SSH 命令失败（退出码 '.(string) $p->getExitCode().'）');
        }
    }

    private function stripBenignSshStderr(string $raw): string
    {
        $lines = preg_split('/\r\n|\n|\r/', $raw) ?: [];
        $out = [];
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }
            if (preg_match('/^Warning:\s+Permanently added\b/i', $line)) {
                continue;
            }
            $out[] = $line;
        }

        return trim(implode("\n", $out));
    }
}
