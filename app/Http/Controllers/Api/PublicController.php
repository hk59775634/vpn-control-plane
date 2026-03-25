<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExitNode;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

/**
 * 公开 API（无需鉴权），供 B 站等拉取可售产品列表
 */
class PublicController extends Controller
{
    /**
     * GET /api/v1/products/public
     * 返回适合对外售卖的产品（id, name, description, price_cents, currency, duration_days）
     */
    public function productsPublic(): JsonResponse
    {
        $list = Product::orderBy('id')
            ->get([
                'id',
                'name',
                'description',
                'price_cents',
                'currency',
                'duration_days',
                'enable_radius',
                'enable_wireguard',
                'bandwidth_limit_kbps',
                'traffic_quota_bytes',
            ]);
        return response()->json($list);
    }

    /**
     * GET /api/v1/regions/public
     * 返回公开可用线路/区域列表（供 B 站下拉选择）
     */
    public function regionsPublic(): JsonResponse
    {
        // 含 WireGuard 与仅 SSL VPN 等产品均需选区域时，列出全部接入区域
        $regions = ExitNode::query()
            ->distinct()
            ->orderBy('region')
            ->pluck('region')
            ->values();

        return response()->json($regions);
    }
}
