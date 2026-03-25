<?php

namespace App\Support;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Crypt;

/**
 * 易支付配置：优先读取管理后台「支付设置」入库项，否则回退 config/epay.php（.env）
 */
class PaymentConfig
{
    public const K_ENABLED = 'epay.enabled';

    public const K_GATEWAY = 'epay.gateway';

    public const K_PID = 'epay.pid';

    public const K_KEY = 'epay.key';

    public const K_NOTIFY_URL = 'epay.notify_url';

    public const K_RETURN_URL = 'epay.return_url';

    public const K_ALLOW_SIMULATED = 'epay.allow_simulated_recharge';

    public static function enabled(): bool
    {
        $db = SiteSetting::getValue(self::K_ENABLED);
        if ($db !== null) {
            return $db === '1' || $db === 'true';
        }

        return (bool) config('epay.enabled');
    }

    public static function allowSimulatedRecharge(): bool
    {
        $db = SiteSetting::getValue(self::K_ALLOW_SIMULATED);
        if ($db !== null) {
            return $db === '1' || $db === 'true';
        }

        return (bool) config('epay.allow_simulated_recharge');
    }

    public static function gateway(): string
    {
        $db = SiteSetting::getValue(self::K_GATEWAY);
        if ($db !== null && trim($db) !== '') {
            return rtrim(trim($db), '/');
        }

        return rtrim((string) config('epay.gateway', ''), '/');
    }

    public static function pid(): string
    {
        $db = SiteSetting::getValue(self::K_PID);
        if ($db !== null && trim($db) !== '') {
            return trim($db);
        }

        return trim((string) config('epay.pid', ''));
    }

    public static function key(): string
    {
        $db = SiteSetting::getValue(self::K_KEY);
        if ($db !== null && $db !== '') {
            try {
                return Crypt::decryptString($db);
            } catch (\Throwable) {
                return $db;
            }
        }

        return (string) config('epay.key', '');
    }

    public static function notifyUrl(): string
    {
        $db = SiteSetting::getValue(self::K_NOTIFY_URL);
        if ($db !== null && trim($db) !== '') {
            return trim($db);
        }
        $env = config('epay.notify_url');
        if (is_string($env) && trim($env) !== '') {
            return trim($env);
        }

        return rtrim((string) config('app.url'), '/').'/api/v1/payments/epay/notify';
    }

    public static function returnUrl(): string
    {
        $db = SiteSetting::getValue(self::K_RETURN_URL);
        if ($db !== null && trim($db) !== '') {
            return trim($db);
        }
        $env = config('epay.return_url');
        if (is_string($env) && trim($env) !== '') {
            return trim($env);
        }

        return rtrim((string) config('app.url'), '/').'/reseller?pay_return=1';
    }

    public static function keyIsSetInDatabase(): bool
    {
        $db = SiteSetting::getValue(self::K_KEY);

        return $db !== null && $db !== '';
    }
}
