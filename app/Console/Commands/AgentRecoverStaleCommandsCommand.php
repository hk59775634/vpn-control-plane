<?php

namespace App\Console\Commands;

use App\Jobs\RecoverStaleAgentCommandsJob;
use Illuminate\Console\Command;

class AgentRecoverStaleCommandsCommand extends Command
{
    protected $signature = 'agent:recover-stale-commands';
    protected $description = 'Requeue stale dispatched agent commands';

    public function handle(): int
    {
        RecoverStaleAgentCommandsJob::dispatchSync();
        $this->info('stale agent commands recovered');
        return self::SUCCESS;
    }
}

