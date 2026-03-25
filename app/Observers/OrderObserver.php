<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\FreeradiusSyncService;

class OrderObserver
{
    public function saved(Order $order): void
    {
        if ($order->vpn_user_id) {
            $vpnUserId = (int) $order->vpn_user_id;
            $vpnUser = \App\Models\VpnUser::find($vpnUserId);
            if ($vpnUser) {
                app(FreeradiusSyncService::class)->syncVpnUser($vpnUser);
            }
            return;
        }

        app(FreeradiusSyncService::class)->syncUserId((int) $order->user_id);
    }

    public function deleted(Order $order): void
    {
        if ($order->vpn_user_id) {
            $vpnUserId = (int) $order->vpn_user_id;
            $vpnUser = \App\Models\VpnUser::find($vpnUserId);
            if ($vpnUser) {
                app(FreeradiusSyncService::class)->syncVpnUser($vpnUser);
            }
            return;
        }

        app(FreeradiusSyncService::class)->syncUserId((int) $order->user_id);
    }
}

