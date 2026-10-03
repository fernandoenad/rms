<?php

namespace App\Console\Commands;

use App\Models\AssessmentGroup;
use App\Models\SkillTest;
use App\Services\AssessmentScoreSyncService;
use Illuminate\Console\Command;

class SyncOfficialAssessmentScores extends Command
{
    protected $signature = 'assessments:sync-official-scores';
    protected $description = 'Synchronize released Written and Skills scores into applicant assessment records.';

    public function handle(AssessmentScoreSyncService $sync): int
    {
        $writtenSynced = 0;
        $skillsSynced = 0;

        AssessmentGroup::query()
            ->whereNotNull('assessment_score_key')
            ->where('score_release_policy','after_close')
            ->whereNull('scores_synced_at')
            ->orderBy('id')
            ->chunkById(50, function ($groups) use ($sync, &$writtenSynced) {
                foreach ($groups as $group) {
                    if (!$group->scoresAreReleased()) {
                        continue;
                    }

                    $result = $sync->syncWrittenGroup($group);
                    $writtenSynced += (int) $result['synced'];
                }
            });

        SkillTest::query()
            ->whereNotNull('assessment_score_key')
            ->where('score_release_policy','after_close')
            ->whereNull('scores_synced_at')
            ->orderBy('id')
            ->chunkById(50, function ($tests) use ($sync, &$skillsSynced) {
                foreach ($tests as $test) {
                    if (!$test->scoresAreReleased()) {
                        continue;
                    }

                    $result = $sync->syncSkillTest($test);
                    $skillsSynced += (int) $result['synced'];
                }
            });

        $this->info("Synchronized {$writtenSynced} Written score(s) and {$skillsSynced} Skills score(s).");

        return self::SUCCESS;
    }
}
