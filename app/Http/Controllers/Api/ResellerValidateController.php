<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ResellerApiKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 供 B 站等外部系统调用：校验 API Key 并返回分销商信息（不要求管理员登录）
 */
class ResellerValidateController extends Controller
{
    /**
     * POST /api/v1/reseller/validate
     * 请求体: { "api_key": "rk_xxx" }
     * 成功: 200 { "reseller": { "id", "name" } }
     * 失败: 401 { "message": "..." }
     */
    public function validate(Request $request): JsonResponse
    {
        $request->validate([
            'api_key' => 'required|string',
        ]);
        $key = ResellerApiKey::where('api_key', $request->input('api_key'))->with('reseller')->first();
        if (!$key || !$key->reseller) {
            return response()->json(['message' => 'API Key 无效或已失效'], 401);
        }
        return response()->json([
            'reseller' => [
                'id' => $key->reseller->id,
                'name' => $key->reseller->name,
            ],
        ]);
    }
}
