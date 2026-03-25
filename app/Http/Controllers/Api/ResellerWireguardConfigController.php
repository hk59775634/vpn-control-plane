<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\VpnUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class ResellerWireguardConfigController extends Controller
{
    /**
     * GET /api/v1/reseller/wireguard/config?user_email=xxx&a_order_id=123
     * 以当前分销商（API Key）查找 vpn_user，并返回 wg-quick 配置文本。
     * 若传 a_order_id，则精确到该 A 站订单对应的 vpn_user（每已购产品独立配置）；
     * 未传时兼容旧行为：同邮箱下取 id 最大的一条。
     */
    public function config(Request $request): JsonResponse
    {
        $reseller = $request->attributes->get('reseller');
        if (!$reseller) {
            return response()->json(['message' => '未授权'], 401);
        }

        $v = $request->validate([
            'user_email' => 'required|email|max:255',
            'a_order_id' => 'nullable|integer|min:1',
        ]);

        $vpnUser = null;
        if (!empty($v['a_order_id'])) {
            $order = Order::query()
                ->whereKey((int) $v['a_order_id'])
                ->where('reseller_id', $reseller->id)
                ->whereNotNull('vpn_user_id')
                ->first();
            if (!$order) {
                return response()->json(['message' => '未找到该订单或无权访问'], 404);
            }
            $vpnUser = VpnUser::query()
                ->whereKey($order->vpn_user_id)
                ->where('reseller_id', $reseller->id)
                ->first();
            if (!$vpnUser) {
                return response()->json(['message' => '订单关联的 VPN 用户不存在'], 404);
            }
            if (strcasecmp((string) $vpnUser->email, (string) $v['user_email']) !== 0) {
                return response()->json(['message' => '邮箱与订单不匹配'], 422);
            }
            $order->loadMissing('product');
            if ($order->product && ($order->product->enable_wireguard ?? true) === false) {
                return response()->json(['message' => '该产品未包含 WireGuard，无法生成配置'], 404);
            }
        } else {
            $vpnUser = VpnUser::query()
                ->where('email', $v['user_email'])
                ->where('reseller_id', $reseller->id)
                ->orderByDesc('id')
                ->first();
        }
        if (!$vpnUser) {
            return response()->json(['message' => '未找到该用户'], 404);
        }

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
            return response()->json(['message' => '该用户的 WireGuard 密钥由客户端生成，服务器未保存私钥，无法提供下载配置'], 409);
        }
        $clientPriv = Crypt::decryptString((string) $privEnc);

        $alloc = DB::table('vpn_ip_allocations')->where('vpn_user_id', $vpnUser->id)->first();
        $addressIp = $alloc?->ip_address ?: null;
        if (!$addressIp) {
            // fallback: 从 allowed_ips 解析
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

