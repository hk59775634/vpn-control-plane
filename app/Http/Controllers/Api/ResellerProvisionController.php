<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\Reseller;
use App\Models\ResellerBalanceTransaction;
use App\Models\ResellerIncomeRecord;
use App\Models\IpPool;
use App\Models\ProvisionResourceAuditLog;
use App\Models\VpnUser;
use Carbon\Carbon;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * 分销商开通接口（Bearer API Key 鉴权，供 B 站在用户支付成功后调用）
 * 按 vpn_users 创建订单，并根据产品开通 FreeRADIUS / WireGuard 认证。
 */
class ResellerProvisionController extends Controller
{
    private function reseller(Request $request)
    {
        $reseller = $request->attributes->get('reseller');
        if (!$reseller) {
            abort(401, '未登录');
        }
        return $reseller;
    }

    /**
     * POST /api/v1/reseller/orders
     *
     * 请求体: external_order_id, user_email, user_name (optional), product_id, duration_days (optional)
     * 行为: 新购为每条订阅创建独立 vpn_user（同邮箱可多条，每产品独立 WireGuard）；
     * 续费沿用目标订单上的 vpn_user。并触发 RADIUS/WireGuard 开通。
     */
    public function create(Request $request): JsonResponse
    {
        $reseller = $this->reseller($request);

        $data = $request->validate([
            'external_order_id' => 'required|string|max:64',
            'user_email' => 'required|email|max:255',
            'user_name' => 'nullable|string|max:255',
            'product_id' => 'required|integer|exists:products,id',
            'duration_days' => 'nullable|integer|min:1',
            'region' => 'nullable|string|max:64',
            'wireguard_public_key' => 'nullable|string|max:191',
            'target_a_order_id' => 'nullable|integer|exists:orders,id',
            /** 产品含 SSL VPN（enable_radius）时新购必填：完整登录名为「用户填写部分@分销商ID」（写入 FreeRADIUS） */
            'sslvpn_username' => 'nullable|string|max:64',
            'sslvpn_password' => 'nullable|string|min:8|max:128',
        ]);

        $isRenew = !empty($data['target_a_order_id']);

        $product = Product::findOrFail($data['product_id']);
        $needSsl = (bool) ($product->enable_radius ?? true) && !$isRenew;

        if ($needSsl) {
            $data = array_merge($data, $request->validate([
                'sslvpn_username' => 'required|string|max:64|regex:/^[a-zA-Z0-9._-]+$/',
                'sslvpn_password' => 'required|string|min:8|max:128',
            ]));
        }

        $displayName = $data['user_name'] ?? $data['user_email'];

        // 续费幂等：同一 B 业务单号（external_order_id）最多扣一次余额/最多触发一次开通
        // 场景：B 站回调重试、前一次已成功但客户端/网络未收到响应。
        if ($isRenew) {
            $existingIncome = ResellerIncomeRecord::query()
                ->where('biz_order_no', $data['external_order_id'])
                ->where('reseller_id', $reseller->id)
                ->where('kind', 'renew')
                ->first();

            if ($existingIncome) {
                $existingOrder = Order::query()
                    ->with('vpnUser')
                    ->whereKey($existingIncome->a_order_id)
                    ->where('reseller_id', $reseller->id)
                    ->first();

                if (!$existingOrder || !$existingOrder->vpnUser) {
                    return response()->json(['message' => '幂等命中但订单/用户缺失，请联系管理员'], 500);
                }

                return $this->provisionJsonResponse($existingOrder->vpnUser, $existingOrder, 200);
            }
        }

        // 新购幂等：同一分销商 + 同一业务订单号只创建一次，重复请求直接返回已有订单（高并发/重试安全）
        if (empty($data['target_a_order_id'])) {
            $existingOrder = Order::query()
                ->where('biz_order_no', $data['external_order_id'])
                ->where('reseller_id', $reseller->id)
                ->with('vpnUser')
                ->first();
            if ($existingOrder) {
                $vpnUserExisting = $existingOrder->vpnUser;
                if (!$vpnUserExisting) {
                    return response()->json(['message' => '订单关联用户缺失'], 500);
                }
                // 与 B 站业务单号对齐：幂等重试时也保证收入流水表有记录（firstOrCreate 不重复插入）
                $this->recordResellerIncome(
                    $reseller,
                    $vpnUserExisting,
                    $existingOrder,
                    $data['external_order_id'],
                    'purchase'
                );

                return $this->provisionJsonResponse($vpnUserExisting, $existingOrder, 200);
            }
        }

        $vpnUser = null;
        $order = null;

        if ($isRenew) {
            $order = Order::query()
                ->whereKey((int) $data['target_a_order_id'])
                ->where('reseller_id', $reseller->id)
                ->with('vpnUser')
                ->first();
            if (!$order) {
                return response()->json(['message' => 'target_a_order_id 无效或不属于当前分销商'], 422);
            }
            $vpnUser = $order->vpnUser;
            if (!$vpnUser) {
                return response()->json(['message' => '订单未关联 VPN 用户'], 500);
            }
            if (strcasecmp((string) $vpnUser->email, (string) $data['user_email']) !== 0) {
                return response()->json(['message' => '邮箱与续费目标订单不匹配'], 422);
            }
        } else {
            $vpnUser = VpnUser::create([
                'email' => $data['user_email'],
                'reseller_id' => $reseller->id,
                'name' => $displayName,
                'status' => 'active',
                'region' => $data['region'] ?? null,
            ]);
        }

        // 购买时以请求 region 为准（用户后续可切换线路）
        if (!empty($data['region']) && $vpnUser->region !== $data['region']) {
            $vpnUser->region = $data['region'];
            $vpnUser->save();
        }

        $days = $data['duration_days'] ?? $product->duration_days;

        // 续费规则：以目标订单到期时间为主进行顺延，确保“必定延长”。
        // base = max(目标订单当前到期, now)
        // new_expires_at = base + days
        $now = Carbon::now();
        $baseTime = $now->copy();
        if ($order && $order->expires_at instanceof Carbon && $order->expires_at->gt($baseTime)) {
            $baseTime = $order->expires_at->copy();
        } elseif ($order && $order->expires_at) {
            $orderExp = Carbon::parse($order->expires_at);
            if ($orderExp->gt($baseTime)) {
                $baseTime = $orderExp;
            }
        }
        $expiresAt = $baseTime->copy()->addDays((int) $days);

        $costCents = $this->computeProvisionCostCents($product, (int) $days, $isRenew);

        DB::transaction(function () use (
            &$order,
            $reseller,
            $costCents,
            $isRenew,
            $data,
            $product,
            $vpnUser,
            $expiresAt
        ): void {
            $locked = Reseller::whereKey($reseller->id)->lockForUpdate()->first();
            if (!$locked) {
                throw new HttpResponseException(response()->json(['message' => '分销商不存在'], 404));
            }
            if ($locked->balance_enforced && $costCents > 0) {
                if ((int) $locked->balance_cents < $costCents) {
                    throw new HttpResponseException(response()->json([
                        'message' => '余额不足',
                        'required_cents' => $costCents,
                        'balance_cents' => (int) $locked->balance_cents,
                    ], 402));
                }
                $locked->balance_cents = (int) $locked->balance_cents - $costCents;
                $locked->save();

                ResellerBalanceTransaction::create([
                    'reseller_id' => $locked->id,
                    'amount_cents' => -$costCents,
                    'balance_after_cents' => (int) $locked->balance_cents,
                    'type' => $isRenew ? 'provision_renew' : 'provision_purchase',
                    'meta' => [
                        'external_order_id' => $data['external_order_id'],
                        'product_id' => $product->id,
                        'days' => (int) ($data['duration_days'] ?? $product->duration_days),
                    ],
                ]);
            }

            if ($order) {
                $order->update([
                    'status' => 'active',
                    'product_id' => $product->id,
                    'vpn_user_id' => $vpnUser->id,
                    'reseller_id' => $reseller->id,
                    'activated_at' => $order->activated_at ?? now(),
                    'last_renewed_at' => now(),
                    'expires_at' => $expiresAt,
                ]);
            } else {
                $order = Order::create([
                    'vpn_user_id' => $vpnUser->id,
                    'user_id' => null,
                    'product_id' => $product->id,
                    'reseller_id' => $reseller->id,
                    'biz_order_no' => $data['external_order_id'],
                    'status' => 'active',
                    'activated_at' => now(),
                    'last_renewed_at' => null,
                    'expires_at' => $expiresAt,
                ]);
            }
        });

        $vpnUser->update(['status' => 'active']);

        $this->provisionRadiusAndWireGuard(
            $vpnUser,
            $product,
            $order,
            $expiresAt,
            region: $data['region'] ?? null,
            wireguardPublicKey: $data['wireguard_public_key'] ?? null,
            sslvpnUsername: $data['sslvpn_username'] ?? null,
            sslvpnPassword: $data['sslvpn_password'] ?? null
        );

        $this->recordResellerIncome(
            $reseller,
            $vpnUser,
            $order,
            $data['external_order_id'],
            empty($data['target_a_order_id']) ? 'purchase' : 'renew'
        );

        return $this->provisionJsonResponse($vpnUser, $order, 201);
    }

    /**
     * B 站每笔 biz 订单号对应一条收入记录（与 A 站订阅订单 orders 分离）
     */
    /**
     * 新购：按产品整价；续费：按天数占周期比例计价（四舍五入）
     */
    private function computeProvisionCostCents(Product $product, int $days, bool $isRenew): int
    {
        $price = (int) ($product->price_cents ?? 0);
        $duration = max(1, (int) ($product->duration_days ?? 30));
        if ($isRenew) {
            return (int) max(0, (int) round($price * $days / $duration));
        }

        return (int) max(0, $price);
    }

    private function recordResellerIncome(
        Reseller $reseller,
        VpnUser $vpnUser,
        Order $order,
        string $bizOrderNo,
        string $kind
    ): void {
        ResellerIncomeRecord::firstOrCreate(
            ['biz_order_no' => $bizOrderNo],
            [
                'reseller_id' => $reseller->id,
                'vpn_user_id' => $vpnUser->id,
                'a_order_id' => $order->id,
                'kind' => $kind,
            ]
        );
    }

    private function provisionJsonResponse(VpnUser $vpnUser, Order $order, int $status): JsonResponse
    {
        $vpnUser->refresh();

        return response()->json([
            'vpn_user' => [
                'id' => $vpnUser->id,
                'email' => $vpnUser->email,
                'name' => $vpnUser->name,
                'status' => $vpnUser->status,
                'radius_username' => $vpnUser->radius_username,
            ],
            'order' => [
                'id' => $order->id,
                'biz_order_no' => $order->biz_order_no,
                'product_id' => $order->product_id,
                'status' => $order->status,
                'activated_at' => $order->activated_at?->format('c'),
                'last_renewed_at' => $order->last_renewed_at?->format('c'),
                'expires_at' => $order->expires_at?->format('c'),
            ],
        ], $status);
    }

    /**
     * 根据购买的产品为 vpn_user 创建 FreeRADIUS 认证与 WireGuard 认证。
     * 具体实现可对接现有 RADIUS 表与 wireguard_peers 表或外部服务。
     */
    private function provisionRadiusAndWireGuard(
        VpnUser $vpnUser,
        Product $product,
        Order $order,
        Carbon $expiresAt,
        ?string $region = null,
        ?string $wireguardPublicKey = null,
        ?string $sslvpnUsername = null,
        ?string $sslvpnPassword = null
    ): void
    {
        $enableRadius = (bool) ($product->enable_radius ?? true);
        $enableWireguard = (bool) ($product->enable_wireguard ?? true);
        $requiresDedicatedPublicIp = (bool) ($product->requires_dedicated_public_ip ?? false);

        $auditCtx = [
            'vpn_user_id' => (int) $vpnUser->id,
            'order_id' => (int) $order->id,
            'product_id' => (int) $product->id,
            'reseller_id' => (int) $vpnUser->reseller_id,
        ];

        /** SSL VPN（FreeRADIUS）：产品启用时若 B 站传入 sslvpn_*，始终以此为准（覆盖 observer 可能写入的占位名） */
        if ($enableRadius
            && $sslvpnUsername !== null && $sslvpnUsername !== ''
            && $sslvpnPassword !== null && $sslvpnPassword !== '') {
            $suffix = (string) (int) $vpnUser->reseller_id;
            $sanitized = preg_replace('/[^a-zA-Z0-9._-]/', '', $sslvpnUsername);
            if ($sanitized === '') {
                $sanitized = 'u' . $vpnUser->id;
            }
            $vpnUser->update([
                'radius_username' => $sanitized . '@' . $suffix,
                'radius_password' => $sslvpnPassword,
            ]);
            $vpnUser->refresh();
        }

        $username = $vpnUser->radius_username;
        $password = $vpnUser->radius_password ?: Str::random(16);
        if ($enableRadius) {
            // 写入 FreeRADIUS 表（radcheck），使该 radius_username 可认证、到期时间生效
            if (!$vpnUser->radius_password) {
                $vpnUser->radius_password = $password;
                $vpnUser->saveQuietly();
            }
        }

        // 区域/IP池绑定：当产品要求独立公网IP时，必须命中支持该能力的服务器池并成功分配。
        $boundIp = null;
        if ($enableRadius) {
            $targetServerId = null;
            if ($region) {
                if ($enableWireguard) {
                    $exit = DB::table('exit_nodes')->where('region', $region)->orderBy('id')->first();
                    if ($exit && isset($exit->server_id)) {
                        $targetServerId = (int) $exit->server_id;
                    }
                }
                if (!$targetServerId) {
                    $targetServerId = $this->resolveRegionPublicIpServerId($region);
                }
            }
            $alloc = $this->allocatePublicIpForVpnUser(
                vpnUser: $vpnUser,
                region: $region,
                targetServerId: $targetServerId,
                requireDedicated: $requiresDedicatedPublicIp
            );
            $boundIp = $alloc['ip'];
            if ($boundIp && !empty($alloc['new_binding'])) {
                ProvisionResourceAuditLog::record(
                    'ip_pool_bind',
                    $auditCtx['vpn_user_id'],
                    $auditCtx['order_id'],
                    $auditCtx['product_id'],
                    $auditCtx['reseller_id'],
                    [
                        'public_ip' => $boundIp,
                        'ip_pool_id' => $alloc['ip_pool_id'],
                        'region' => $region,
                        'target_server_id' => $targetServerId,
                    ]
                );
            }
        }

        if ($enableRadius && $username) {
            $tg = 'tunnel-group=reseller_' . $vpnUser->reseller_id;

            DB::transaction(function () use ($username, $password, $expiresAt, $boundIp, $tg, $vpnUser) {
                DB::table('radcheck')->where('username', $username)->whereIn('attribute', [
                    'Cleartext-Password',
                    'Expiration',
                ])->delete();

                DB::table('radcheck')->insert([
                    'username' => $username,
                    'attribute' => 'Cleartext-Password',
                    'op' => ':=',
                    'value' => $password,
                ]);

                // FreeRADIUS expects "Expiration" like "17 Mar 2026"
                DB::table('radcheck')->insert([
                    'username' => $username,
                    'attribute' => 'Expiration',
                    'op' => ':=',
                    'value' => $expiresAt->copy()->timezone('UTC')->format('d M Y'),
                ]);

                DB::table('radreply')->where('username', $username)->whereIn('attribute', [
                    'Framed-IP-Address',
                    'Cisco-AVPair',
                    'Filter-Id',
                ])->delete();

                if ($boundIp) {
                    DB::table('radreply')->insert([
                        'username' => $username,
                        'attribute' => 'Framed-IP-Address',
                        'op' => '=',
                        'value' => $boundIp,
                    ]);
                }

                // 深度隔离：写入可识别分销商的属性，供 FreeRADIUS 策略或下游网关识别
                DB::table('radreply')->insert([
                    'username' => $username,
                    'attribute' => 'Cisco-AVPair',
                    'op' => ':=',
                    'value' => $tg,
                ]);
                DB::table('radreply')->insert([
                    'username' => $username,
                    'attribute' => 'Filter-Id',
                    'op' => ':=',
                    'value' => 'reseller_' . $vpnUser->reseller_id,
                ]);
            });

            // OCServ/RADIUS 对齐方案：以 Framed-IP-Address 作为 source_ip 下发 SNAT 映射。
            // 说明：当前控制面可稳定获得该地址，不依赖在线会话探测。
            if ($boundIp) {
                $accessServerId = $region ? $this->resolveRegionPublicIpServerId($region) : null;
                if ($accessServerId) {
                    $natServerId = $this->resolveNatCommandTargetServerId((int) $accessServerId);
                    if ($natServerId > 0) {
                        $natServer = DB::table('servers')->where('id', $natServerId)->first(['node_nat_interface']);
                        $this->syncUserSnatMapping(
                            vpnUserId: (int) $vpnUser->id,
                            serverId: $natServerId,
                            iface: (string) (($natServer->node_nat_interface ?? null) ?: 'eth0'),
                            sourceIp: (string) $boundIp,
                            publicIp: (string) $boundIp,
                            audit: $auditCtx
                        );
                    }
                }
            }
        }

        // WireGuard：仅当产品启用 WireGuard 时生成 Peer；不含 WireGuard 的产品不生成配置。
        // 1) 若客户端未提供 public key，则由 A 站自动生成一对 key（并加密保存 private key 以便下载配置）。
        // 2) 若客户端提供 public key，则仅保存 public key（无法提供私钥下载）。
        if ($enableWireguard && $region) {
            $exit = DB::table('exit_nodes')->where('region', $region)->orderBy('id')->first();
            if ($exit && isset($exit->server_id, $exit->ip_address)) {
                $server = DB::table('servers')->where('id', (int) $exit->server_id)->first();
                if ($server && ($server->protocol ?? null) === 'wireguard') {
                    $internalIp = $this->allocateVpnInternalIp((int) $exit->server_id, $vpnUser->id, $region, (string) ($server->vpn_ip_cidrs ?? ''));
                    if ($internalIp) {
                        $existingPeer = DB::table('wireguard_peers')->where('vpn_user_id', $vpnUser->id)->first();

                        // 续费时默认复用已有 keypair；仅当显式传入新公钥或历史不存在 peer 才变更/生成。
                        $clientPub = $wireguardPublicKey ?: ($existingPeer->public_key ?? null);
                        $clientPrivEnc = $existingPeer->private_key_enc ?? null;
                        if (!$clientPub) {
                            [$priv, $pub] = $this->generateWireguardKeypair();
                            $clientPub = $pub;
                            $clientPrivEnc = Crypt::encryptString($priv);
                        } elseif ($wireguardPublicKey) {
                            // 客户端主动提交新 public key 时，不持有其 private key。
                            $clientPrivEnc = null;
                        }

                        if ($existingPeer) {
                            DB::table('wireguard_peers')
                                ->where('vpn_user_id', $vpnUser->id)
                                ->update([
                                    'server_id' => (int) $exit->server_id,
                                    'public_key' => $clientPub,
                                    'private_key_enc' => $clientPrivEnc,
                                    'allowed_ips' => $internalIp . '/32',
                                    'endpoint' => (string) $exit->ip_address,
                                    'updated_at' => now(),
                                ]);
                        } else {
                            DB::table('wireguard_peers')->insert([
                                'vpn_user_id' => $vpnUser->id,
                                'server_id' => (int) $exit->server_id,
                                'public_key' => $clientPub,
                                'private_key_enc' => $clientPrivEnc,
                                'allowed_ips' => $internalIp . '/32',
                                'endpoint' => (string) $exit->ip_address,
                                'updated_at' => now(),
                                'created_at' => now(),
                            ]);
                        }
                        if ($boundIp) {
                            $natServerId = $this->resolveNatCommandTargetServerId((int) $exit->server_id);
                            if ($natServerId > 0) {
                                $natServer = DB::table('servers')->where('id', $natServerId)->first(['node_nat_interface']);
                                $this->syncUserSnatMapping(
                                    vpnUserId: (int) $vpnUser->id,
                                    serverId: $natServerId,
                                    iface: (string) (($natServer->node_nat_interface ?? null) ?: 'eth0'),
                                    sourceIp: $internalIp,
                                    publicIp: (string) $boundIp,
                                    audit: $auditCtx
                                );
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * 生成 WireGuard 客户端 keypair（Curve25519），返回 [privateKeyBase64, publicKeyBase64]
     */
    private function generateWireguardKeypair(): array
    {
        if (!function_exists('sodium_crypto_scalarmult_base')) {
            abort(500, '缺少 sodium 扩展，无法生成 WireGuard 密钥');
        }
        $priv = random_bytes(32);
        $priv[0] = chr(ord($priv[0]) & 248);
        $priv[31] = chr((ord($priv[31]) & 127) | 64);
        $pub = sodium_crypto_scalarmult_base($priv);
        return [base64_encode($priv), base64_encode($pub)];
    }

    private function allocateVpnInternalIp(int $serverId, int $vpnUserId, ?string $region, string $cidrs): ?string
    {
        // 已分配则复用
        $existing = DB::table('vpn_ip_allocations')->where('vpn_user_id', $vpnUserId)->first();
        if ($existing && isset($existing->ip_address)) {
            return (string) $existing->ip_address;
        }

        $cidrList = array_values(array_filter(array_map('trim', preg_split('/[\\s,]+/', $cidrs) ?: [])));
        if (!$cidrList) {
            return null;
        }

        foreach ($cidrList as $cidr) {
            $ip = $this->allocateFromCidr($serverId, $vpnUserId, $region, $cidr);
            if ($ip) return $ip;
        }
        return null;
    }

    private function allocateFromCidr(int $serverId, int $vpnUserId, ?string $region, string $cidr): ?string
    {
        // 仅支持 IPv4 CIDR，例如 10.66.0.0/24
        if (!preg_match('/^([0-9]{1,3}(?:\\.[0-9]{1,3}){3})\\/(\\d{1,2})$/', $cidr, $m)) {
            return null;
        }
        $base = $m[1];
        $mask = (int) $m[2];
        if ($mask < 16 || $mask > 30) {
            return null;
        }

        $baseLong = ip2long($base);
        if ($baseLong === false) return null;
        $hostBits = 32 - $mask;
        $count = 1 << $hostBits;
        // 跳过 network(.0) 和 gateway(.1) 与 broadcast(最后一位)，从 .2 开始分配
        $start = 2;
        $end = $count - 2;
        if ($end <= $start) return null;

        for ($i = $start; $i <= $end; $i++) {
            $candidateLong = $baseLong + $i;
            $candidate = long2ip($candidateLong);
            if (!$candidate) continue;

            try {
                DB::table('vpn_ip_allocations')->insert([
                    'server_id' => $serverId,
                    'vpn_user_id' => $vpnUserId,
                    'ip_address' => $candidate,
                    'region' => $region,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                return $candidate;
            } catch (\Throwable $e) {
                // 冲突则继续尝试下一个
                continue;
            }
        }
        return null;
    }

    private function resolveRegionPublicIpServerId(string $region): ?int
    {
        $row = DB::table('servers')
            ->where('region', $region)
            ->where('split_nat_multi_public_ip_enabled', 1)
            ->orderBy('id')
            ->first(['id']);
        return $row ? (int) $row->id : null;
    }

    /**
     * @return array{ip: ?string, ip_pool_id: ?int, new_binding: bool}
     */
    private function allocatePublicIpForVpnUser(
        VpnUser $vpnUser,
        ?string $region,
        ?int $targetServerId,
        bool $requireDedicated
    ): array {
        if (!$region) {
            if ($requireDedicated) {
                throw new HttpResponseException(response()->json(['message' => '该产品要求独立公网IP，必须指定区域'], 422));
            }
            $existing = IpPool::query()->where('vpn_user_id', $vpnUser->id)->orderByDesc('id')->first();
            if ($existing) {
                return [
                    'ip' => $existing->ip_address,
                    'ip_pool_id' => (int) $existing->id,
                    'new_binding' => false,
                ];
            }

            return ['ip' => null, 'ip_pool_id' => null, 'new_binding' => false];
        }

        if ($requireDedicated) {
            if (!$targetServerId) {
                throw new HttpResponseException(response()->json(['message' => '该区域未找到支持“多公网IP绑定”的接入服务器'], 422));
            }
            $server = DB::table('servers')->where('id', $targetServerId)->first(['id', 'split_nat_multi_public_ip_enabled']);
            if (!$server || !(bool) ($server->split_nat_multi_public_ip_enabled ?? false)) {
                throw new HttpResponseException(response()->json(['message' => '目标服务器未启用“可绑定多个公网IP”'], 422));
            }
        }

        $boundIp = null;
        $ipPoolId = null;
        $newBinding = false;
        DB::transaction(function () use ($vpnUser, $region, $targetServerId, $requireDedicated, &$boundIp, &$ipPoolId, &$newBinding) {
            $existing = IpPool::query()
                ->where('vpn_user_id', $vpnUser->id)
                ->lockForUpdate()
                ->orderByDesc('id')
                ->first();
            if ($existing) {
                $boundIp = $existing->ip_address;
                $ipPoolId = (int) $existing->id;
                $newBinding = false;

                return;
            }

            $q = IpPool::query()
                ->where('region', $region)
                ->where('status', 'free')
                ->whereNull('vpn_user_id');
            if ($targetServerId) {
                $q->where('server_id', $targetServerId);
            }

            $ip = $q->lockForUpdate()->orderBy('id')->first();
            if (!$ip && !$requireDedicated) {
                $ip = IpPool::query()
                    ->where('region', $region)
                    ->where('status', 'free')
                    ->whereNull('vpn_user_id')
                    ->lockForUpdate()
                    ->orderBy('id')
                    ->first();
            }

            if (!$ip && $requireDedicated) {
                throw new HttpResponseException(response()->json(['message' => '当前区域/服务器的公网IP池不足，请先补充IP池'], 422));
            }
            if ($ip) {
                $ip->update([
                    'status' => 'used',
                    'vpn_user_id' => $vpnUser->id,
                ]);
                $boundIp = $ip->ip_address;
                $ipPoolId = (int) $ip->id;
                $newBinding = true;
            }
        });

        return [
            'ip' => $boundIp,
            'ip_pool_id' => $ipPoolId,
            'new_binding' => $newBinding,
        ];
    }

    private function resolveNatCommandTargetServerId(int $accessServerId): int
    {
        $server = DB::table('servers')
            ->where('id', $accessServerId)
            ->first(['id', 'nat_topology', 'split_nat_server_id']);
        if (!$server) {
            return 0;
        }
        $topo = (string) ($server->nat_topology ?? 'combined');
        if ($topo === 'split_access' && !empty($server->split_nat_server_id)) {
            return (int) $server->split_nat_server_id;
        }
        return (int) $server->id;
    }

    private function enqueueSnatMapCommand(int $serverId, string $iface, string $sourceIp, string $publicIp): void
    {
        DB::table('agent_commands')->insert([
            'server_id' => $serverId,
            'type' => 'apply_snat_map',
            'payload' => json_encode([
                'interface' => $iface,
                'source_ip' => $sourceIp,
                'public_ip' => $publicIp,
            ], JSON_UNESCAPED_UNICODE),
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function enqueueRemoveSnatMapCommand(int $serverId, string $iface, string $sourceIp, string $publicIp): void
    {
        DB::table('agent_commands')->insert([
            'server_id' => $serverId,
            'type' => 'remove_snat_map',
            'payload' => json_encode([
                'interface' => $iface,
                'source_ip' => $sourceIp,
                'public_ip' => $publicIp,
            ], JSON_UNESCAPED_UNICODE),
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  array{vpn_user_id: int, order_id: int, product_id: int, reseller_id: int}|null  $audit
     */
    private function syncUserSnatMapping(int $vpnUserId, int $serverId, string $iface, string $sourceIp, string $publicIp, ?array $audit = null): void
    {
        $sourceIp = trim($sourceIp);
        if ($sourceIp === '' || trim($publicIp) === '') {
            return;
        }
        $existing = DB::table('user_public_ip_snat_maps')
            ->where('vpn_user_id', $vpnUserId)
            ->where('source_ip', $sourceIp)
            ->where('status', 'active')
            ->orderByDesc('id')
            ->first();

        if ($existing) {
            $same = ((int) $existing->server_id === $serverId)
                && ((string) $existing->nat_interface === $iface)
                && ((string) $existing->source_ip === $sourceIp)
                && ((string) $existing->public_ip === $publicIp);
            if ($same) {
                return;
            }
            $this->enqueueRemoveSnatMapCommand(
                (int) $existing->server_id,
                (string) $existing->nat_interface,
                (string) $existing->source_ip,
                (string) $existing->public_ip
            );
            DB::table('user_public_ip_snat_maps')
                ->where('id', (int) $existing->id)
                ->update([
                    'status' => 'released',
                    'released_at' => now(),
                    'updated_at' => now(),
                ]);
            if ($audit) {
                ProvisionResourceAuditLog::record(
                    'snat_replaced',
                    $audit['vpn_user_id'],
                    $audit['order_id'],
                    $audit['product_id'],
                    $audit['reseller_id'],
                    [
                        'previous' => [
                            'map_id' => (int) $existing->id,
                            'server_id' => (int) $existing->server_id,
                            'nat_interface' => (string) $existing->nat_interface,
                            'source_ip' => (string) $existing->source_ip,
                            'public_ip' => (string) $existing->public_ip,
                        ],
                        'next' => [
                            'server_id' => $serverId,
                            'nat_interface' => $iface,
                            'source_ip' => $sourceIp,
                            'public_ip' => $publicIp,
                        ],
                    ]
                );
            }
        }

        $this->enqueueSnatMapCommand($serverId, $iface, $sourceIp, $publicIp);
        $mapId = (int) DB::table('user_public_ip_snat_maps')->insertGetId([
            'vpn_user_id' => $vpnUserId,
            'server_id' => $serverId,
            'nat_interface' => $iface,
            'source_ip' => $sourceIp,
            'public_ip' => $publicIp,
            'status' => 'active',
            'applied_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        if ($audit) {
            ProvisionResourceAuditLog::record(
                'snat_applied',
                $audit['vpn_user_id'],
                $audit['order_id'],
                $audit['product_id'],
                $audit['reseller_id'],
                [
                    'map_id' => $mapId,
                    'server_id' => $serverId,
                    'nat_interface' => $iface,
                    'source_ip' => $sourceIp,
                    'public_ip' => $publicIp,
                ]
            );
        }
    }
}

