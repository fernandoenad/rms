<?php

namespace App\Console\Commands;

use App\Models\AssessmentAttemptEvent;
use App\Models\ExamAttempt;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FinalizeExpiredWrittenAttempts extends Command
{
    protected $signature = 'assessments:finalize-expired {--limit=500}';
    protected $description = 'Finalize expired in-progress written assessment attempts.';

    public function handle(): int
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
            ->each(function ($attemptId) use (&$processed) {
                DB::transaction(function () use ($attemptId, &$processed) {
                    $attempt = ExamAttempt::whereKey($attemptId)->lockForUpdate()->first();

                    if (!$attempt || (int) $attempt->status !== 1 || !$attempt->expires_at || now()->lt($attempt->expires_at)) {
                        return;
                    }

                    $items = DB::table('written_exams')
                        ->where('exam_id', $attempt->exam_id)
                        ->where('status', 1)
                        ->pluck('id');

                    $total = $items->count();

                    $correct = DB::table('exam_attempt_answers as a')
                        ->join('written_exam_options as o', 'o.id', '=', 'a.selected_option_id')
                        ->where('a.exam_attempt_id', $attempt->id)
                        ->whereIn('a.written_exam_id', $items)
                        ->where('o.is_correct', 1)
                        ->count();

                    $answered = DB::table('exam_attempt_answers')
                        ->where('exam_attempt_id', $attempt->id)
                        ->whereIn('written_exam_id', $items)
                        ->whereNotNull('selected_option_id')
                        ->count();

                    $percentage = $total > 0 ? round(($correct / $total) * 100, 2) : 0;

                    $attempt->update([
                        'ended_at' => now(),
                        'status' => 2,
                        'auto_submitted' => true,
                        'auto_submit_reason' => 'timeout',
                        'correct_answers' => $correct,
                        'total_items' => $total,
                        'percentage' => $percentage,
                        'scored_at' => now(),
                    ]);

                    AssessmentAttemptEvent::create([
                        'exam_attempt_id' => $attempt->id,
                        'event_type' => 'timeout',
                        'event_at' => now(),
                        'metadata' => [
                            'source' => 'scheduled_finalizer',
                            'correct_answers' => $correct,
                            'total_items' => $total,
                            'answered_items' => $answered,
                        ],
                    ]);

                    $processed++;
                });
            });

        $this->info("Finalized {$processed} expired attempt(s).");

        return self::SUCCESS;
    }
}
