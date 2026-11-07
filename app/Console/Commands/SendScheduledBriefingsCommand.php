<?php

namespace App\Console\Commands;

use App\Services\BriefingService;
use Illuminate\Console\Command;

class SendScheduledBriefingsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ops:send-briefings';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch scheduled Daily Briefings and Weekly Digests in each user timezone';

    public function handle(BriefingService $briefingService): int
    {
        $stats = $briefingService->dispatchScheduledBriefings();

        $this->info("Dispatched {$stats['daily_sent']} daily briefing(s) and {$stats['weekly_sent']} weekly digest(s).");

        return self::SUCCESS;
    }
}
