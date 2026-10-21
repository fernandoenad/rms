<?php

namespace App\Services\Applicant;

use App\Models\ApplicantApplicationLink;
use App\Models\ApplicantNotification;
use App\Models\Exam;
use App\Models\ExamAssignment;
use App\Models\SkillTest;
use App\Models\SkillTestAssignment;

class PublishedAssessmentScheduleNotifier
{
    public function written(Exam $exam): void
    {
        $exam = $exam->fresh();
        if ((int) $exam->status !== 1 || $exam->approval_status !== 'approved'
            || !$exam->start_date || !$exam->end_date) return;

        $applicationIds = ExamAssignment::where('exam_id', $exam->id)->pluck('application_id')->all();
        $this->notify($applicationIds, 'written', $exam->id, (int) $exam->vacancy_id, $exam->start_date, $exam->end_date);
    }

    public function skills(SkillTest $skillTest): void
    {
        $skillTest = $skillTest->fresh();
        if ((int) $skillTest->status !== 1 || $skillTest->approval_status !== 'approved'
            || !$skillTest->start_date || !$skillTest->end_date) return;

        $applicationIds = SkillTestAssignment::where('skill_test_id', $skillTest->id)->pluck('application_id')->all();
        $this->notify($applicationIds, 'skills', $skillTest->id, (int) $skillTest->vacancy_id, $skillTest->start_date, $skillTest->end_date);
    }

    private function notify(array $applicationIds, string $kind, int $setId, int $vacancyId, $start, $end): void
    {
        if (!$applicationIds) return;
        $startText = (string) $start;
        $endText = (string) $end;
        $releaseKey = hash('sha256', $kind.'|'.$setId.'|'.$startText.'|'.$endText);
        ApplicantApplicationLink::whereIn('application_id', $applicationIds)
            ->whereNotNull('verified_at')
            ->whereHas('application', fn ($query) => $query->where('vacancy_id', $vacancyId))
            ->get(['applicant_profile_id', 'application_id'])
            ->each(function ($link) use ($kind, $setId, $startText, $endText, $releaseKey) {
                ApplicantNotification::firstOrCreate(
                    ['event_key' => 'assessment-schedule:'.$releaseKey.':'.$link->application_id],
                    ['applicant_profile_id' => $link->applicant_profile_id,
                     'type' => 'assessment_schedule',
                     'title' => ucfirst($kind).' assessment schedule published',
                     'message' => 'An assigned assessment schedule has been published. Check your application for the official schedule and instructions.']
                );
            });
    }
}
