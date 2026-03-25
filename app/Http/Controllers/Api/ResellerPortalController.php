<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reseller;
use App\Models\ResellerApiKey;
use App\Models\ResellerBalanceTransaction;
use App\Models\ResellerIncomeRecord;
use App\Models\ResellerPaymentOrder;
use App\Services\Epay\EpayService;
use App\Support\PaymentConfig;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Models\VpnUser;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * 分销商门户（Sanctum：Reseller 模型），余额、充值、管理 API Key
 */
class ResellerPortalController extends Controller
{
    private function reseller(Request $request): Reseller
    {
        $r = $request->user();
        if (!$r instanceof Reseller) {
            abort(401, '未登录');
        }

        return $r;
    }

    public function me(Request $request): JsonResponse
    {
        $r = $this->reseller($request)->fresh();

        return response()->json([
            'id' => $r->id,
            'name' => $r->name,
            'email' => $r->email,
            'balance_cents' => (int) $r->balance_cents,
            'balance_enforced' => (bool) $r->balance_enforced,
            'status' => $r->status,
        ]);
    }

    public function balanceTransactions(Request $request): JsonResponse
    {
        $r = $this->reseller($request);
        $limit = min((int) $request->query('limit', 50), 200);

        $rows = ResellerBalanceTransaction::query()
            ->where('reseller_id', $r->id)
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return response()->json($rows->map(fn (ResellerBalanceTransaction $t) => [
            'id' => $t->id,
            'amount_cents' => (int) $t->amount_cents,
            'balance_after_cents' => (int) $t->balance_after_cents,
            'type' => $t->type,
            'meta' => $t->meta,
            'created_at' => $t->created_at?->format('c'),
        ])->values()->all());
    }

    /**
     * MVP：模拟充值（可通过 EPAY_ALLOW_SIMULATED_RECHARGE=false 关闭）
     */
    public function recharge(Request $request): JsonResponse
    {
        if (!PaymentConfig::allowSimulatedRecharge()) {
            return response()->json(['message' => '模拟充值已关闭，请使用在线支付'], 403);
        }

        $data = $request->validate([
            'amount_cents' => 'required|integer|min:1|max:100000000',
            'note' => 'nullable|string|max:255',
        ]);

        $r = $this->reseller($request);

        DB::transaction(function () use ($r, $data) {
            $locked = Reseller::whereKey($r->id)->lockForUpdate()->first();
            if (!$locked) {
                throw new HttpResponseException(response()->json(['message' => '账号不存在'], 404));
            }
            $locked->balance_cents = (int) $locked->balance_cents + (int) $data['amount_cents'];
            $locked->save();

            ResellerBalanceTransaction::create([
                'reseller_id' => $locked->id,
                'amount_cents' => (int) $data['amount_cents'],
                'balance_after_cents' => (int) $locked->balance_cents,
                'type' => 'recharge',
                'meta' => array_filter([
                    'note' => $data['note'] ?? null,
                    'channel' => 'simulated',
                ]),
            ]);
        });

        return response()->json([
            'message' => '充值成功（模拟入账，生产环境请对接支付）',
            'balance_cents' => (int) $r->fresh()->balance_cents,
        ]);
    }

    /**
     * 创建易支付订单并返回收银台跳转 URL（彩虹易支付 V1 / submit.php）
     */
    public function createEpayRecharge(Request $request): JsonResponse
    {
        if (!PaymentConfig::enabled()) {
            return response()->json(['message' => '在线支付未开启'], 503);
        }

        $service = EpayService::fromConfig();
        if ($service === null || !$service->isConfigured()) {
            return response()->json(['message' => '易支付未正确配置（EPAY_GATEWAY / EPAY_PID / EPAY_KEY）'], 503);
        }

        $data = $request->validate([
            'amount_cents' => ['required', 'integer', 'min:100', 'max:100000000'],
            'note' => ['nullable', 'string', 'max:255'],
            'pay_type' => ['nullable', 'string', 'max:32'], // alipay / wxpay / qqpay，留空则跳转聚合收银台
        ]);

        $r = $this->reseller($request);

        $outTradeNo = 'RP'.now()->format('YmdHis').Str::upper(Str::random(10));
        if (strlen($outTradeNo) > 64) {
            $outTradeNo = substr($outTradeNo, 0, 64);
        }

        $money = number_format(((int) $data['amount_cents']) / 100, 2, '.', '');

        $notifyUrl = PaymentConfig::notifyUrl();
        $returnUrl = PaymentConfig::returnUrl();

        $extra = [];
        if (!empty($data['pay_type'])) {
            $extra['type'] = $data['pay_type'];
        }

        ResellerPaymentOrder::create([
            'reseller_id' => $r->id,
            'out_trade_no' => $outTradeNo,
            'amount_cents' => (int) $data['amount_cents'],
            'status' => 'pending',
            'pay_type' => $data['pay_type'] ?? null,
            'meta' => array_filter([
                'note' => $data['note'] ?? null,
            ]),
        ]);

        $name = '分销商余额充值 '.$money.' 元';
        $payUrl = $service->buildPayUrl(
            $outTradeNo,
            $name,
            $money,
            $notifyUrl,
            $returnUrl,
            $extra,
        );

        return response()->json([
            'pay_url' => $payUrl,
            'out_trade_no' => $outTradeNo,
        ]);
    }

    /**
     * 列出 API Key（脱敏，仅显示前缀与末尾）
     */
    public function listApiKeys(Request $request): JsonResponse
    {
        $r = $this->reseller($request);
        $keys = $r->apiKeys()->orderByDesc('id')->get(['id', 'name', 'api_key', 'created_at']);

        return response()->json($keys->map(function (ResellerApiKey $k) {
            return [
                'id' => $k->id,
                'name' => $k->name ?: ('Key #' . $k->id),
                // 分销商后台：展示完整 API Key，并提供前端一键复制。
                'api_key' => $k->api_key,
                // 同时保留预览字段，兼容旧前端（如有）。
                'api_key_preview' => $this->maskApiKey($k->api_key),
                'created_at' => $k->created_at?->format('c'),
            ];
        })->values()->all());
    }

    /**
     * 新建 API Key；完整 key 仅在本次响应中返回一次
     */
    public function createApiKey(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'nullable|string|max:255',
        ]);

        $r = $this->reseller($request);
        $key = ResellerApiKey::create([
            'reseller_id' => $r->id,
            // migration: name 不可为 NULL，因此需要把 null 兜底成空字符串
            'name' => ($data['name'] ?? '') ?: '',
        ]);

        return response()->json([
            'id' => $key->id,
            'name' => $key->name,
            'api_key' => $key->api_key,
            'created_at' => $key->created_at?->format('c'),
            'warning' => '请妥善保存，服务端不再完整展示该密钥',
        ], 201);
    }

    public function deleteApiKey(Request $request, int $id): JsonResponse
    {
        $r = $this->reseller($request);
        $deleted = ResellerApiKey::query()
            ->where('reseller_id', $r->id)
            ->whereKey($id)
            ->delete();
        if (!$deleted) {
            return response()->json(['message' => 'API Key 不存在'], 404);
        }

        return response()->json(null, 204);
    }

    public function stats(Request $request): JsonResponse
    {
        $r = $this->reseller($request)->fresh();

        $incomeBase = ResellerIncomeRecord::query()->where('reseller_id', $r->id);

        $purchaseCount = (clone $incomeBase)->where('kind', 'purchase')->count();
        $renewCount = (clone $incomeBase)->where('kind', 'renew')->count();
        $incomeCount = (clone $incomeBase)->count();

        $totalVpnUsersCount = (clone $incomeBase)
            ->whereNotNull('vpn_user_id')
            ->distinct()
            ->count('vpn_user_id');

        $totalAOrdersCount = (clone $incomeBase)
            ->whereNotNull('a_order_id')
            ->distinct()
            ->count('a_order_id');

        // 成本口径：按每笔收入流水关联的 A 订单 -> 产品价格累计。
        // 与真实扣费同口径：使用 reseller_balance_transactions 里的扣款流水（amount_cents 录入为负数）
        $totalCostCents = (int) -ResellerBalanceTransaction::query()
            ->where('reseller_id', $r->id)
            ->whereIn('type', ['provision_purchase', 'provision_renew'])
            ->sum('amount_cents');

        $activeVpnUsersCount = VpnUser::query()
            ->where('reseller_id', $r->id)
            ->where('status', 'active')
            ->count();

        $rechargeTotalCents = ResellerBalanceTransaction::query()
            ->where('reseller_id', $r->id)
            ->where('type', 'recharge')
            ->where('amount_cents', '>', 0)
            ->sum('amount_cents');

        return response()->json([
            'balance_cents' => (int) $r->balance_cents,
            'balance_enforced' => (bool) $r->balance_enforced,
            'status' => $r->status,

            // 销量/订单
            'purchase_count' => (int) $purchaseCount,
            'renew_count' => (int) $renewCount,
            'income_records_count' => (int) $incomeCount,

            // 用户维度
            'total_vpn_users_count' => (int) $totalVpnUsersCount,
            'active_vpn_users_count' => (int) $activeVpnUsersCount,
            'total_a_orders_count' => (int) $totalAOrdersCount,

            // 财务口径
            'total_cost_cents' => (int) $totalCostCents,
            'recharge_total_cents' => (int) $rechargeTotalCents,

            // 支付能力（前端控制「模拟充值 / 在线支付」展示）
            'epay_enabled' => PaymentConfig::enabled(),
            'epay_configured' => EpayService::fromConfig()?->isConfigured() ?? false,
            'simulated_recharge_allowed' => PaymentConfig::allowSimulatedRecharge(),
        ]);
    }

    public function vpnUsers(Request $request): JsonResponse
    {
        $r = $this->reseller($request);

        $limit = min((int) $request->query('limit', 50), 200);
        $status = $request->query('status');
        if ($status !== null && !in_array($status, ['active', 'suspended'], true)) {
            return response()->json(['message' => 'status 无效'], 422);
        }

        $q = VpnUser::query()
            ->where('reseller_id', $r->id);
        if ($status) {
            $q->where('status', $status);
        }

        $vpnUsers = $q->orderByDesc('id')
            ->limit($limit)
            ->with(['orders' => function ($orderQ) use ($r) {
                $orderQ->where('reseller_id', $r->id)
                    ->orderByDesc('id')
                    ->with('product:id,name,price_cents');
            }])
            ->get();

        return response()->json($vpnUsers->map(function (VpnUser $vu) {
            $latestOrder = $vu->orders->first();
            return [
                'id' => $vu->id,
                'email' => $vu->email,
                'name' => $vu->name,
                'status' => $vu->status,
                'region' => $vu->region,
                'created_at' => $vu->created_at?->format('c'),
                'latest_order' => $latestOrder ? [
                    'id' => $latestOrder->id,
                    'status' => $latestOrder->status,
                    'expires_at' => $latestOrder->expires_at?->format('c'),
                    'product' => $latestOrder->product ? [
                        'id' => $latestOrder->product->id,
                        'name' => $latestOrder->product->name,
                        'price_cents' => (int) $latestOrder->product->price_cents,
                    ] : null,
                ] : null,
            ];
        })->values()->all());
    }

    public function updateVpnUserStatus(Request $request, int $id): JsonResponse
    {
        $r = $this->reseller($request);

        $v = $request->validate([
            'status' => 'required|string|in:active,suspended',
        ]);

        $vpnUser = VpnUser::query()
            ->where('reseller_id', $r->id)
            ->whereKey($id)
            ->first();

        if (!$vpnUser) {
            return response()->json(['message' => 'VPN 用户不存在'], 404);
        }

        $vpnUser->update(['status' => $v['status']]);

        return response()->json([
            'id' => $vpnUser->id,
            'status' => $vpnUser->status,
        ]);
    }

    /**
     * 下载 B 站源码（用于分销商部署接入）
     */
    public function downloadBSource(Request $request): BinaryFileResponse
    {
        // 仅需鉴权即可下载；不做额外权限隔离（如需可加 reseller_id 校验）。
        $this->reseller($request);

        $bDir = realpath(base_path('../B'));
        if (!$bDir || !is_dir($bDir)) {
            abort(500, 'B 站源码目录不存在，请联系管理员');
        }

        $cacheDir = storage_path('app/reseller_downloads');
        if (!is_dir($cacheDir)) {
            File::makeDirectory($cacheDir, 0755, true);
        }

        $zipPath = $cacheDir . '/b_site_source.zip';
        $zipMTime = filemtime($zipPath);
        $latestSourceMTime = filemtime($bDir);

        // 简单缓存：B 目录修改时间未变则复用压缩包
        if (!$zipMTime || ($latestSourceMTime && $zipMTime < $latestSourceMTime)) {
            @unlink($zipPath);

            $zip = new \ZipArchive();
            if ($zip->open($zipPath, \ZipArchive::CREATE) !== true) {
                abort(500, '生成源码压缩包失败');
            }

            $excludePrefixes = [
                'vendor/',
                'node_modules/',
                'storage/',
                '.env',
                '.env.example',
                'public/build/',
            ];

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($bDir, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $file) {
                /** @var \SplFileInfo $file */
                if ($file->isDir()) {
                    continue;
                }

                $absPath = $file->getRealPath();
                if (!$absPath) {
                    continue;
                }

                $relPath = ltrim(str_replace($bDir, '', $absPath), DIRECTORY_SEPARATOR);

                // 部署需要示例 env，确保保留
                if ($relPath === '.env.example') {
                    $zip->addFile($absPath, $relPath);
                    continue;
                }

                $shouldExclude = false;
                foreach ($excludePrefixes as $prefix) {
                    if (str_starts_with($relPath, $prefix) || str_starts_with($relPath, rtrim($prefix, '/'))) {
                        $shouldExclude = true;
                        break;
                    }
                }

                // 忽略 vendor/node_modules/storage 等大目录
                if ($shouldExclude) {
                    continue;
                }

                $zip->addFile($absPath, $relPath);
            }

            $zip->close();
        }

        return response()->download($zipPath, 'b_site_source.zip', [
            'Content-Type' => 'application/zip',
        ]);
    }

    /**
     * 修改登录密码（需验证当前密码；成功后使其他门户 Token 失效）
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
        ]);

        $r = $this->reseller($request);

        if (!$r->password || !Hash::check($data['current_password'], $r->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['当前密码不正确'],
            ]);
        }

        $currentToken = $r->currentAccessToken();
        $r->password = $data['password'];
        $r->save();

        if ($currentToken) {
            $r->tokens()
                ->where('name', 'reseller_portal')
                ->where('id', '!=', $currentToken->id)
                ->delete();
        }

        return response()->json(['message' => '密码已更新']);
    }

    private function maskApiKey(string $apiKey): string
    {
        if (strlen($apiKey) <= 12) {
            return substr($apiKey, 0, 4) . '…';
        }

        return substr($apiKey, 0, 6) . '…' . substr($apiKey, -4);
    }
}
