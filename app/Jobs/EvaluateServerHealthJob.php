<?php

namespace App\Jobs;

use App\Models\Server;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EvaluateServerHealthJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $serverId)
    {
    }

    public function handle(): void
    {
        $server = Server::find($this->serverId);
        if (!$server) {
            return;
        }

        $isFresh = $server->last_seen_at && now()->diffInSeconds($server->last_seen_at) <= 90;
        $status = $isFresh ? 'online' : 'stale';

        if ($server->agent_status !== $status) {
            $server->forceFill(['agent_status' => $status])->save();
        }
    }
}

