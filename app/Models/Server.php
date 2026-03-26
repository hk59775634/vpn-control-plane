<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Server extends Model
{
    protected $fillable = [
        'hostname',
        'region',
        'role',
        'cost_cents',
        'protocol',
        'vpn_ip_cidrs',
        'wg_public_key',
        'wg_private_key_enc',
        'wg_port',
        'wg_dns',
        'ocserv_radius_host',
        'ocserv_radius_auth_port',
        'ocserv_radius_acct_port',
        'ocserv_radius_secret',
        'ocserv_port',
        'ocserv_domain',
        'ocserv_tls_cert_pem',
        'ocserv_tls_key_pem',
        'host',
        'ssh_port',
        'ssh_user',
        'ssh_password',
        'notes',
        'agent_token_hash',
        'agent_version',
        'agent_status',
        'last_seen_at',
        'agent_enabled',
        'node_nat_interface',
        'node_bandwidth_interface',
        'nat_topology',
        'cn_public_iface',
        'hk_public_iface',
        'peer_link_iface',
        'peer_link_local_ip',
        'peer_link_remote_ip',
        'link_tunnel_type',
        'peer_link_wg_private_key',
        'peer_link_wg_peer_public_key',
        'peer_link_wg_endpoint',
        'peer_link_wg_allowed_ips',
        'split_nat_server_id',
        'split_nat_host',
        'split_nat_ssh_port',
        'split_nat_ssh_user',
        'split_nat_ssh_password',
        'split_nat_hk_public_iface',
        'split_nat_multi_public_ip_enabled',
        'agent_deploy_status',
        'agent_deploy_message',
        'agent_deploy_at',
        'config_revision_ts',
        'agent_reported_config_ts',
        'online_users',
        'online_sessions',
    ];

    protected $casts = [
        'last_heartbeat_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'agent_deploy_at' => 'datetime',
        'config_revision_ts' => 'integer',
        'agent_reported_config_ts' => 'integer',
        'ssh_port' => 'integer',
        'ssh_password' => 'encrypted',
        'wg_private_key_enc' => 'encrypted',
        'ocserv_radius_auth_port' => 'integer',
        'ocserv_radius_acct_port' => 'integer',
        'ocserv_radius_secret' => 'encrypted',
        'ocserv_port' => 'integer',
        'ocserv_tls_cert_pem' => 'encrypted',
        'ocserv_tls_key_pem' => 'encrypted',
        'peer_link_wg_private_key' => 'encrypted',
        'split_nat_server_id' => 'integer',
        'split_nat_ssh_port' => 'integer',
        'split_nat_ssh_password' => 'encrypted',
        'split_nat_multi_public_ip_enabled' => 'boolean',
        'agent_enabled' => 'boolean',
        'online_users' => 'integer',
        'online_sessions' => 'array',
    ];

    public function exitNodes(): HasMany
    {
        return $this->hasMany(ExitNode::class, 'server_id');
    }
}
