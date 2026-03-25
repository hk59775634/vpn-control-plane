<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VpnUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * 管理端拉取指定 VPN 用户的 WireGuard 配置（与分销商接口生成逻辑一致，不按 reseller 过滤）。
 */
class AdminWireguardController extends Controller
{
    public function show(int $id): JsonResponse
    {
        $vpnUser = VpnUser::findOrFail($id);

        $peer = DB::table('wireguard_peers')->where('vpn_user_id', $vpnUser->id)->first();
        if (!$peer) {
            return response()->json(['message' => '该用户尚未创建 WireGuard Peer'], 404);
        }

        $server = DB::table('servers')->where('id', (int) $peer->server_id)->first();
        if (!$server) {
            return response()->json(['message' => '未找到 WireGuard 服务器'], 404);
        }

        $serverPub = (string) ($server->wg_public_key ?? '');
        if (!$serverPub) {
            return response()->json(['message' => 'WireGuard 服务器未配置公钥'], 409);
        }
        $port = (int) ($server->wg_port ?? 51820);
        $dns = trim((string) ($server->wg_dns ?? ''));

        $privEnc = $peer->private_key_enc ?? null;
        if (!$privEnc) {
            return response()->json(['message' => '该用户的 WireGuard 密钥由客户端生成，服务器未保存私钥，无法提供完整配置'], 409);
        }
        $clientPriv = Crypt::decryptString((string) $privEnc);

        $alloc = DB::table('vpn_ip_allocations')->where('vpn_user_id', $vpnUser->id)->first();
        $addressIp = $alloc?->ip_address ?: null;
        if (!$addressIp) {
            $allowed = (string) ($peer->allowed_ips ?? '');
            $addressIp = explode('/', $allowed)[0] ?: null;
        }
        if (!$addressIp) {
            return response()->json(['message' => '未分配 VPN 内网 IP，无法生成配置'], 409);
        }

        $endpointHost = (string) ($peer->endpoint ?? '');
        if (!$endpointHost) {
            return response()->json(['message' => 'Peer endpoint 为空'], 409);
        }

        $lines = [];
        $lines[] = '[Interface]';
        $lines[] = 'PrivateKey = ' . $clientPriv;
        $lines[] = 'Address = ' . $addressIp . '/32';
        if ($dns !== '') {
            $lines[] = 'DNS = ' . $dns;
        }
        $lines[] = '';
        $lines[] = '[Peer]';
        $lines[] = 'PublicKey = ' . $serverPub;
        $lines[] = 'Endpoint = ' . $endpointHost . ':' . $port;
        $lines[] = 'AllowedIPs = 0.0.0.0/0, ::/0';
        $lines[] = 'PersistentKeepalive = 25';
        $lines[] = '';

        return response()->json([
            'vpn_user_id' => $vpnUser->id,
            'region' => $vpnUser->region,
            'config' => implode("\n", $lines),
        ]);
    }
}
