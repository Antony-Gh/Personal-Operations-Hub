<?php

namespace App\Console\Commands;

use App\Services\RecurringTaskService;
use Illuminate\Console\Command;

class ProcessRecurringTasksCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ops:process-recurring';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ensure completed recurring tasks have generated their next scheduled occurrence';

    public function handle(RecurringTaskService $recurringTaskService): int
    {
        $generated = $recurringTaskService->processPendingCompletedRecurringTasks();

        $this->info("Generated {$generated} recurring task occurrence(s).");

        return self::SUCCESS;
    }
}
