<?php

namespace App\Services\Applicant;

use App\Models\ApplicantApplicationLink;
use App\Models\ApplicantNotification;
use App\Models\Application;

class HrApplicationStatusNotifier
{
    public function changed(Application $application, int $inquiryId, string $status): void
    {
        // HR qualification is a screening tag, not an appointment or final selection.
        $messages = [
            'qualified' => ['HR screening status updated', 'HR marked your application as qualified for the applicable screening stage. This is not a final appointment decision.'],
            'disqualified' => ['HR screening status updated', 'HR marked your application as disqualified for the applicable screening stage. Review your application and contact HR for guidance.'],
        ];
        if (!isset($messages[$status])) return;
        $details = $messages[$status];
        ApplicantApplicationLink::query()
            ->where('application_id', $application->id)
            ->whereNotNull('verified_at')
            ->get(['applicant_profile_id'])
            ->each(function ($link) use ($application, $inquiryId, $status, $details) {
                ApplicantNotification::firstOrCreate(
                    ['event_key' => 'hr-screening:'.$inquiryId.':'.$status.':'.$link->applicant_profile_id],
                    ['applicant_profile_id' => $link->applicant_profile_id,
                     'type' => 'application_status',
                     'title' => $details[0],
                     'message' => $application->application_code.' — '.$details[1]]
                );
            });
    }
}
