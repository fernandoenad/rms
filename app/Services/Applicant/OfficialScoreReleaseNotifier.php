<?php

namespace App\Services\Applicant;

use App\Models\ApplicantApplicationLink;
use App\Models\ApplicantNotification;
use App\Models\AssessmentGroup;
use App\Models\SkillTestGroup;
use App\Models\ExamAttempt;
use App\Models\SkillTestAttempt;

class OfficialScoreReleaseNotifier
{
    /**
     * Notify only current verified owners with completed attempts.
     * Never include score values or results that are not officially visible.
     */
    public function written(AssessmentGroup $group): void
    {
        $group = $group->fresh();
        if (!$group->scoresAreReleased()) return;
        $applicationIds = ExamAttempt::query()
            ->where('status', 2)
            ->whereHas('exam', fn ($query) => $query->where('assessment_group_id', $group->id))
            ->distinct()->pluck('application_id');
        $this->notifyOwners($applicationIds->all(), 'written', (int) $group->id,
            $group->scores_released_at?->format('YmdHisu') ?? 'automatic',
            'Written assessment results released',
            'Official Written Assessment results have been released. Open your application to check currently available results.');
    }

    public function skills(SkillTestGroup $group): void
    {
        $group = $group->fresh();
        if (!$group->scoresAreReleased()) return;
        $applicationIds = SkillTestAttempt::query()
            ->where('status', 2)->whereNotNull('final_score')
            ->whereHas('skillTest', fn ($query) => $query->where('skill_test_group_id', $group->id))
            ->distinct()->pluck('application_id');
        $this->notifyOwners($applicationIds->all(), 'skills', (int) $group->id,
            $group->scores_released_at?->format('YmdHisu') ?? 'automatic',
            'Skills assessment results released',
            'Official Skills Assessment results have been released. Open your application to check currently available results.');
    }

    private function notifyOwners(array $applicationIds, string $kind, int $groupId, string $releaseId, string $title, string $message): void
    {
        if (!$applicationIds) return;
        ApplicantApplicationLink::query()->whereIn('application_id', $applicationIds)
            ->whereNotNull('verified_at')->get(['applicant_profile_id','application_id'])
            ->each(function ($link) use ($kind, $groupId, $releaseId, $title, $message) {
                ApplicantNotification::firstOrCreate(
                    ['event_key' => 'official-release:'.$kind.':'.$groupId.':'.$releaseId.':'.$link->application_id],
                    ['applicant_profile_id' => $link->applicant_profile_id, 'type' => 'assessment_release',
                     'title' => $title, 'message' => $message]
                );
            });
    }
}
