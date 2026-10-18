<?php

namespace App\Services\Applicant;

use App\Models\ApplicantProfile;
use App\Models\ApplicantApplicationLink;
use App\Models\Application;
use Illuminate\Support\Facades\DB;

class HistoricalApplicationLinkingService
{
    /**
     * The legacy email field was not verified during original submission.
     * Only invoke this service with an email independently verified by Google.
     */
    public function linkMatchingEmail(ApplicantProfile $profile, string $verifiedGoogleEmail): int
    {
        $email = mb_strtolower(trim($verifiedGoogleEmail));
        if ($email === '') {
            return 0;
        }

        return DB::transaction(function () use ($profile, $email) {
            $count = 0;
            $ids = Application::query()
                ->whereRaw('LOWER(TRIM(email)) = ?', [$email])
                ->orderBy('id')->pluck('id');

            foreach ($ids as $applicationId) {
                // The unique application_id constraint prevents conflicting ownership.
                $created = ApplicantApplicationLink::query()->insertOrIgnore([
                    'applicant_profile_id' => $profile->id,
                    'application_id' => $applicationId,
                    'link_method' => 'google_email_match',
                    'verified_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $count += $created;
            }

            return $count;
        });
    }
}
