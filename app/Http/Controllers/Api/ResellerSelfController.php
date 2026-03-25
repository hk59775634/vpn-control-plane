<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 分销商自助接口（Bearer API Key 鉴权），供 B 站转发或直接调用
 */
class ResellerSelfController extends Controller
{
    private function reseller(Request $request)
    {
        $reseller = $request->attributes->get('reseller');
        if (!$reseller) {
            abort(401, '未登录');
        }
        return $reseller;
    }

    public function me(Request $request): JsonResponse
    {
        $reseller = $this->reseller($request);
        return response()->json([
            'id' => $reseller->id,
            'name' => $reseller->name,
            'balance_cents' => (int) ($reseller->balance_cents ?? 0),
            'balance_enforced' => (bool) ($reseller->balance_enforced ?? false),
        ]);
    }

    public function apiKeys(Request $request): JsonResponse
    {
        $reseller = $this->reseller($request);
        $keys = $reseller->apiKeys()->orderBy('id', 'desc')->get(['id', 'name', 'api_key', 'created_at']);
        $list = $keys->map(function ($k) {
            return [
                'id' => $k->id,
                'name' => $k->name ?: ('Key #' . $k->id),
                'api_key' => $k->api_key,
                'created_at' => $k->created_at?->format('c'),
            ];
        });
        return response()->json($list->values()->all());
    }
}
