<?php

namespace App\Console\Commands;

use App\Models\AssessmentAttemptEvent;
use App\Models\AssessmentPerformanceSample;
use App\Models\SkillTestAiEvaluation;
use App\Models\SkillTestAttemptEvent;
use Illuminate\Console\Command;

class PruneAssessmentTelemetry extends Command
{
    protected $signature = 'assessments:prune-telemetry';
    protected $description = 'Prune non-official Assessment Center telemetry according to retention settings.';

    public function handle(): int
    {
        $performanceDays = max(1, (int) config('assessment.performance_sample_retention_days', 30));
        $eventDays = max(1, (int) config('assessment.attempt_event_retention_days', 365));
        $aiRawDays = max(0, (int) config('assessment.ai_raw_response_retention_days', 0));

        $performance = AssessmentPerformanceSample::where('recorded_at','<',now()->subDays($performanceDays))->delete();
        $writtenEvents = AssessmentAttemptEvent::where('event_at','<',now()->subDays($eventDays))->delete();
        $skillEvents = SkillTestAttemptEvent::where('event_at','<',now()->subDays($eventDays))->delete();

        $aiRaw = 0;
        if ($aiRawDays > 0) {
            $aiRaw = SkillTestAiEvaluation::whereNotNull('raw_response')
                ->where('completed_at','<',now()->subDays($aiRawDays))
                ->update(['raw_response'=>null]);
        }

        $this->info("Pruned {$performance} performance sample(s), {$writtenEvents} written event(s), {$skillEvents} skills event(s), and cleared {$aiRaw} raw AI response(s).");

        return self::SUCCESS;
    }
}
