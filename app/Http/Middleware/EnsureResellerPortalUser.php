<?php

namespace App\Http\Middleware;

use App\Models\Reseller;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 需已通过 auth:sanctum；且当前 token 对应 Reseller，且账号未停用。
 */
class EnsureResellerPortalUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user instanceof Reseller) {
            return response()->json(['message' => '请使用分销商门户账号登录'], 403);
        }
        if (($user->status ?? 'active') !== 'active') {
            return response()->json(['message' => '账号已停用'], 403);
        }

        return $next($request);
    }
}
