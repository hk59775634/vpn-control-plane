<?php

namespace App\Observers;

use App\Models\VpnUser;
use App\Services\FreeradiusSyncService;

class VpnUserObserver
{
    public function saved(VpnUser $vpnUser): void
    {
        app(FreeradiusSyncService::class)->syncVpnUser($vpnUser);
    }

    public function deleted(VpnUser $vpnUser): void
    {
        // On delete, purge credentials
        app(FreeradiusSyncService::class)->syncVpnUser($vpnUser->setAttribute('status', 'deleted'));
    }
}

