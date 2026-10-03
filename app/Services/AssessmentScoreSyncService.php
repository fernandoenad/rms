<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Assessment;
use App\Models\AssessmentGroup;
use App\Models\ExamAttempt;
use App\Models\SkillTest;
use App\Models\SkillTestGroup;
use App\Models\SkillTestAttempt;
use App\Models\Template;
use App\Models\Vacancy;
use Illuminate\Support\Facades\DB;

class AssessmentScoreSyncService
{
    public function criteriaForVacancy(Vacancy $vacancy): array
    {
        if (!$vacancy->template_id) {
            return [];
        }

        $template = Template::find($vacancy->template_id);
        $criteria = json_decode((string) optional($template)->template, true);

        if (!is_array($criteria)) {
            return [];
        }

        return collect($criteria)
            ->filter(fn ($max, $key) => is_string($key) && trim($key) !== '' && is_numeric($max) && (float) $max > 0)
            ->map(fn ($max) => (float) $max)
            ->all();
    }

    public function criterionExistsForVacancy(int $vacancyId, ?string $key): bool
    {
        if (!$key) {
            return true;
        }

        $vacancy = Vacancy::find($vacancyId);
        if (!$vacancy) {
            return false;
        }

        return array_key_exists($key, $this->criteriaForVacancy($vacancy));
    }

    public function syncWrittenGroup(AssessmentGroup $group): array
    {
        if (!$group->assessment_score_key || !$group->scoresAreReleased()) {
            return ['synced'=>0, 'skipped'=>0];
        }

        $synced = 0;
        $skipped = 0;

        ExamAttempt::query()
            ->whereHas('exam', fn ($q) => $q->where('assessment_group_id', $group->id))
            ->where('status', 2)
            ->whereNotNull('percentage')
            ->orderBy('id')
            ->chunkById(250, function ($attempts) use ($group, &$synced, &$skipped) {
                foreach ($attempts as $attempt) {
                    $ok = $this->syncApplicationCriterion(
                        (int) $attempt->application_id,
                        (int) $group->vacancy_id,
                        (string) $group->assessment_score_key,
                        (float) $attempt->percentage
                    );

                    $ok ? $synced++ : $skipped++;
                }
            });

        $group->forceFill(['scores_synced_at'=>$skipped === 0 ? now() : null])->save();

        return compact('synced','skipped');
    }

    public function syncWrittenAttempt(ExamAttempt $attempt): bool
    {
        if ((int) $attempt->status !== 2 || $attempt->percentage === null) {
            return false;
        }

        $attempt->loadMissing('exam.assessmentGroup');
        $exam = $attempt->exam;
        $group = $exam?->assessmentGroup;

        if ($group) {
            if (!$group->assessment_score_key || !$group->scoresAreReleased()) {
                return false;
            }

            return $this->syncApplicationCriterion(
                (int) $attempt->application_id,
                (int) $group->vacancy_id,
                (string) $group->assessment_score_key,
                (float) $attempt->percentage
            );
        }

        // Standalone Written Tests do not have a group release policy.
        // When explicitly mapped, their submitted score is treated as official
        // and synchronized immediately.
        if (!$exam || !$exam->assessment_score_key) {
            return false;
        }

        return $this->syncApplicationCriterion(
            (int) $attempt->application_id,
            (int) $exam->vacancy_id,
            (string) $exam->assessment_score_key,
            (float) $attempt->percentage
        );
    }

    public function syncSkillGroup(SkillTestGroup $group): array
    {
        if (!$group->assessment_score_key || !$group->scoresAreReleased()) {
            return ['synced'=>0,'skipped'=>0];
        }

        $setIds = $group->skillTests()->pluck('id');
        $synced = 0;
        $skipped = 0;

        SkillTestAttempt::query()
            ->whereIn('skill_test_id',$setIds)
            ->where('status',2)
            ->whereNotNull('final_score')
            ->orderBy('id')
            ->chunkById(250, function ($attempts) use ($group,&$synced,&$skipped) {
                foreach ($attempts as $attempt) {
                    $ok = $this->syncApplicationCriterion(
                        (int)$attempt->application_id,
                        (int)$group->vacancy_id,
                        (string)$group->assessment_score_key,
                        (float)$attempt->final_score
                    );
                    $ok ? $synced++ : $skipped++;
                }
            });

        $group->forceFill(['scores_synced_at'=>$skipped === 0 ? now() : null])->save();

        return compact('synced','skipped');
    }

    public function syncSkillTest(SkillTest $test): array
    {
        $test->loadMissing('skillTestGroup');

        if ($test->skillTestGroup) {
            return $this->syncSkillGroup($test->skillTestGroup);
        }

        if (!$test->assessment_score_key || !$test->scoresAreReleased()) {
            return ['synced'=>0, 'skipped'=>0];
        }

        $synced = 0;
        $skipped = 0;

        SkillTestAttempt::query()
            ->where('skill_test_id', $test->id)
            ->where('status', 2)
            ->whereNotNull('final_score')
            ->orderBy('id')
            ->chunkById(250, function ($attempts) use ($test, &$synced, &$skipped) {
                foreach ($attempts as $attempt) {
                    $ok = $this->syncApplicationCriterion(
                        (int) $attempt->application_id,
                        (int) $test->vacancy_id,
                        (string) $test->assessment_score_key,
                        (float) $attempt->final_score
                    );

                    $ok ? $synced++ : $skipped++;
                }
            });

        $test->forceFill(['scores_synced_at'=>$skipped === 0 ? now() : null])->save();

        return compact('synced','skipped');
    }

    public function syncSkillAttempt(SkillTestAttempt $attempt): bool
    {
        if ((int) $attempt->status !== 2 || $attempt->final_score === null) {
            return false;
        }

        $attempt->loadMissing('skillTest.skillTestGroup');
        $test = $attempt->skillTest;
        if (!$test || !$test->scoresAreReleased()) {
            return false;
        }

        $criterion = $test->skillTestGroup?->assessment_score_key ?: $test->assessment_score_key;
        $vacancyId = $test->skillTestGroup?->vacancy_id ?: $test->vacancy_id;

        if (!$criterion) {
            return false;
        }

        return $this->syncApplicationCriterion(
            (int) $attempt->application_id,
            (int) $vacancyId,
            (string) $criterion,
            (float) $attempt->final_score
        );
    }

    protected function syncApplicationCriterion(
        int $applicationId,
        int $vacancyId,
        string $criterionKey,
        float $scoreOutOf100
    ): bool {
        return DB::transaction(function () use ($applicationId, $vacancyId, $criterionKey, $scoreOutOf100) {
            $application = Application::whereKey($applicationId)
                ->where('vacancy_id', $vacancyId)
                ->first();

            if (!$application) {
                return false;
            }

            $vacancy = Vacancy::find($vacancyId);
            if (!$vacancy) {
                return false;
            }

            $criteria = $this->criteriaForVacancy($vacancy);
            if (!array_key_exists($criterionKey, $criteria)) {
                return false;
            }

            $assessment = Assessment::where('application_id', $applicationId)
                ->lockForUpdate()
                ->first();

            if (!$assessment) {
                return false;
            }

            $scores = json_decode((string) $assessment->assessment, true);
            $scores = is_array($scores) ? $scores : [];

            $maxPoints = (float) $criteria[$criterionKey];
            $normalized = max(0.0, min(100.0, $scoreOutOf100));
            $scaled = round(($normalized / 100.0) * $maxPoints, 2);

            $scores[$criterionKey] = $scaled;

            $total = 0.0;
            foreach ($criteria as $key => $max) {
                if (isset($scores[$key]) && is_numeric($scores[$key])) {
                    $total += (float) $scores[$key];
                }
            }

            $assessment->update([
                'assessment'=>json_encode($scores, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'score'=>round($total, 2),
            ]);

            return true;
        });
    }
}
