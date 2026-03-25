<?php

namespace App\Support;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * 可选 Redis：仅当 .env 中显式填写 REDIS_URL 或 REDIS_HOST，且连接校验通过时，
 * 将 cache / session / queue 切到 redis；否则不启用（未填写则完全遵循 .env 其余项；
 * 已填写但连不上则回退为 database，避免应用因 Redis 不可用而崩溃）。
 */
final class RuntimeStackConfig
{
    /** 探针 key 前缀（与业务 key 区分；仍会叠加 Laravel redis.options.prefix） */
    private const HEALTH_KEY_PREFIX = 'vpn_stack:health:';

    private static ?bool $pingCache = null;

    public static function resetPingCache(): void
    {
        self::$pingCache = null;
    }

    /** .env 中是否显式出现 REDIS_URL 或 REDIS_HOST（见 config/database.php env_redis，无默认值）。 */
    public static function redisExplicitlyConfiguredInEnv(): bool
    {
        $url = config('database.env_redis.url');
        if (is_string($url) && trim($url) !== '') {
            return true;
        }

        $host = config('database.env_redis.host');

        return $host !== null && trim((string) $host) !== '';
    }

    /**
     * 已显式配置且链路可用：PING + 带前缀 SET（EX）+ DEL（同一请求内缓存结果）。
     */
    public static function redisPingSucceeded(): bool
    {
        if (self::$pingCache !== null) {
            return self::$pingCache;
        }

        if (!self::redisExplicitlyConfiguredInEnv()) {
            return self::$pingCache = false;
        }

        try {
            self::verifyRedisWithPingAndSetDel();

            return self::$pingCache = true;
        } catch (\Throwable $e) {
            Log::debug('Redis health check failed', ['exception' => $e->getMessage()]);

            return self::$pingCache = false;
        }
    }

    /**
     * PING 后写入再删除带前缀的探针 key，验证可写、可删（失败则抛异常）。
     */
    private static function verifyRedisWithPingAndSetDel(): void
    {
        $conn = Redis::connection();
        $conn->ping();

        $key = self::HEALTH_KEY_PREFIX.bin2hex(random_bytes(8));
        $conn->set($key, '1', 'EX', 5);
        $conn->del($key);
    }

    public static function apply(): void
    {
        if (!self::redisExplicitlyConfiguredInEnv()) {
            return;
        }

        if (!self::redisPingSucceeded()) {
            Log::warning('Redis 已在 .env 中配置但连接失败，cache/session/queue 将回退为 database');
            Config::set('cache.default', 'database');
            Config::set('session.driver', 'database');
            Config::set('queue.default', 'database');

            return;
        }

        Config::set('cache.default', 'redis');
        Config::set('session.driver', 'redis');
        Config::set('queue.default', 'redis');
    }
}
