<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * POST /api/v1/auth/login
     * 请求体: { "email": "...", "password": "..." }
     * 返回: { "token": "...", "role": "admin|user", "email": "..." }
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => '邮箱或密码错误'], 401);
        }

        $user->tokens()->where('name', 'admin')->delete();
        $token = $user->createToken('admin')->plainTextToken;
        $role = $user->role ?? 'user';

        return response()->json([
            'token' => $token,
            'role' => $role,
            'email' => $user->email,
        ]);
    }

    /**
     * POST /api/v1/auth/register
     */
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|string|email|unique:users,email',
            'password' => 'required|string|min:6',
            'name' => 'nullable|string|max:255',
        ]);

        $user = User::create([
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'name' => $request->name ?? $request->email,
            'role' => 'user',
        ]);

        $token = $user->createToken('admin')->plainTextToken;

        return response()->json([
            'token' => $token,
            'role' => $user->role,
            'email' => $user->email,
        ], 201);
    }

    /**
     * POST /api/v1/auth/logout
     * 撤销当前 Sanctum 令牌（与 ResellerAuthController::logout 一致）
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user instanceof User) {
            $user->currentAccessToken()?->delete();
        }

        return response()->json(['message' => '已退出']);
    }

    /**
     * PATCH /api/v1/auth/password
     * Laravel 惯例：current_password、password + password_confirmation（confirmed 规则）
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
        ]);

        $user = $request->user();
        if (!$user instanceof User) {
            abort(401, '未登录');
        }

        if (!$user->password || !Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['当前密码不正确'],
            ]);
        }

        $currentToken = $user->currentAccessToken();
        $user->password = $data['password'];
        $user->save();

        if ($currentToken) {
            $user->tokens()
                ->where('name', 'admin')
                ->where('id', '!=', $currentToken->id)
                ->delete();
        }

        return response()->json(['message' => '密码已更新']);
    }
}
