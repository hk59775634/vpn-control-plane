<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VpnUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 分销商同步终端用户到 A 站：仅写入 vpn_users 表（VPN 终端用户），不操作 users 表。
 * A 站 users 表仅保留管理员账号。
 *
 * 受 EnsureResellerApiKey 中间件保护，使用 Bearer API Key 鉴权。
 */
class ResellerUserSyncController extends Controller
{
    /**
     * POST /api/v1/reseller/users/sync
     *
     * 请求体: user_email (required), user_name (optional)
     *
     * 行为: 按 (email, reseller_id) 在 vpn_users 表中 firstOrCreate，不创建 users 表记录。
     */
    public function sync(Request $request): JsonResponse
    {
        $reseller = $request->attributes->get('reseller');
        if (!$reseller) {
            return response()->json(['message' => '未授权'], 401);
        }

        $data = $request->validate([
            'user_email' => 'required|email|max:255',
            'user_name' => 'nullable|string|max:255',
            'region' => 'nullable|string|max:64',
        ]);

        $displayName = $data['user_name'] ?? $data['user_email'];

        $vpnUser = VpnUser::firstOrCreate(
            [
                'email' => $data['user_email'],
                'reseller_id' => $reseller->id,
            ],
            [
                'name' => $displayName,
                'status' => 'pending',
                'region' => $data['region'] ?? null,
            ]
        );

        // 已存在则允许补齐 region（不强制覆盖）
        if (!$vpnUser->region && !empty($data['region'])) {
            $vpnUser->region = $data['region'];
            $vpnUser->save();
        }

        return response()->json([
            'vpn_user' => [
                'id' => $vpnUser->id,
                'email' => $vpnUser->email,
                'reseller_id' => $vpnUser->reseller_id,
                'region' => $vpnUser->region,
                'name' => $vpnUser->name,
                'status' => $vpnUser->status,
            ],
        ]);
    }
}

