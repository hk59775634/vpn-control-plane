<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminRole
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user()) {
            return response()->json(['message' => '未登录'], 401);
        }
        if (($request->user()->role ?? '') !== 'admin') {
            return response()->json(['message' => '需要管理员权限'], 403);
        }
        return $next($request);
    }
}
