<?php

namespace App\Console\Commands;

use App\Services\ReminderService;
use Illuminate\Console\Command;

class ProcessDueRemindersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ops:process-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process all due task, project, and milestone reminders safely';

    public function handle(ReminderService $reminderService): int
    {
        $stats = $reminderService->processDueReminders();

        $this->info(sprintf(
            'Processed: %d | Sent: %d | Deferred (Quiet Hours): %d | Cancelled: %d | Failed: %d',
            $stats['processed'],
            $stats['sent'],
            $stats['deferred'],
            $stats['cancelled'],
            $stats['failed']
        ));

        return self::SUCCESS;
    }
}
