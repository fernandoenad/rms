<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class AssessmentHealthHeartbeat extends Command
{
    protected $signature = 'assessments:health-heartbeat';
    protected $description = 'Record a scheduler heartbeat for Assessment Center health monitoring.';

    public function handle(): int
    {
        Cache::put('assessment-center:scheduler-heartbeat', now()->toIso8601String(), now()->addMinutes(10));
        return self::SUCCESS;
    }
}
