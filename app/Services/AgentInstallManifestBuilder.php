<?php

namespace App\Services;

use App\Models\Server;

/**
 * 生成节点 Agent 安装所需 env 与清单（SSH 下发与「节点自举脚本」共用，避免分叉）。
 */
final class AgentInstallManifestBuilder
{
    public function build(int $serverId): array
    {
        $baseUrl = rtrim((string) config('app.url', ''), '/');
        $server = Server::query()->findOrFail($serverId);

        $parentAccess = Server::query()
            ->where('split_nat_server_id', $server->id)
            ->first();

        if ($parentAccess && (string) ($parentAccess->nat_topology ?: '') === 'split_access') {
            $env = $this->buildEnvForNatNode($parentAccess, (int) $server->id);
            $label = 'NAT 机';
        } else {
            $env = $this->buildEnvForAccessNode($server);
            $label = '接入机';
        }

        return [
            'server_id' => $serverId,
            'stage_label' => $label,
            'package_url' => $baseUrl.'/api/v1/agent/package',
            'env' => $env,
            'remote_dir' => '/opt/vpn1-agent',
            'systemd_unit' => self::systemdUnitContent(),
        ];
    }

    public static function systemdUnitContent(): string
    {
        return <<<'UNIT'
[Unit]
Description=VPN Node Agent
After=network-online.target
Wants=network-online.target

[Service]
Type=simple
WorkingDirectory=/opt/vpn1-agent
EnvironmentFile=/etc/vpn-node/agent.env
ExecStart=/usr/bin/python3 /opt/vpn1-agent/run_agent.py
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
UNIT;
    }

    private function buildEnvForAccessNode(Server $server): string
    {
        $baseUrl = rtrim((string) config('app.url', ''), '/');
        $bootstrapToken = trim((string) config('app.agent_bootstrap_token', ''));
        $topo = (string) ($server->nat_topology ?: 'combined');
        $accessNatIface = $topo === 'split_access'
            ? (string) ($server->peer_link_iface ?: 'eth0')
            : (string) ($server->hk_public_iface ?: 'eth0');
        $accessBwIface = $accessNatIface;
        $rev = (int) ($server->config_revision_ts ?? 0);

        return $this->compileEnv(
            baseUrl: $baseUrl,
            bootstrapToken: $bootstrapToken,
            agentVersion: $this->normalizedAgentVersion(),
            configRevisionTs: $rev,
            serverId: (int) $server->id,
            hostname: (string) $server->hostname,
            region: (string) $server->region,
            role: (string) $server->role,
            natIface: $accessNatIface,
            bwIface: $accessBwIface,
            linkTunnelType: (string) ($server->link_tunnel_type ?: ''),
            linkIface: (string) ($server->peer_link_iface ?: ''),
            linkLocalIp: (string) ($server->peer_link_local_ip ?: ''),
            linkRemoteIp: (string) ($server->peer_link_remote_ip ?: ''),
            linkWgPrivateKey: (string) ($server->peer_link_wg_private_key ?: ''),
            linkWgPeerPublicKey: (string) ($server->peer_link_wg_peer_public_key ?: ''),
            linkWgEndpoint: (string) ($server->peer_link_wg_endpoint ?: ''),
            linkWgAllowedIps: (string) ($server->peer_link_wg_allowed_ips ?: ''),
            server: $server,
        );
    }

    private function buildEnvForNatNode(Server $accessParent, int $natServerId): string
    {
        $baseUrl = rtrim((string) config('app.url', ''), '/');
        $bootstrapToken = trim((string) config('app.agent_bootstrap_token', ''));
        $natRow = Server::query()->findOrFail($natServerId);
        $rev = (int) ($natRow->config_revision_ts ?? 0);

        return $this->compileEnv(
            baseUrl: $baseUrl,
            bootstrapToken: $bootstrapToken,
            agentVersion: $this->normalizedAgentVersion(),
            configRevisionTs: $rev,
            serverId: $natServerId,
            hostname: (string) ($accessParent->hostname.'-nat'),
            region: (string) $accessParent->region,
            role: 'split_nat',
            natIface: (string) ($accessParent->split_nat_hk_public_iface ?: 'eth0'),
            bwIface: (string) ($accessParent->split_nat_hk_public_iface ?: 'eth0'),
            server: $natRow,
        );
    }

    private function normalizedAgentVersion(): string
    {
        $raw = (string) config('app.agent_package_version', '2026032501');
        $v = preg_replace('/\D/', '', $raw) ?? '';

        return strlen($v) === 10 ? $v : '2026032501';
    }

    private function compileEnv(
        string $baseUrl,
        string $bootstrapToken,
        string $agentVersion,
        int $configRevisionTs,
        int $serverId,
        string $hostname,
        string $region,
        string $role,
        string $natIface,
        string $bwIface,
        ?string $linkTunnelType = null,
        ?string $linkIface = null,
        ?string $linkLocalIp = null,
        ?string $linkRemoteIp = null,
        ?string $linkWgPrivateKey = null,
        ?string $linkWgPeerPublicKey = null,
        ?string $linkWgEndpoint = null,
        ?string $linkWgAllowedIps = null,
        ?Server $server = null,
    ): string {
        $natTopo = 'combined';
        if ($server !== null) {
            $natTopo = (string) ($server->nat_topology ?: 'combined');
        }
        $lines = [
            'CONTROL_PLANE_HTTP_BASE='.$baseUrl,
            'NODE_SERVER_ID='.$serverId,
            'NODE_HOSTNAME='.$hostname,
            'NODE_REGION='.$region,
            'NODE_ROLE='.$role,
            'NODE_NAT_INTERFACE='.$natIface,
            'NODE_BANDWIDTH_INTERFACE='.$bwIface,
            'NODE_NAT_TOPOLOGY='.$this->oneLine($natTopo),
            'NODE_WG_INTERFACE=wg0',
            'NODE_LINK_TUNNEL_TYPE='.(string) ($linkTunnelType ?? ''),
            'NODE_LINK_IFACE='.(string) ($linkIface ?? ''),
            'NODE_LINK_LOCAL_IP='.(string) ($linkLocalIp ?? ''),
            'NODE_LINK_REMOTE_IP='.(string) ($linkRemoteIp ?? ''),
            'NODE_LINK_WG_PRIVATE_KEY='.(string) ($linkWgPrivateKey ?? ''),
            'NODE_LINK_WG_PEER_PUBLIC_KEY='.(string) ($linkWgPeerPublicKey ?? ''),
            'NODE_LINK_WG_ENDPOINT='.(string) ($linkWgEndpoint ?? ''),
            'NODE_LINK_WG_ALLOWED_IPS='.(string) ($linkWgAllowedIps ?? ''),
            'AGENT_BOOTSTRAP_TOKEN='.$bootstrapToken,
            'AGENT_VERSION='.$agentVersion,
            'CONFIG_REVISION_TS='.(string) max(0, $configRevisionTs),
            'HEARTBEAT_INTERVAL_SEC=15',
            'AGENT_TOKEN_FILE=/etc/vpn-node/agent.token',
            'SKIP_SYSTEM_COMMANDS=0',
        ];
        if ($server !== null) {
            foreach ($this->protocolEnvLines($server) as $row) {
                $lines[] = $row;
            }
        }
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * 节点协议与 OCServ 参数（供 Agent 自动安装 ocserv + radcli 并写配置）。
     * 敏感字段用 base64，避免 PEM/secret 破坏 env 单行格式。
     *
     * @return list<string>
     */
    private function protocolEnvLines(Server $server): array
    {
        $proto = (string) ($server->protocol ?? '');
        $lines = [
            'NODE_PROTOCOL='.$proto,
            'NODE_VPN_IP_CIDRS='.$this->oneLine((string) ($server->vpn_ip_cidrs ?? '')),
            'NODE_WG_DNS='.$this->oneLine((string) ($server->wg_dns ?: '1.1.1.1')),
        ];
        if ($proto !== 'ocserv') {
            return $lines;
        }
        $cert = (string) ($server->ocserv_tls_cert_pem ?? '');
        $key = (string) ($server->ocserv_tls_key_pem ?? '');
        $secret = (string) ($server->ocserv_radius_secret ?? '');

        return array_merge($lines, [
            'NODE_OCCTL_SOCKET=/var/run/occtl.socket',
            'NODE_OCSERV_RADIUS_HOST='.$this->oneLine((string) ($server->ocserv_radius_host ?? '')),
            'NODE_OCSERV_RADIUS_AUTH_PORT='.(string) ((int) ($server->ocserv_radius_auth_port ?: 1812)),
            'NODE_OCSERV_RADIUS_ACCT_PORT='.(string) ((int) ($server->ocserv_radius_acct_port ?: 1813)),
            'NODE_OCSERV_RADIUS_SECRET_B64='.base64_encode($secret),
            'NODE_OCSERV_PORT='.(string) ((int) ($server->ocserv_port ?: 443)),
            'NODE_OCSERV_DOMAIN='.$this->oneLine((string) ($server->ocserv_domain ?? '')),
            'NODE_OCSERV_TLS_CERT_B64='.base64_encode($cert),
            'NODE_OCSERV_TLS_KEY_B64='.base64_encode($key),
        ]);
    }

    private function oneLine(string $value): string
    {
        return str_replace(["\r", "\n"], [' ', ' '], trim($value));
    }
}
