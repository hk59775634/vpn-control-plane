<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Support\RateLimitSettings;
use App\Support\RuntimeStackConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class AdminRuntimeSettingsController extends Controller
{
    public function show(): JsonResponse
    {
        $envOn = RuntimeStackConfig::redisExplicitlyConfiguredInEnv();
        $pingOk = $envOn && RuntimeStackConfig::redisPingSucceeded();

        return response()->json([
            'redis_env_configured' => $envOn,
            'redis_connection_ok' => $pingOk,
            'rate_limits' => RateLimitSettings::allForAdmin(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $defaults = RateLimitSettings::defaults();
        $rules = [
            'rate_limits' => ['nullable', 'array'],
        ];
        foreach (array_keys($defaults) as $key) {
            $rules['rate_limits.'.$key] = ['nullable', 'integer', 'min:1', 'max:100000'];
        }

        $data = $request->validate($rules);

        $limits = $data['rate_limits'] ?? [];
        if (!is_array($limits)) {
            $limits = [];
        }
        $out = [];
        foreach ($defaults as $k => $def) {
            if (array_key_exists($k, $limits) && $limits[$k] !== null && $limits[$k] !== '') {
                $out[$k] = max(1, min(100000, (int) $limits[$k]));
            } else {
                $out[$k] = $def;
            }
        }
        SiteSetting::setValue(RateLimitSettings::K_RATE_LIMITS, json_encode($out, JSON_UNESCAPED_UNICODE));

        RateLimitSettings::resetCache();

        try {
            Artisan::call('config:clear');
        } catch (\Throwable) {
        }

        return $this->show();
    }
}
