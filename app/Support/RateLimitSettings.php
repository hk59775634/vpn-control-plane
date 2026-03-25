<?php

namespace App\Support;

use App\Models\SiteSetting;

/**
 * 公开接口限流：每分钟请求数（按 IP + 路径），值存于 site_settings JSON。
 */
final class RateLimitSettings
{
    public const K_RATE_LIMITS = 'stack.rate_limits';

    /** @var array<string, int>|null */
    private static ?array $merged = null;

    public static function resetCache(): void
    {
        self::$merged = null;
    }

    /**
     * @return array<string, int>
     */
    public static function defaults(): array
    {
        return [
            'auth_login' => 30,
            'auth_register' => 10,
            'reseller_validate' => 120,
            'reseller_portal_register' => 10,
            'reseller_portal_login' => 30,
            'epay_notify' => 300,
        ];
    }

    public static function rpm(string $key): int
    {
        if (self::$merged === null) {
            self::$merged = self::defaults();
            try {
                $raw = SiteSetting::getValue(self::K_RATE_LIMITS);
                if ($raw !== null && $raw !== '') {
                    $decoded = json_decode($raw, true);
                    if (is_array($decoded)) {
                        foreach ($decoded as $k => $v) {
                            if (is_string($k) && is_numeric($v)) {
                                self::$merged[$k] = (int) $v;
                            }
                        }
                    }
                }
            } catch (\Throwable) {
                // keep defaults
            }
        }

        $v = self::$merged[$key] ?? self::defaults()[$key] ?? 60;

        return max(1, min(100000, (int) $v));
    }

    /**
     * @return array<string, int>
     */
    public static function allForAdmin(): array
    {
        $out = self::defaults();
        try {
            $raw = SiteSetting::getValue(self::K_RATE_LIMITS);
            if ($raw !== null && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $k => $v) {
                        if (is_string($k) && is_numeric($v)) {
                            $out[$k] = max(1, min(100000, (int) $v));
                        }
                    }
                }
            }
        } catch (\Throwable) {
        }

        return $out;
    }
}
