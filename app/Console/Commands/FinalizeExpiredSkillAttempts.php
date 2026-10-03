<?php

namespace App\Console\Commands;

use App\Jobs\ScoreSkillTestSubmission;
use App\Models\SkillTestAttempt;
use App\Models\SkillTestSubmission;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FinalizeExpiredSkillAttempts extends Command
{
    protected $signature = 'assessments:finalize-expired-skills {--limit=500}';
    protected $description = 'Finalize expired in-progress skills test attempts.';

    public function handle(): int
    {
        $limit = max(1, min(5000, (int) $this->option('limit')));
        $processed = 0;
        $aiAttemptIds = [];

        SkillTestAttempt::query()
            ->where('status', 1)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->each(function ($attemptId) use (&$processed, &$aiAttemptIds) {
                DB::transaction(function () use ($attemptId, &$processed, &$aiAttemptIds) {
                    $attempt = SkillTestAttempt::with('skillTest')
                        ->whereKey($attemptId)
                        ->lockForUpdate()
                        ->first();

                    if (!$attempt
                        || (int) $attempt->status !== 1
                        || !$attempt->expires_at
                        || now()->lt($attempt->expires_at)) {
                        return;
                    }

                    $latest = SkillTestSubmission::where('skill_test_attempt_id', $attempt->id)
                        ->orderByDesc('version')
                        ->first();

                    if (!$latest) {
                        $latest = SkillTestSubmission::create([
                            'skill_test_attempt_id'=>$attempt->id,
                            'version'=>1,
                        ]);
                    }

                    SkillTestSubmission::where('skill_test_attempt_id', $attempt->id)
                        ->update(['is_final'=>false]);

                    $latest->update([
                        'is_final'=>true,
                        'submitted_at'=>now(),
                    ]);

                    $attempt->update([
                        'status'=>2,
                        'submitted_at'=>now(),
                    ]);

                    if ($attempt->skillTest?->ai_scoring) {
                        $aiAttemptIds[] = $attempt->id;
                    }

                    $processed++;
                });
            });

        foreach (array_unique($aiAttemptIds) as $attemptId) {
            ScoreSkillTestSubmission::dispatch($attemptId);
        }

        $this->info("Finalized {$processed} expired skills attempt(s).");

        return self::SUCCESS;
    }
}
