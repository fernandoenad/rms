<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\ExamAttempt;
use App\Models\Inquiry;
use App\Models\SkillTestAttempt;
use Illuminate\Support\Collection;

class AssessmentIntegrityService
{
    public function __construct(
        protected AssessmentScoreSyncService $scoreSync
    ) {
    }

    public function anomalies(?string $type = null, ?string $search = null): Collection
    {
        $candidates = collect();

        if (!$type || $type === 'written') {
            ExamAttempt::query()
                ->with([
                    'application:id,vacancy_id,application_code,first_name,middle_name,last_name',
                    'exam:id,vacancy_id,assessment_group_id,title,set_code,assessment_score_key',
                    'exam.assessmentGroup:id,vacancy_id,title,assessment_score_key,score_release_policy,scores_released_at',
                    'exam.vacancy:id,position_title,template_id',
                    'exam.assessmentGroup.vacancy:id,position_title,template_id',
                ])
                ->where('status', 2)
                ->whereNotNull('percentage')
                ->orderBy('id')
                ->chunkById(250, function ($attempts) use ($candidates) {
                    foreach ($attempts as $attempt) {
                        $exam = $attempt->exam;
                        if (!$exam || !$attempt->application) {
                            continue;
                        }

                        $group = $exam->assessmentGroup;
                        if ($group) {
                            if (!$group->assessment_score_key || !$group->scoresAreReleased()) {
                                continue;
                            }
                            $criterion = (string) $group->assessment_score_key;
                            $vacancy = $group->vacancy;
                            $title = $group->title;
                            $vacancyId = (int) $group->vacancy_id;
                        } else {
                            if (!$exam->assessment_score_key) {
                                continue;
                            }
                            $criterion = (string) $exam->assessment_score_key;
                            $vacancy = $exam->vacancy;
                            $title = $exam->title;
                            $vacancyId = (int) $exam->vacancy_id;
                        }

                        if (!$vacancy || (int) $attempt->application->vacancy_id !== $vacancyId) {
                            continue;
                        }

                        $criteria = $this->scoreSync->criteriaForVacancy($vacancy);
                        if (!array_key_exists($criterion, $criteria)) {
                            continue;
                        }

                        $expected = $this->scale((float) $attempt->percentage, (float) $criteria[$criterion]);

                        $candidates->push([
                            'key' => $attempt->application_id.'|'.$criterion,
                            'type' => 'written',
                            'type_label' => 'Written',
                            'source_id' => (int) $attempt->id,
                            'source_at' => $attempt->scored_at ?: $attempt->ended_at ?: $attempt->updated_at,
                            'application' => $attempt->application,
                            'vacancy' => $vacancy,
                            'criterion' => $criterion,
                            'expected' => $expected,
                            'raw_score' => (float) $attempt->percentage,
                            'source_title' => $title,
                            'set_code' => $exam->set_code,
                            'exam_id' => (int) $exam->id,
                            'assessment_group_id' => $group?->id,
                            'skill_test_id' => null,
                        ]);
                    }
                });
        }

        if (!$type || $type === 'skills') {
            SkillTestAttempt::query()
                ->with([
                    'application:id,vacancy_id,application_code,first_name,middle_name,last_name',
                    'skillTest:id,vacancy_id,skill_test_group_id,title,set_code,assessment_score_key,score_release_policy,scores_released_at,end_date',
                    'skillTest.skillTestGroup:id,vacancy_id,title,assessment_score_key,score_release_policy,scores_released_at',
                    'skillTest.vacancy:id,position_title,template_id',
                    'skillTest.skillTestGroup.vacancy:id,position_title,template_id',
                ])
                ->where('status', 2)
                ->whereNotNull('final_score')
                ->orderBy('id')
                ->chunkById(250, function ($attempts) use ($candidates) {
                    foreach ($attempts as $attempt) {
                        $test = $attempt->skillTest;
                        if (!$test || !$attempt->application || !$test->scoresAreReleased()) {
                            continue;
                        }

                        $group = $test->skillTestGroup;
                        $criterion = $group?->assessment_score_key ?: $test->assessment_score_key;
                        $vacancy = $group?->vacancy ?: $test->vacancy;
                        $vacancyId = (int) ($group?->vacancy_id ?: $test->vacancy_id);
                        $title = $group?->title ?: $test->title;

                        if (!$criterion || !$vacancy || (int) $attempt->application->vacancy_id !== $vacancyId) {
                            continue;
                        }

                        $criteria = $this->scoreSync->criteriaForVacancy($vacancy);
                        if (!array_key_exists($criterion, $criteria)) {
                            continue;
                        }

                        $expected = $this->scale((float) $attempt->final_score, (float) $criteria[$criterion]);

                        $candidates->push([
                            'key' => $attempt->application_id.'|'.$criterion,
                            'type' => 'skills',
                            'type_label' => 'Skills',
                            'source_id' => (int) $attempt->id,
                            'source_at' => $attempt->evaluated_at ?: $attempt->submitted_at ?: $attempt->updated_at,
                            'application' => $attempt->application,
                            'vacancy' => $vacancy,
                            'criterion' => (string) $criterion,
                            'expected' => $expected,
                            'raw_score' => (float) $attempt->final_score,
                            'source_title' => $title,
                            'set_code' => $test->set_code,
                            'exam_id' => null,
                            'assessment_group_id' => null,
                            'skill_test_id' => (int) $test->id,
                        ]);
                    }
                });
        }

        // If legacy data contains more than one completed source for the same
        // applicant/criterion, use the most recently finalized authoritative source.
        $authoritative = $candidates
            ->groupBy('key')
            ->map(fn ($items) => $items->sortByDesc(
                fn ($item) => optional($item['source_at'])->timestamp ?? 0
            )->first())
            ->values();

        $applicationIds = $authoritative->pluck('application.id')->filter()->unique()->values();

        $assessments = Assessment::query()
            ->whereIn('application_id', $applicationIds)
            ->get()
            ->keyBy('application_id');

        $scoreUpdateAudits = Inquiry::query()
            ->whereIn('application_id', $applicationIds)
            ->where('message', 'like', '%assessment scores were updated%')
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('application_id');

        $rows = $authoritative->map(function ($source) use ($assessments, $scoreUpdateAudits) {
            $application = $source['application'];
            $assessment = $assessments->get($application->id);

            if (!$assessment) {
                return null;
            }

            $scores = json_decode((string) $assessment->assessment, true);
            $scores = is_array($scores) ? $scores : [];

            $current = array_key_exists($source['criterion'], $scores) && is_numeric($scores[$source['criterion']])
                ? round((float) $scores[$source['criterion']], 2)
                : null;

            $expected = round((float) $source['expected'], 2);
            if ($current !== null && abs($current - $expected) < 0.005) {
                return null;
            }

            $modifierAudit = $this->matchingModifierAudit(
                $scoreUpdateAudits->get($application->id, collect()),
                $assessment->updated_at
            );

            return array_merge($source, [
                'assessment_id' => (int) $assessment->id,
                'assessment_updated_at' => $assessment->updated_at,
                'current' => $current,
                'difference' => $current === null ? null : round($current - $expected, 2),
                'modifier' => $modifierAudit?->author,
                'modifier_at' => $modifierAudit?->created_at,
                'modifier_captured' => (bool) $modifierAudit,
            ]);
        })->filter()->values();

        if ($search) {
            $needle = mb_strtolower(trim($search));
            $rows = $rows->filter(function ($row) use ($needle) {
                $app = $row['application'];
                $haystack = mb_strtolower(implode(' ', array_filter([
                    $app->application_code,
                    $app->first_name,
                    $app->middle_name,
                    $app->last_name,
                    $row['vacancy']?->position_title,
                    $row['criterion'],
                    $row['source_title'],
                ])));

                return str_contains($haystack, $needle);
            })->values();
        }

        return $rows->sortByDesc(
            fn ($row) => optional($row['assessment_updated_at'])->timestamp ?? 0
        )->values();
    }

    public function repair(string $type, int $sourceId): bool
    {
        if ($type === 'written') {
            $attempt = ExamAttempt::findOrFail($sourceId);
            return $this->scoreSync->syncWrittenAttempt($attempt);
        }

        if ($type === 'skills') {
            $attempt = SkillTestAttempt::findOrFail($sourceId);
            return $this->scoreSync->syncSkillAttempt($attempt);
        }

        return false;
    }

    protected function scale(float $scoreOutOf100, float $maxPoints): float
    {
        $normalized = max(0.0, min(100.0, $scoreOutOf100));
        return round(($normalized / 100.0) * $maxPoints, 2);
    }

    protected function matchingModifierAudit(Collection $audits, $assessmentUpdatedAt): ?Inquiry
    {
        if (!$assessmentUpdatedAt) {
            return null;
        }

        return $audits
            ->filter(fn ($audit) => $audit->created_at
                && abs($audit->created_at->diffInSeconds($assessmentUpdatedAt, false)) <= 600)
            ->sortBy(fn ($audit) => abs($audit->created_at->diffInSeconds($assessmentUpdatedAt, false)))
            ->first();
    }
}
