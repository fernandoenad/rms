<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Exam;
use App\Models\SkillTest;

class EquivalentSetScheduleResolver
{
    public function currentWrittenSet(int $groupId, Application $application): ?Exam
    {
        return $this->writtenCandidates($groupId, $application)
            ->filter(fn (Exam $set) =>
                $set->start_date
                && $set->end_date
                && now()->gte($set->start_date)
                && now()->lt($set->end_date)
            )
            ->sortByDesc(fn (Exam $set) => $set->start_date->getTimestamp())
            ->first();
    }

    public function nextWrittenSet(int $groupId, Application $application): ?Exam
    {
        return $this->writtenCandidates($groupId, $application)
            ->filter(fn (Exam $set) => $set->start_date && now()->lt($set->start_date))
            ->sortBy(fn (Exam $set) => $set->start_date->getTimestamp())
            ->first();
    }

    public function currentSkillSet(int $groupId, Application $application): ?SkillTest
    {
        return $this->skillCandidates($groupId, $application)
            ->filter(fn (SkillTest $set) =>
                $set->start_date
                && $set->end_date
                && now()->gte($set->start_date)
                && now()->lt($set->end_date)
            )
            ->sortByDesc(fn (SkillTest $set) => $set->start_date->getTimestamp())
            ->first();
    }

    public function nextSkillSet(int $groupId, Application $application): ?SkillTest
    {
        return $this->skillCandidates($groupId, $application)
            ->filter(fn (SkillTest $set) => $set->start_date && now()->lt($set->start_date))
            ->sortBy(fn (SkillTest $set) => $set->start_date->getTimestamp())
            ->first();
    }

    protected function writtenCandidates(int $groupId, Application $application)
    {
        return Exam::query()
            ->where('assessment_group_id', $groupId)
            ->where('vacancy_id', $application->vacancy_id)
            ->where('status', 1)
            ->whereNotNull('start_date')
            ->whereNotNull('end_date')
            ->where(function ($q) use ($application) {
                $q->where('access_mode', 'all_taken_in')
                    ->orWhereExists(function ($sub) use ($application) {
                        $sub->selectRaw('1')
                            ->from('exam_assignments')
                            ->whereColumn('exam_assignments.exam_id', 'exams.id')
                            ->where('exam_assignments.application_id', $application->id);
                    });
            })
            ->with([
                'assessmentGroup:id,title,status,is_paused,archived_at,score_release_policy,scores_released_at',
                'attempts' => fn ($q) => $q->where('application_id', $application->id),
            ])
            ->orderBy('start_date')
            ->orderBy('set_code')
            ->get()
            ->filter(fn (Exam $set) =>
                $set->assessmentGroup
                && $set->assessmentGroup->status
                && !$set->assessmentGroup->archived_at
                && !$set->assessmentGroup->is_paused
            )
            ->values();
    }

    protected function skillCandidates(int $groupId, Application $application)
    {
        return SkillTest::query()
            ->where('skill_test_group_id', $groupId)
            ->where('vacancy_id', $application->vacancy_id)
            ->where('status', 1)
            ->whereNotNull('start_date')
            ->whereNotNull('end_date')
            ->where(function ($q) use ($application) {
                $q->where('access_mode', 'all_taken_in')
                    ->orWhereExists(function ($sub) use ($application) {
                        $sub->selectRaw('1')
                            ->from('skill_test_assignments')
                            ->whereColumn('skill_test_assignments.skill_test_id', 'skill_tests.id')
                            ->where('skill_test_assignments.application_id', $application->id);
                    });
            })
            ->with([
                'skillTestGroup:id,title,status,is_paused,archived_at,score_release_policy,scores_released_at',
                'attempts' => fn ($q) => $q->where('application_id', $application->id),
            ])
            ->orderBy('start_date')
            ->orderBy('set_code')
            ->get()
            ->filter(fn (SkillTest $set) =>
                $set->skillTestGroup
                && $set->skillTestGroup->status
                && !$set->skillTestGroup->archived_at
                && !$set->skillTestGroup->is_paused
            )
            ->values();
    }
}
