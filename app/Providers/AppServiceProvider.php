<?php

namespace App\Providers;

use App\Models\Order;
use App\Models\VpnUser;
use App\Observers\OrderObserver;
use App\Observers\VpnUserObserver;
use App\Support\RateLimitSettings;
use App\Support\RuntimeStackConfig;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        try {
            RuntimeStackConfig::apply();
        } catch (\Throwable) {
        }

        $throttleKey = static function (Request $request): string {
            return $request->ip().'|'.$request->path();
        };

        RateLimiter::for('a-auth-login', fn (Request $r) => Limit::perMinute(RateLimitSettings::rpm('auth_login'))->by($throttleKey($r)));
        RateLimiter::for('a-auth-register', fn (Request $r) => Limit::perMinute(RateLimitSettings::rpm('auth_register'))->by($throttleKey($r)));
        RateLimiter::for('a-reseller-validate', fn (Request $r) => Limit::perMinute(RateLimitSettings::rpm('reseller_validate'))->by($throttleKey($r)));
        RateLimiter::for('a-reseller-portal-register', fn (Request $r) => Limit::perMinute(RateLimitSettings::rpm('reseller_portal_register'))->by($throttleKey($r)));
        RateLimiter::for('a-reseller-portal-login', fn (Request $r) => Limit::perMinute(RateLimitSettings::rpm('reseller_portal_login'))->by($throttleKey($r)));
        RateLimiter::for('a-epay-notify', fn (Request $r) => Limit::perMinute(RateLimitSettings::rpm('epay_notify'))->by($throttleKey($r)));

        VpnUser::observe(VpnUserObserver::class);
        Order::observe(OrderObserver::class);
    }
}
