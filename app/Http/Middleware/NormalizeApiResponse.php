<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 统一 A 站 API 返回格式：
 * - 成功: { success: true, code, message, data }
 * - 失败: { success: false, code, message, data }
 *
 * 说明：仅处理 JsonResponse；文本回调（如易支付 notify）保持原样。
 */
class NormalizeApiResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (!$response instanceof JsonResponse) {
            return $response;
        }

        $status = $response->getStatusCode();
        if ($status === 204) {
            return $response;
        }

        $payload = $response->getData(true);
        if (!is_array($payload)) {
            $payload = ['raw' => $payload];
        }

        // 已是统一结构则不重复包裹
        if (array_key_exists('success', $payload)
            && array_key_exists('code', $payload)
            && array_key_exists('message', $payload)
            && array_key_exists('data', $payload)) {
            return $response;
        }

        if ($status >= 400) {
            $message = (string) ($payload['message'] ?? $payload['error'] ?? '请求失败');
            $wrapped = [
                'success' => false,
                'code' => $this->errorCodeByStatus($status),
                'message' => $message,
                'data' => array_diff_key($payload, array_flip(['message', 'error'])),
            ];

            return response()->json($wrapped, $status);
        }

        $wrapped = [
            'success' => true,
            'code' => 'OK',
            'message' => '操作成功',
            'data' => $payload,
        ];

        return response()->json($wrapped, $status);
    }

    private function errorCodeByStatus(int $status): string
    {
        return match ($status) {
            400 => 'BAD_REQUEST',
            401 => 'UNAUTHENTICATED',
            403 => 'FORBIDDEN',
            404 => 'NOT_FOUND',
            409 => 'CONFLICT',
            422 => 'VALIDATION_FAILED',
            429 => 'TOO_MANY_REQUESTS',
            default => 'HTTP_ERROR',
        };
    }
}

