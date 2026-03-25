<?php

namespace App\Http\Middleware;

use App\Models\ResellerApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 校验 Bearer 中的 API Key，将当前分销商注入 request（供 A 站分销商自助接口及 B 站转发使用）
 */
class EnsureResellerApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        if (!$token) {
            return response()->json(['message' => '缺少 API Key'], 401);
        }
        $key = ResellerApiKey::where('api_key', $token)->with('reseller')->first();
        if (!$key || !$key->reseller) {
            return response()->json(['message' => 'API Key 无效或已失效'], 401);
        }
        if (($key->reseller->status ?? 'active') !== 'active') {
            return response()->json(['message' => '分销商账号已停用'], 403);
        }
        $request->attributes->set('reseller', $key->reseller);
        $request->attributes->set('reseller_api_key', $key);
        return $next($request);
    }
}
