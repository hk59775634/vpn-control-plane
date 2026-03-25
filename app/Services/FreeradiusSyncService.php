<?php

namespace App\Services;

use App\Models\Order;
use App\Models\VpnUser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class FreeradiusSyncService
{
    /**
     * Ensure radcheck reflects current VPN user entitlement.
     *
     * Rules:
     * - Active vpn_user + at least one paid, non-expired order for owning user => ensure radcheck credentials exist.
     * - Otherwise => remove radcheck entries for this vpn_user username.
     */
    public function syncVpnUser(VpnUser $vpnUser): void
    {
        $username = $this->resolveUsername($vpnUser);
        if ($username === null) {
            return;
        }

        $entitled = $this->isEntitledVpnUser($vpnUser) && ($vpnUser->status === 'active');
        if (!$entitled) {
            $this->purgeUsername($username);
            return;
        }

        // Ensure password exists (stored on vpn_users for later display/export).
        if (!$vpnUser->radius_password) {
            $vpnUser->radius_password = $this->generatePassword();
            $vpnUser->saveQuietly();
        }

        $expiresAt = $this->latestEntitlementExpiryForVpnUser($vpnUser);

        DB::transaction(function () use ($username, $vpnUser, $expiresAt) {
            // Clear existing auth checks for this username (we manage these).
            DB::table('radcheck')->where('username', $username)->whereIn('attribute', [
                'Cleartext-Password',
                'Expiration',
            ])->delete();

            DB::table('radcheck')->insert([
                'username' => $username,
                'attribute' => 'Cleartext-Password',
                'op' => ':=',
                'value' => $vpnUser->radius_password,
            ]);

            if ($expiresAt) {
                // FreeRADIUS expects "Expiration" in a readable date format.
                // Example: "17 Mar 2026"
                DB::table('radcheck')->insert([
                    'username' => $username,
                    'attribute' => 'Expiration',
                    'op' => ':=',
                    'value' => $expiresAt->copy()->timezone('UTC')->format('d M Y'),
                ]);
            }
        });

        $this->syncRedisAuthCache($username, (string) $vpnUser->radius_password, $expiresAt);
    }

    public function syncUserId(int $userId): void
    {
        $vpnUsers = VpnUser::query()->where('user_id', $userId)->get();
        foreach ($vpnUsers as $vu) {
            $this->syncVpnUser($vu);
        }
    }

    private function purgeUsername(string $username): void
    {
        DB::table('radcheck')->where('username', $username)->delete();
        DB::table('radreply')->where('username', $username)->delete();
        DB::table('radusergroup')->where('username', $username)->delete();
        $this->purgeRedisAuthCache($username);
    }

    private function syncRedisAuthCache(string $username, string $password, ?Carbon $expiresAt): void
    {
        $enabled = filter_var((string) env('RADIUS_REDIS_AUTH_ENABLED', false), FILTER_VALIDATE_BOOLEAN);
        if (!$enabled) {
            return;
        }
        $key = $this->redisAuthKey($username);
        if ($key === null) {
            return;
        }
        try {
            $conn = Redis::connection(env('RADIUS_REDIS_CONNECTION', 'default'));
            $conn->hMSet($key, [
                'password' => $password,
                'status' => 'active',
                'expires_at' => $expiresAt?->copy()->timezone('UTC')->toIso8601String() ?? '',
                'updated_at' => now()->toIso8601String(),
            ]);
            if ($expiresAt) {
                $ttl = max(60, $expiresAt->getTimestamp() - time());
                $conn->expire($key, $ttl);
            } else {
                $conn->persist($key);
            }
        } catch (\Throwable) {
            // Redis auth cache should not block SQL-based radius sync.
        }
    }

    private function purgeRedisAuthCache(string $username): void
    {
        $enabled = filter_var((string) env('RADIUS_REDIS_AUTH_ENABLED', false), FILTER_VALIDATE_BOOLEAN);
        if (!$enabled) {
            return;
        }
        $key = $this->redisAuthKey($username);
        if ($key === null) {
            return;
        }
        try {
            Redis::connection(env('RADIUS_REDIS_CONNECTION', 'default'))->del($key);
        } catch (\Throwable) {
        }
    }

    private function redisAuthKey(string $username): ?string
    {
        $u = trim($username);
        if ($u === '') {
            return null;
        }
        $prefix = trim((string) env('RADIUS_REDIS_AUTH_PREFIX', 'radius:auth:user:'));
        if ($prefix === '') {
            $prefix = 'radius:auth:user:';
        }

        return $prefix.$u;
    }

    private function resolveUsername(VpnUser $vpnUser): ?string
    {
        // 已显式设置（含分销商开通后写入的 user@resellerId）
        if (filled($vpnUser->radius_username)) {
            $u = trim((string) $vpnUser->radius_username);
            if ($u === '') {
                return null;
            }
            if (mb_strlen($u) > 64) {
                $u = mb_substr($u, 0, 64);
            }
            if ($vpnUser->radius_username !== $u) {
                $vpnUser->radius_username = $u;
                $vpnUser->saveQuietly();
            }

            return $u;
        }

        // 分销商 B 站创建的 vpn_users：未设置 radius_username 时不要用 name 占位。
        // 否则会在 ResellerProvision 写入 SSL 账号之前被 observer 写成邮箱前缀（如 demo123），导致开通逻辑跳过 B 站传入的用户名密码。
        if ($vpnUser->reseller_id) {
            return null;
        }

        $u = trim((string) $vpnUser->name);
        if ($u === '') {
            return null;
        }
        if (mb_strlen($u) > 64) {
            $u = mb_substr($u, 0, 64);
        }
        if (!$vpnUser->radius_username || $vpnUser->radius_username !== $u) {
            $vpnUser->radius_username = $u;
            $vpnUser->saveQuietly();
        }

        return $u;
    }

    private function isEntitledVpnUser(VpnUser $vpnUser): bool
    {
        $now = now();
        $q = Order::query()
            ->whereIn('status', ['paid', 'active'])
            ->where('expires_at', '>', $now);

        // 新模型：订单关联 vpn_user_id
        if ($vpnUser->id) {
            $q2 = (clone $q)->where('vpn_user_id', $vpnUser->id);
            if ($q2->exists()) {
                return true;
            }
        }

        // 兼容旧数据：订单仍关联 user_id
        if ($vpnUser->user_id) {
            return (clone $q)->where('user_id', $vpnUser->user_id)->exists();
        }

        return false;
    }

    private function latestEntitlementExpiryForVpnUser(VpnUser $vpnUser): ?Carbon
    {
        $q = Order::query()
            ->whereIn('status', ['paid', 'active'])
            ->orderByDesc('expires_at');

        $v = null;
        if ($vpnUser->id) {
            $v = (clone $q)->where('vpn_user_id', $vpnUser->id)->value('expires_at');
        }
        if (!$v && $vpnUser->user_id) {
            $v = (clone $q)->where('user_id', $vpnUser->user_id)->value('expires_at');
        }
        return $v;
    }

    private function generatePassword(): string
    {
        // Avoid ambiguous characters; radius passwords are usually used manually.
        return Str::password(16, symbols: false);
    }
}

