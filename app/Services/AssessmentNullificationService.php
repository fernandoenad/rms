<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Assessment;
use App\Models\AssessmentGroup;
use App\Models\AssessmentNullificationBatch;
use App\Models\AssessmentNullificationItem;
use App\Models\ExamAttempt;
use App\Models\SkillTestAttempt;
use App\Models\SkillTestGroup;
use App\Models\Vacancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssessmentNullificationService
{
    public function __construct(protected AssessmentScoreSyncService $scores)
    {
    }

    public function isNullified(int $applicationId, string $criterion): bool
    {
        return AssessmentNullificationItem::query()
            ->where('application_id', $applicationId)
            ->where('criterion_key', $criterion)
            ->whereHas('batch', fn ($query) => $query->where('status', 'active'))
            ->exists();
    }

    public function lockedCriteria(int $applicationId): array
    {
        return AssessmentNullificationItem::query()
            ->where('application_id', $applicationId)
            ->whereHas('batch', fn ($query) => $query->where('status', 'active'))
            ->pluck('criterion_key')
            ->unique()
            ->values()
            ->all();
    }

    public function scoreTotal(array $scores, array $criteria): float
    {
        $total = 0.0;
        foreach ($criteria as $key => $maximum) {
            if (isset($scores[$key]) && is_numeric($scores[$key])) {
                $total += (float) $scores[$key];
            }
        }
        return round($total, 2);
    }

    /**
     * Validate a tightly scoped set selection. Affected applicants are the
     * union of applicants who started an attempt in either selected group.
     * No vacancy-wide updates or unselected groups are included.
     */
    public function preview(int $vacancyId, ?int $writtenGroupId, ?int $skillsGroupId): array
    {
        if (!$writtenGroupId && !$skillsGroupId) {
            throw ValidationException::withMessages(['selection' => 'Select at least one assessment group.']);
        }

        $vacancy = Vacancy::findOrFail($vacancyId);
        $criteria = $this->scores->criteriaForVacancy($vacancy);
        $keys = [];
        $ids = collect();

        if ($writtenGroupId) {
            $group = AssessmentGroup::findOrFail($writtenGroupId);
            if ((int) $group->vacancy_id !== $vacancyId || !$group->assessment_score_key
                || !array_key_exists($group->assessment_score_key, $criteria)) {
                throw ValidationException::withMessages(['written_group_id' => 'Written group must belong to this position and have a valid mapped criterion.']);
            }
            if (ExamAttempt::whereHas('exam', fn ($q) => $q->where('assessment_group_id', $group->id))
                ->where('status', 1)->whereNotNull('started_at')->exists()) {
                throw ValidationException::withMessages(['written_group_id' => 'Finalize all active written attempts before nullification.']);
            }
            $keys[] = $group->assessment_score_key;
            $ids = $ids->merge(ExamAttempt::whereHas('exam', fn ($q) => $q->where('assessment_group_id', $group->id))
                ->whereNotNull('started_at')->pluck('application_id'));
        }

        if ($skillsGroupId) {
            $group = SkillTestGroup::findOrFail($skillsGroupId);
            if ((int) $group->vacancy_id !== $vacancyId || !$group->assessment_score_key
                || !array_key_exists($group->assessment_score_key, $criteria)) {
                throw ValidationException::withMessages(['skills_group_id' => 'Skills group must belong to this position and have a valid mapped criterion.']);
            }
            if (SkillTestAttempt::whereHas('skillTest', fn ($q) => $q->where('skill_test_group_id', $group->id))
                ->where('status', 1)->whereNotNull('started_at')->exists()) {
                throw ValidationException::withMessages(['skills_group_id' => 'Finalize all active skills attempts before nullification.']);
            }
            $keys[] = $group->assessment_score_key;
            $ids = $ids->merge(SkillTestAttempt::whereHas('skillTest', fn ($q) => $q->where('skill_test_group_id', $group->id))
                ->whereNotNull('started_at')->pluck('application_id'));
        }

        if (count(array_unique($keys)) !== count($keys)) {
            throw ValidationException::withMessages(['selection' => 'Written and Skills groups must map to different score criteria.']);
        }

        $ids = $ids->map(fn ($id) => (int) $id)->unique()->sort()->values();
        if ($ids->isEmpty()) {
            throw ValidationException::withMessages(['selection' => 'No applicants started an attempt in the selected groups. Nothing can be nullified.']);
        }

        $eligible = Application::query()
            ->where('vacancy_id', $vacancyId)
            ->whereIn('id', $ids)
            ->whereHas('assessment')
            ->count();
        if ($eligible !== $ids->count()) {
            throw ValidationException::withMessages(['selection' => 'Some affected applicants have no matching assessment for this vacancy; correct the records first.']);
        }

        $overlaps = AssessmentNullificationItem::query()
            ->whereIn('application_id', $ids)
            ->whereIn('criterion_key', $keys)
            ->whereHas('batch', fn ($q) => $q->where('status', 'active'))
            ->exists();
        if ($overlaps) {
            throw ValidationException::withMessages(['selection' => 'A selected applicant/criterion is already nullified. Restore or resolve that action before proceeding.']);
        }

        $fingerprint = hash('sha256', implode('|', [
            $vacancyId, $writtenGroupId ?: 0, $skillsGroupId ?: 0,
            implode(',', $keys), $ids->implode(','),
        ]));

        return [
            'vacancy' => $vacancy,
            'applicant_ids' => $ids->all(),
            'count' => $ids->count(),
            'criteria' => $keys,
            'fingerprint' => $fingerprint,
            'sample' => Application::whereIn('id', $ids)
                ->orderBy('application_code')->limit(20)
                ->get(['id', 'application_code', 'first_name', 'last_name']),
        ];
    }

    public function nullify(array $input, int $actorId): AssessmentNullificationBatch
    {
        return DB::transaction(function () use ($input, $actorId) {
            $vacancyId = (int) $input['vacancy_id'];
            $writtenId = !empty($input['written_group_id']) ? (int) $input['written_group_id'] : null;
            $skillsId = !empty($input['skills_group_id']) ? (int) $input['skills_group_id'] : null;

            // Serialize actions on the selected groups before re-checking scope.
            if ($writtenId) {
                AssessmentGroup::whereKey($writtenId)->lockForUpdate()->firstOrFail();
            }
            if ($skillsId) {
                SkillTestGroup::whereKey($skillsId)->lockForUpdate()->firstOrFail();
            }
            $scope = $this->preview($vacancyId, $writtenId, $skillsId);
            if (!hash_equals($scope['fingerprint'], (string) $input['scope_fingerprint'])) {
                throw ValidationException::withMessages(['selection' => 'The affected applicant list changed since preview. Review the current list and confirm again.']);
            }

            $batch = AssessmentNullificationBatch::create([
                'vacancy_id' => $vacancyId,
                'written_group_id' => $writtenId,
                'skills_group_id' => $skillsId,
                'board_reference' => $input['board_reference'],
                'board_date' => $input['board_date'],
                'reason' => $input['reason'],
                'status' => 'active',
                'created_by' => $actorId,
            ]);

            $criteria = $this->scores->criteriaForVacancy($scope['vacancy']);
            foreach ($scope['applicant_ids'] as $applicationId) {
                $assessment = Assessment::where('application_id', $applicationId)->lockForUpdate()->firstOrFail();
                $values = json_decode((string) $assessment->assessment, true);
                $values = is_array($values) ? $values : [];
                foreach ($scope['criteria'] as $key) {
                    // Locking this assessment serializes concurrent nullifications
                    // and score writes targeting the same applicant.
                    if ($this->isNullified($applicationId, $key)) {
                        throw ValidationException::withMessages(['selection' => 'An overlapping nullification was recorded. No scores were changed.']);
                    }

                    $present = array_key_exists($key, $values);
                    AssessmentNullificationItem::create([
                        'batch_id' => $batch->id,
                        'application_id' => $applicationId,
                        'criterion_key' => $key,
                        'previously_present' => $present,
                        'previous_value_json' => $present ? json_encode($values[$key]) : null,
                    ]);
                    $values[$key] = 0.0;
                }
                $assessment->update([
                    'assessment' => json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'score' => $this->scoreTotal($values, $criteria),
                ]);
            }

            if ($writtenId) {
                AssessmentGroup::whereKey($writtenId)->update([
                    'is_paused' => true, 'pause_reason' => 'Board-nullified: '.$input['board_reference'],
                    'paused_at' => now(), 'paused_by' => $actorId, 'scores_synced_at' => now(),
                ]);
            }
            if ($skillsId) {
                SkillTestGroup::whereKey($skillsId)->update([
                    'is_paused' => true, 'pause_reason' => 'Board-nullified: '.$input['board_reference'],
                    'paused_at' => now(), 'paused_by' => $actorId, 'scores_synced_at' => now(),
                ]);
            }

            return $batch;
        }, 3);
    }

    /**
     * Restore the captured criterion values, not the assessment attempt results.
     * Selected groups stay paused until a separate authorized action resumes them.
     */
    public function restore(AssessmentNullificationBatch $batch, int $actorId, string $reference, string $reason): void
    {
        DB::transaction(function () use ($batch, $actorId, $reference, $reason) {
            $locked = AssessmentNullificationBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'active') {
                throw ValidationException::withMessages(['restoration' => 'This batch has already been restored.']);
            }

            $criteria = $this->scores->criteriaForVacancy(Vacancy::findOrFail($locked->vacancy_id));
            $items = $locked->items()->orderBy('application_id')->orderBy('criterion_key')->get()->groupBy('application_id');

            foreach ($items as $applicationId => $entries) {
                $assessment = Assessment::where('application_id', $applicationId)->lockForUpdate()->firstOrFail();
                $scores = json_decode((string) $assessment->assessment, true);
                $scores = is_array($scores) ? $scores : [];

                foreach ($entries as $entry) {
                    $current = $scores[$entry->criterion_key] ?? null;
                    if (!is_numeric($current) || abs((float) $current) > 0.00001) {
                        throw ValidationException::withMessages(['restoration' => 'An official score changed after nullification; no changes were restored. Investigate the conflicting record first.']);
                    }
                    if ($entry->previously_present) {
                        $scores[$entry->criterion_key] = json_decode($entry->previous_value_json, true);
                    } else {
                        unset($scores[$entry->criterion_key]);
                    }
                }

                $assessment->update([
                    'assessment' => json_encode($scores, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'score' => $this->scoreTotal($scores, $criteria),
                ]);
            }

            $locked->update([
                'status' => 'restored', 'restored_by' => $actorId,
                'restoration_reference' => $reference, 'restoration_reason' => $reason,
                'restored_at' => now(),
            ]);
            // Synchronizers may resume after restoration, but paused groups are
            // not reopened automatically. HR must verify the Board authorization.
        }, 3);
    }

    /**
     * Prevent manual score entry from bypassing a Board decision. Non-nullified
     * criteria retain normal edit behavior, and the assessment status is preserved
     * unless explicitly supplied by its original workflow.
     */
    public function updateManualAssessment(Assessment $assessment, array $formData, ?int $status = null): void
    {
        DB::transaction(function () use ($assessment, $formData, $status) {
            $current = Assessment::whereKey($assessment->id)->lockForUpdate()->firstOrFail();
            $vacancy = $current->application->vacancy;
            $criteria = $this->scores->criteriaForVacancy($vacancy);
            $template = json_decode((string) $vacancy->template?->template, true);
            $keys = array_keys(is_array($template) ? $template : []);
            $submitted = array_intersect_key($formData, array_flip($keys));
            $locked = $this->lockedCriteria((int) $current->application_id);
            foreach ($locked as $key) {
                if (array_key_exists($key, $submitted) && (!is_numeric($submitted[$key]) || abs((float) $submitted[$key]) > 0.00001)) {
                    throw ValidationException::withMessages([$key => 'Board-nullified score is locked at zero.']);
                }
                $submitted[$key] = 0.0;
            }

            $changes = [
                'assessment' => json_encode($submitted, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'score' => $this->scoreTotal($submitted, $criteria),
            ];
            if ($status !== null) {
                $changes['status'] = $status;
            }
            $current->update($changes);
        });
    }
}
