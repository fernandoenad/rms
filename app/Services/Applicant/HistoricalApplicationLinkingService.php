<?php

namespace App\Services\Applicant;

use App\Models\ApplicantProfile;
use App\Models\ApplicantApplicationLink;
use App\Models\Application;
use App\Models\ApplicationClaimRequest;
use App\Models\ApplicantAuditLog;
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
                if ($created === 1) {
                    // A verified Google match may resolve a record while HR claims
                    // are still awaiting review. Close those requests atomically.
                    $openClaims = ApplicationClaimRequest::query()
                        ->where('application_id', $applicationId)
                        ->whereIn('status', ['pending', 'clarification'])
                        ->lockForUpdate()
                        ->get();
                    foreach ($openClaims as $claim) {
                        $sameApplicant = (int) $claim->applicant_profile_id === (int) $profile->id;
                        $claim->update([
                            'status' => $sameApplicant ? 'approved' : 'rejected',
                            'reviewed_at' => now(),
                            'review_notes' => $sameApplicant
                                ? 'Ownership established by verified Google email.'
                                : 'Application linked through verified Google email. Contact HR if you dispute this outcome.',
                        ]);
                        ApplicantAuditLog::create([
                            'actor_user_id' => $profile->user_id,
                            'applicant_profile_id' => $claim->applicant_profile_id,
                            'application_id' => $applicationId,
                            'event_type' => $sameApplicant
                                ? 'historical_claim_resolved_by_google'
                                : 'historical_claim_conflict_closed_by_google',
                            'metadata' => ['claim_request_id' => $claim->id],
                        ]);
                    }
                }
                $count += $created;
            }

            return $count;
        });
    }
}
