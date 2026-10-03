<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('assessments:health-heartbeat')
            ->everyMinute()
            ->withoutOverlapping();
        $schedule->command('assessments:finalize-expired --limit=1000')
            ->everyMinute()
            ->withoutOverlapping();

        $schedule->command('assessments:finalize-expired-skills --limit=1000')
            ->everyMinute()
            ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
