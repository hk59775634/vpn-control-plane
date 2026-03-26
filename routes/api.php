<?php

use App\Http\Controllers\Api\AdminCrudController;
use App\Http\Controllers\Api\AdminPaymentSettingsController;
use App\Http\Controllers\Api\AdminRuntimeSettingsController;
use App\Http\Controllers\Api\AgentOpsController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EpayNotifyController;
use App\Http\Controllers\Api\PublicController;
use App\Http\Controllers\Api\ResellerAuthController;
use App\Http\Controllers\Api\ResellerPortalController;
use App\Http\Controllers\Api\ResellerProvisionController;
use App\Http\Controllers\Api\ResellerSelfController;
use App\Http\Controllers\Api\ResellerValidateController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| A 站控制面 API（与 1.0 规范对齐）
|--------------------------------------------------------------------------
*/

// 健康
Route::get('/health', fn () => response()->json(['status' => 'ok']));
Route::get('/version', fn () => response()->json(['version' => '2.0-a']));

// 公开：认证（按 IP + 路径限流）
Route::post('/v1/auth/register', [AuthController::class, 'register'])->middleware('throttle:a-auth-register');
Route::post('/v1/auth/login', [AuthController::class, 'login'])->middleware('throttle:a-auth-login');

// 公开：供 B 站校验 API Key（不要求登录）
Route::post('/v1/reseller/validate', [ResellerValidateController::class, 'validate'])->middleware('throttle:a-reseller-validate');

// 公开：分销商门户注册/登录（Sanctum token，tokenable = Reseller）
Route::post('/v1/reseller-portal/register', [ResellerAuthController::class, 'register'])->middleware('throttle:a-reseller-portal-register');
Route::post('/v1/reseller-portal/login', [ResellerAuthController::class, 'login'])->middleware('throttle:a-reseller-portal-login');

// 节点 Agent API（首期 HTTP MVP）
Route::get('/v1/agent/package', [AgentOpsController::class, 'downloadAgentPackage']);
Route::get('/v1/agent/install-context', [AgentOpsController::class, 'installContextBySourceIp'])
    ->middleware('throttle:60,1');
Route::get('/v1/agent/install-source-debug', [AgentOpsController::class, 'installSourceDebug'])
    ->middleware('throttle:60,1');
Route::get('/v1/agent/install-manifest', [AgentOpsController::class, 'installManifest']);
Route::post('/v1/agent/install-progress', [AgentOpsController::class, 'reportInstallProgress']);
Route::post('/v1/agent/register', [AgentOpsController::class, 'register']);
// 与 register 相同；避免 URL 含 register 被 CDN/WAF 误拦（Cloudflare 等）
Route::post('/v1/agent/bootstrap', [AgentOpsController::class, 'register']);
Route::post('/v1/agent/heartbeat', [AgentOpsController::class, 'heartbeat']);
Route::post('/v1/agent/commands/ack', [AgentOpsController::class, 'ack']);

// 易支付异步通知（无鉴权，MD5 签名校验）
Route::match(['get', 'post'], '/v1/payments/epay/notify', [EpayNotifyController::class, 'notify'])->middleware('throttle:a-epay-notify');

// 分销商门户（需 Bearer Sanctum，且为 Reseller 账号）
Route::middleware(['auth:sanctum', 'reseller_portal'])->group(function (): void {
    $portal = ResellerPortalController::class;
    Route::post('/v1/reseller-portal/logout', [ResellerAuthController::class, 'logout']);
    Route::patch('/v1/reseller-portal/password', [$portal, 'updatePassword']);
    Route::get('/v1/reseller-portal/me', [$portal, 'me']);
    Route::get('/v1/reseller-portal/stats', [$portal, 'stats']);
    Route::get('/v1/reseller-portal/vpn_users', [$portal, 'vpnUsers']);
    Route::patch('/v1/reseller-portal/vpn_users/{id}/status', [$portal, 'updateVpnUserStatus']);
    Route::get('/v1/reseller-portal/balance/transactions', [$portal, 'balanceTransactions']);
    Route::post('/v1/reseller-portal/recharge', [$portal, 'recharge']);
    Route::post('/v1/reseller-portal/recharge/epay', [$portal, 'createEpayRecharge']);
    Route::get('/v1/reseller-portal/api_keys', [$portal, 'listApiKeys']);
    Route::post('/v1/reseller-portal/api_keys', [$portal, 'createApiKey']);
    Route::delete('/v1/reseller-portal/api_keys/{id}', [$portal, 'deleteApiKey']);
    Route::get('/v1/reseller-portal/b/download', [$portal, 'downloadBSource']);
});

// 公开：可售产品列表（供 B 站组合为分销产品）
Route::get('/v1/products/public', [PublicController::class, 'productsPublic']);
// 公开：可用线路/区域列表（供 B 站下拉选择）
Route::get('/v1/regions/public', [PublicController::class, 'regionsPublic']);

// 分销商自助接口（Bearer API Key 鉴权，供 B 站转发或直接调用）
Route::middleware('reseller_api_key')->group(function (): void {
    $self = ResellerSelfController::class;
    Route::get('/v1/reseller/me', [$self, 'me']);
    Route::get('/v1/reseller/me/api_keys', [$self, 'apiKeys']);

    // 分销商订单开通（供 B 站在终端用户支付成功后调用）
    Route::post('/v1/reseller/orders', [ResellerProvisionController::class, 'create']);

    // 分销商同步终端用户（注册时即可在 A 站建用户）
    Route::post('/v1/reseller/users/sync', [\App\Http\Controllers\Api\ResellerUserSyncController::class, 'sync']);

    // 分销商拉取 WireGuard 配置（wg-quick）
    Route::get('/v1/reseller/wireguard/config', [\App\Http\Controllers\Api\ResellerWireguardConfigController::class, 'config']);
});

// 以下接口需登录且为管理员
Route::middleware(['auth:sanctum', 'admin'])->group(function (): void {
    $admin = AdminCrudController::class;

    Route::post('/v1/auth/logout', [AuthController::class, 'logout']);
    Route::patch('/v1/auth/password', [AuthController::class, 'updatePassword']);

    // 产品
    Route::get('/v1/products', [$admin, 'listProducts']);
Route::post('/v1/products', fn () => response()->json([], 501));

// 服务器
Route::get('/v1/servers', [$admin, 'listServers']);
Route::post('/v1/servers', [$admin, 'createServer']);
Route::put('/v1/servers/{id}', [$admin, 'updateServer']);
Route::delete('/v1/servers/{id}', [$admin, 'deleteServer']);

// 出口节点
Route::get('/v1/exit_nodes', [$admin, 'listExitNodes']);
Route::post('/v1/exit_nodes', [$admin, 'createExitNode']);
Route::put('/v1/exit_nodes/{id}', [$admin, 'updateExitNode']);
Route::delete('/v1/exit_nodes/{id}', [$admin, 'deleteExitNode']);

// IP 池
Route::get('/v1/ip_pool', [$admin, 'listIpPool']);
Route::post('/v1/ip_pool', [$admin, 'createIpPool']);
Route::post('/v1/ip_pool/{id}/release', [$admin, 'releaseIpPool']);
Route::get('/v1/admin/snat_maps', [$admin, 'listSnatMaps']);
Route::get('/v1/admin/provision_audit_logs', [$admin, 'listProvisionAuditLogs']);

// 分销商
Route::get('/v1/resellers', [$admin, 'listResellers']);
Route::post('/v1/resellers', [$admin, 'createReseller']);
Route::get('/v1/resellers/{id}', [$admin, 'getReseller']);
Route::put('/v1/resellers/{id}', [$admin, 'updateReseller']);
Route::delete('/v1/resellers/{id}', [$admin, 'deleteReseller']);

// 用户订单
Route::get('/v1/users/{user_id}/vpn_users', [$admin, 'listVpnUsersByUser']);
Route::post('/v1/users/{user_id}/vpn_users', [$admin, 'createVpnUser']);
Route::delete('/v1/users/{user_id}/vpn_users/{vpn_user_id}', [$admin, 'deleteVpnUser']);
Route::get('/v1/users/{user_id}/orders', [$admin, 'listOrdersByUser']);
Route::post('/v1/users/{user_id}/orders', [$admin, 'createOrder']);
Route::put('/v1/me/password', function () { return response()->json([], 501); });

// 管理端
Route::get('/v1/admin/summary', [$admin, 'summary']);
Route::get('/v1/admin/analytics', [$admin, 'analytics']);
Route::get('/v1/admin/servers', [$admin, 'listServers']);
Route::get('/v1/admin/online_sessions', [$admin, 'listOnlineSessions']);
Route::get('/v1/admin/users', [$admin, 'listUsers']);
Route::patch('/v1/admin/users/{id}/role', [$admin, 'updateUserRole']);
Route::post('/v1/admin/users', [$admin, 'createUser']);
Route::put('/v1/admin/users/{id}/password', [$admin, 'updateUserPassword']);
Route::delete('/v1/admin/users/{id}', [$admin, 'deleteUser']);
Route::get('/v1/admin/orders', [$admin, 'listOrders']);
Route::get('/v1/admin/income_records', [$admin, 'listIncomeRecords']);
Route::delete('/v1/admin/orders/{id}', [$admin, 'deleteOrder']);
Route::post('/v1/admin/products', [$admin, 'createProduct']);
Route::put('/v1/admin/products/{id}', [$admin, 'updateProduct']);
Route::delete('/v1/admin/products/{id}', [$admin, 'deleteProduct']);
Route::get('/v1/admin/vpn_users', [$admin, 'listVpnUsersAdmin']);
Route::get('/v1/admin/purchased_products', [$admin, 'listPurchasedProductsAdmin']);
Route::get('/v1/admin/servers/{server_id}/commands', [AgentOpsController::class, 'listCommands']);
Route::post('/v1/admin/servers/{server_id}/commands', [AgentOpsController::class, 'enqueueCommand']);
Route::post('/v1/admin/servers/{server_id}/agent/install', [AgentOpsController::class, 'installAgent']);
Route::get('/v1/vpn_users/{id}', [$admin, 'showVpnUser']);
Route::get('/v1/admin/vpn_users/{id}/wireguard_config', [\App\Http\Controllers\Api\AdminWireguardController::class, 'show']);
Route::put('/v1/vpn_users/{id}', [$admin, 'updateVpnUser']);
Route::post('/v1/admin/resellers', [$admin, 'createReseller']);
Route::get('/v1/admin/resellers/{id}/api_keys', [$admin, 'listResellerApiKeys']);
Route::post('/v1/admin/resellers/{id}/api_keys', [$admin, 'createResellerApiKey']);
Route::get('/v1/admin/resellers/{id}/balance/transactions', [$admin, 'listResellerBalanceTransactions']);
Route::post('/v1/admin/resellers/{id}/balance_adjust', [$admin, 'adjustResellerBalance']);
    Route::get('/v1/admin/settings/payment', [AdminPaymentSettingsController::class, 'show']);
    Route::put('/v1/admin/settings/payment', [AdminPaymentSettingsController::class, 'update']);
    Route::get('/v1/admin/settings/runtime', [AdminRuntimeSettingsController::class, 'show']);
    Route::put('/v1/admin/settings/runtime', [AdminRuntimeSettingsController::class, 'update']);
    Route::get('/v1/admin/client_commands', function () { return response()->json([]); });
    Route::post('/v1/admin/client_commands', function () { return response()->json([], 501); });
    Route::get('/v1/admin/wireguard_peers', function () { return response()->json([]); });

// IP 池删改
Route::delete('/v1/ip_pool/{id}', [AdminCrudController::class, 'deleteIpPool']);
Route::post('/v1/ip_pool/batch_delete', [AdminCrudController::class, 'batchDeleteIpPool']);

// 其他占位
Route::get('/v1/ip_pool/allocate', function () { return response()->json([], 501); });
Route::get('/v1/ip_pool/{id}', function () { return response()->json([], 404); });
Route::get('/v1/routes', function () { return response()->json([]); });
Route::post('/v1/routes', function () { return response()->json([], 501); });
Route::get('/v1/routes/servers/{access_server_id}', function () { return response()->json([], 404); });
Route::delete('/v1/routes/servers/{access_server_id}', function () { return response()->json([], 501); });
Route::get('/v1/servers/{id}/bandwidth', function () { return response()->json([], 404); });
Route::put('/v1/servers/{id}/bandwidth', function () { return response()->json([], 501); });
Route::post('/v1/servers/{id}/bandwidth', function () { return response()->json([], 501); });
Route::get('/v1/antiblock', function () { return response()->json([]); });
Route::get('/v1/servers/{server_id}/antiblock', function () { return response()->json([], 404); });
Route::post('/v1/servers/{server_id}/antiblock', function () { return response()->json([], 501); });
Route::get('/v1/vpn_users/{vpn_user_id}/wireguard', function () { return response()->json([], 404); });
Route::post('/v1/vpn_users/{vpn_user_id}/wireguard', function () { return response()->json([], 501); });
Route::get('/v1/monitoring/users/{user_id}/traffic', function () { return response()->json([]); });
Route::get('/v1/monitoring/servers/traffic', function () { return response()->json([]); });
Route::get('/v1/client/vpn_users/{vpn_user_id}/wireguard_config', function () { return response()->json([], 404); });
Route::get('/v1/client/vpn_users/{vpn_user_id}/commands', function () { return response()->json([]); });
Route::post('/v1/client/vpn_users/{vpn_user_id}/commands/consume', function () { return response()->json([], 501); });

    // B 端分销商认证（A 站若统一提供 API 可在此；也可由 B 站自实现）
    Route::post('/v1/reseller/auth', function (Request $request) {
        return response()->json(['message' => 'TODO: reseller auth', 'token' => ''], 501);
    });
});
