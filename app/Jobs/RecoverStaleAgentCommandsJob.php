<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class RecoverStaleAgentCommandsJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $cutoff = now()->subMinutes(2);

        DB::table('agent_commands')
            ->where('status', 'dispatched')
            ->whereNotNull('dispatched_at')
            ->where('dispatched_at', '<', $cutoff)
            ->update([
                'status' => 'pending',
                'updated_at' => now(),
            ]);
    }
}

