<?php

namespace App\Console\Commands;

use App\Models\AssessmentAttemptEvent;
use App\Models\ExamAttempt;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\AssessmentScoreSyncService;
use App\Services\WrittenAttemptScoringService;

class FinalizeExpiredWrittenAttempts extends Command
{
    protected $signature = 'assessments:finalize-expired {--limit=500}';
    protected $description = 'Finalize expired in-progress written assessment attempts.';

    public function handle(AssessmentScoreSyncService $scoreSync, WrittenAttemptScoringService $writtenScoring): int
    {
        $limit = max(1, min(5000, (int) $this->option('limit')));
        $processed = 0;

        ExamAttempt::query()
            ->where('status', 1)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->each(function ($attemptId) use (&$processed, $scoreSync, $writtenScoring) {
                $finalized = DB::transaction(function () use ($attemptId, &$processed, $writtenScoring) {
                    $attempt = ExamAttempt::whereKey($attemptId)->lockForUpdate()->first();

                    if (!$attempt || (int) $attempt->status !== 1 || !$attempt->expires_at || now()->lt($attempt->expires_at)) {
                        return;
                    }

                    $score = $writtenScoring->calculate($attempt);

                    $attempt->update([
                        'ended_at' => now(),
                        'status' => 2,
                        'auto_submitted' => true,
                        'auto_submit_reason' => 'timeout',
                        'correct_answers' => $score['correct_answers'],
                        'total_items' => $score['total_items'],
                        'percentage' => $score['percentage'],
                        'scored_at' => now(),
                    ]);

                    AssessmentAttemptEvent::create([
                        'exam_attempt_id' => $attempt->id,
                        'event_type' => 'timeout',
                        'event_at' => now(),
                        'metadata' => [
                            'source' => 'scheduled_finalizer',
                            'correct_answers' => $score['correct_answers'],
                            'total_items' => $score['total_items'],
                            'answered_items' => $score['answered_items'],
                        ],
                    ]);

                    $processed++;
                    return $attempt->fresh();
                });

                if ($finalized) {
                    $scoreSync->syncWrittenAttempt($finalized);
                }
            });

        $this->info("Finalized {$processed} expired attempt(s).");

        return self::SUCCESS;
    }
}
