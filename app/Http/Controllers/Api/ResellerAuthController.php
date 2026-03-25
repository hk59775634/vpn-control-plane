<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reseller;
use App\Models\ResellerApiKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * 分销商门户：邮箱+密码注册/登录（Sanctum Bearer）
 */
class ResellerAuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:resellers,email',
            'password' => 'required|string|min:8|max:255',
        ]);

        $reseller = Reseller::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'balance_cents' => 0,
            'balance_enforced' => true,
            'status' => 'active',
        ]);

        // 注册后自动生成一把 API Key，方便用户在 B 站填写配置
        $apiKey = ResellerApiKey::create([
            'reseller_id' => $reseller->id,
            'name' => 'default',
        ]);

        $reseller->tokens()->where('name', 'reseller_portal')->delete();
        $token = $reseller->createToken('reseller_portal')->plainTextToken;

        return response()->json([
            'token' => $token,
            'reseller' => $this->resellerPublic($reseller),
            'api_key' => $apiKey->api_key,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $reseller = Reseller::where('email', $data['email'])->first();
        if (!$reseller || !$reseller->password || !Hash::check($data['password'], $reseller->password)) {
            throw ValidationException::withMessages([
                'email' => ['邮箱或密码错误'],
            ]);
        }
        if (($reseller->status ?? 'active') !== 'active') {
            return response()->json(['message' => '账号已停用'], 403);
        }

        $reseller->tokens()->where('name', 'reseller_portal')->delete();
        $token = $reseller->createToken('reseller_portal')->plainTextToken;

        return response()->json([
            'token' => $token,
            'reseller' => $this->resellerPublic($reseller),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user instanceof Reseller) {
            $user->currentAccessToken()?->delete();
        }

        return response()->json(['message' => '已退出']);
    }

    private function resellerPublic(Reseller $r): array
    {
        return [
            'id' => $r->id,
            'name' => $r->name,
            'email' => $r->email,
            'balance_cents' => (int) $r->balance_cents,
            'balance_enforced' => (bool) $r->balance_enforced,
        ];
    }
}
