<?php

namespace App\Services;

use App\Models\AssessmentAuditLog;
use App\Models\AssessmentGroup;
use App\Models\Exam;
use App\Models\SkillTest;
use Illuminate\Support\Collection;

class AssessmentGovernanceService
{
    public function log(string $action, array $context = [], array $metadata = []): void
    {
        AssessmentAuditLog::create([
            'assessment_group_id' => $context['assessment_group_id'] ?? null,
            'exam_id' => $context['exam_id'] ?? null,
            'written_exam_id' => $context['written_exam_id'] ?? null,
            'skill_test_id' => $context['skill_test_id'] ?? null,
            'skill_test_rubric_criterion_id' => $context['skill_test_rubric_criterion_id'] ?? null,
            'user_id' => auth()->id(),
            'action' => $action,
            'metadata' => $metadata ?: null,
        ]);
    }

    public function readiness(Exam $exam): array
    {
        $exam->loadMissing(['writtenExams.options', 'assessmentGroup']);

        $issues = [];
        $items = $exam->writtenExams->where('status', 1)->values();

        if (!$exam->start_date || !$exam->end_date) {
            $issues[] = 'Schedule is incomplete.';
        } elseif ($exam->end_date->lte($exam->start_date)) {
            $issues[] = 'End date must be after the start date.';
        }

        if (!$exam->duration || (int) $exam->duration < 1) {
            $issues[] = 'Duration must be at least one minute.';
        }

        if ($items->isEmpty()) {
            $issues[] = 'No active assessment items are available.';
        }

        foreach ($items as $item) {
            $optionCount = $item->options->count();
            $correctCount = $item->options->where('is_correct', true)->count();

            if ($optionCount !== 4) {
                $issues[] = "Item {$item->id} does not have exactly four options.";
            }
            if ($correctCount !== 1) {
                $issues[] = "Item {$item->id} does not have exactly one correct answer.";
            }
            if ($item->review_status !== 'approved') {
                $issues[] = "Item {$item->id} has not been approved.";
            }
        }

        $blueprint = $exam->assessmentGroup?->blueprint ?: [];

        if ($blueprint) {
            $expectedItems = (int) ($blueprint['item_count'] ?? 0);
            if ($expectedItems > 0 && $items->count() !== $expectedItems) {
                $issues[] = "Blueprint requires {$expectedItems} active items; this set has {$items->count()}.";
            }

            $this->checkDistribution(
                $items->pluck('solo_level')->filter(),
                $blueprint['solo_distribution'] ?? [],
                'SOLO',
                $issues
            );

            $this->checkDistribution(
                $items->pluck('difficulty')->filter(),
                $blueprint['difficulty_distribution'] ?? [],
                'difficulty',
                $issues
            );

            $competencies = collect($blueprint['competencies'] ?? []);
            foreach ($competencies as $competency) {
                $name = trim((string) ($competency['name'] ?? ''));
                $target = (int) ($competency['items'] ?? 0);
                if ($name === '' || $target < 1) {
                    continue;
                }

                $actual = $items->filter(function ($item) use ($name) {
                    return str_contains(
                        mb_strtolower((string) $item->competency_basis),
                        mb_strtolower($name)
                    );
                })->count();

                if ($actual < $target) {
                    $issues[] = "Blueprint competency '{$name}' requires {$target} item(s); {$actual} matched.";
                }
            }
        }

        if ($exam->access_mode === 'selected_applicants'
            && !$exam->assignments()->exists()) {
            $issues[] = 'Selected-applicant mode is enabled but no applicants are assigned.';
        }

        return [
            'ready' => empty($issues),
            'issues' => array_values(array_unique($issues)),
            'item_count' => $items->count(),
        ];
    }

    protected function checkDistribution(
        Collection $values,
        array $target,
        string $label,
        array &$issues
    ): void {
        if (empty($target) || $values->isEmpty()) {
            return;
        }

        $total = $values->count();
        foreach ($target as $key => $percent) {
            $expected = (int) round($total * ((float) $percent / 100));
            $actual = $values->filter(fn ($value) =>
                mb_strtolower((string) $value) === mb_strtolower((string) $key)
            )->count();

            if (abs($actual - $expected) > 1) {
                $issues[] = "{$label} distribution mismatch for {$key}: expected about {$expected}, found {$actual}.";
            }
        }
    }

    public function skillReadiness(SkillTest $test): array
    {
        $test->loadMissing(['rubricCriteria']);

        $issues = [];
        $rubric = $test->rubricCriteria;

        if (!$test->start_date || !$test->end_date) {
            $issues[] = 'Schedule is incomplete.';
        } elseif ($test->end_date->lte($test->start_date)) {
            $issues[] = 'End date must be after the start date.';
        }

        if (!$test->duration || (int) $test->duration < 1) {
            $issues[] = 'Duration must be at least one minute.';
        }

        if (blank($test->instructions)) {
            $issues[] = 'Task instructions are required.';
        }

        if (!is_array($test->submission_modes) || empty($test->submission_modes)) {
            $issues[] = 'At least one submission mode is required.';
        }

        if (in_array('file', $test->submission_modes ?: [], true)
            && empty($test->allowed_extensions)) {
            $issues[] = 'File submission is enabled but no allowed extensions are configured.';
        }

        if ($test->review_status !== 'approved') {
            $issues[] = 'The skills task has not been approved.';
        }

        if ($rubric->isEmpty()) {
            $issues[] = 'No rubric criteria are defined.';
        } else {
            $total = (float) $rubric->sum('max_points');
            if (abs($total - 100) > 0.01) {
                $issues[] = 'Rubric must total exactly 100 points.';
            }

            foreach ($rubric as $criterion) {
                if ($criterion->review_status !== 'approved') {
                    $issues[] = "Rubric criterion {$criterion->id} has not been approved.";
                }
            }
        }

        if ($test->access_mode === 'selected_applicants'
            && !$test->assignments()->exists()) {
            $issues[] = 'Selected-applicant mode is enabled but no applicants are assigned.';
        }

        return [
            'ready' => empty($issues),
            'issues' => array_values(array_unique($issues)),
            'rubric_total' => (float) $rubric->sum('max_points'),
        ];
    }

    public function nextSetCode(AssessmentGroup $group): string
    {
        $used = $group->exams()->pluck('set_code')->filter()->map(fn ($x) => strtoupper((string) $x));
        for ($i = 0; $i < 26; $i++) {
            $code = chr(65 + $i);
            if (!$used->contains($code)) {
                return $code;
            }
        }

        return 'SET-' . ($used->count() + 1);
    }
}
