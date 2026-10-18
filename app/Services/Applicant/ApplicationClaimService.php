<?php

namespace App\Services\Applicant;

use App\Models\ApplicantProfile;
use App\Models\ApplicantApplicationLink;
use App\Models\Application;
use App\Models\ApplicationClaimRequest;
use Illuminate\Support\Facades\DB;
use App\Models\ApplicantAuditLog;
use Illuminate\Validation\ValidationException;

class ApplicationClaimService
{
    public function request(ApplicantProfile $profile, string $code, string $email): ApplicationClaimRequest
    {
        $code = trim($code);
        $email = mb_strtolower(trim($email));

        // Do not return whether either identifier exists.
        $application = Application::query()
            ->where('application_code', $code)
            ->whereRaw('LOWER(TRIM(email)) = ?', [$email])
            ->first();

        if (!$application) {
            throw ValidationException::withMessages([
                'claim' => 'We could not process this request. Check your information or contact HR.',
            ]);
        }

        if (ApplicantApplicationLink::where('application_id', $application->id)->exists()) {
            throw ValidationException::withMessages([
                'claim' => 'We could not process this request. Check your information or contact HR.',
            ]);
        }

        return DB::transaction(function () use ($profile, $application, $code, $email) {
            $pending = ApplicationClaimRequest::query()
                ->where('applicant_profile_id', $profile->id)
                ->where('application_id', $application->id)
                ->whereIn('status', ['pending', 'clarification'])
                ->first();

            return $pending ?: ApplicationClaimRequest::create([
                'applicant_profile_id' => $profile->id,
                'application_id' => $application->id,
                'claimed_application_code' => $code,
                'claimed_email' => $email,
                'status' => 'pending',
            ]);
        });
    }

    public function requestClarification(ApplicationClaimRequest $claim, int $reviewerUserId, string $reason): void
    {
        DB::transaction(function () use ($claim, $reviewerUserId, $reason) {
            $claim = ApplicationClaimRequest::query()->lockForUpdate()->findOrFail($claim->id);
            if (!in_array($claim->status, ['pending', 'clarification'], true)) {
                throw ValidationException::withMessages(['claim' => 'This claim is already finalized.']);
            }
            $profile = ApplicantProfile::findOrFail($claim->applicant_profile_id);
            if ((int) $profile->user_id === $reviewerUserId) {
                throw ValidationException::withMessages(['claim' => 'You cannot review your own claim.']);
            }

            $claim->update([
                'status' => 'clarification',
                'reviewed_by' => $reviewerUserId,
                'reviewed_at' => now(),
                'review_notes' => $reason,
            ]);
            ApplicantAuditLog::create([
                'actor_user_id' => $reviewerUserId,
                'applicant_profile_id' => $claim->applicant_profile_id,
                'application_id' => $claim->application_id,
                'event_type' => 'historical_claim_clarification_requested',
                'metadata' => ['claim_request_id' => $claim->id],
            ]);
        });
    }

    public function approve(ApplicationClaimRequest $claim, int $reviewerUserId, string $reason): void
    {
        DB::transaction(function () use ($claim, $reviewerUserId, $reason) {
            $claim = ApplicationClaimRequest::query()->lockForUpdate()->findOrFail($claim->id);

            if (!in_array($claim->status, ['pending', 'clarification'], true)) {
                throw ValidationException::withMessages(['claim' => 'This claim is already finalized.']);
            }

            if (!$claim->application_id) {
                throw ValidationException::withMessages(['claim' => 'The application could not be resolved.']);
            }

            $profile = ApplicantProfile::findOrFail($claim->applicant_profile_id);
            if ((int) $profile->user_id === $reviewerUserId) {
                throw ValidationException::withMessages(['claim' => 'You cannot approve your own claim.']);
            }

            $inserted = ApplicantApplicationLink::query()->insertOrIgnore([
                'applicant_profile_id' => $profile->id,
                'application_id' => $claim->application_id,
                'link_method' => 'hr_approved_claim',
                'verified_at' => now(),
                'verified_by' => $reviewerUserId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($inserted !== 1) {
                throw ValidationException::withMessages(['claim' => 'This application is already linked.']);
            }

            ApplicantAuditLog::create([
                'actor_user_id' => $reviewerUserId,
                'applicant_profile_id' => $profile->id,
                'application_id' => $claim->application_id,
                'event_type' => 'historical_claim_approved',
                'metadata' => ['claim_request_id' => $claim->id],
            ]);
            // The first approved owner wins. Resolve other outstanding claims for this
            // application so HR is not presented with requests that can no longer succeed.
            $competing = ApplicationClaimRequest::query()
                ->where('application_id', $claim->application_id)
                ->where('id', '!=', $claim->id)
                ->whereIn('status', ['pending', 'clarification'])
                ->lockForUpdate()
                ->get();

            foreach ($competing as $other) {
                $other->update([
                    'status' => 'rejected',
                    'reviewed_by' => $reviewerUserId,
                    'reviewed_at' => now(),
                    'review_notes' => 'This application was linked to another verified claimant. Contact HR if you dispute the decision.',
                ]);
                ApplicantAuditLog::create([
                    'actor_user_id' => $reviewerUserId,
                    'applicant_profile_id' => $other->applicant_profile_id,
                    'application_id' => $claim->application_id,
                    'event_type' => 'historical_claim_conflict_closed',
                    'metadata' => ['claim_request_id' => $other->id, 'approved_claim_id' => $claim->id],
                ]);
            }

            $claim->update([
                'status' => 'approved',
                'reviewed_by' => $reviewerUserId,
                'reviewed_at' => now(),
                'review_notes' => $reason,
            ]);
        });
    }

    public function reject(ApplicationClaimRequest $claim, int $reviewerUserId, string $reason): void
    {
        DB::transaction(function () use ($claim, $reviewerUserId, $reason) {
            $claim = ApplicationClaimRequest::query()->lockForUpdate()->findOrFail($claim->id);
            if (!in_array($claim->status, ['pending', 'clarification'], true)) {
                throw ValidationException::withMessages(['claim' => 'This claim is already finalized.']);
            }
            $profile = ApplicantProfile::findOrFail($claim->applicant_profile_id);
            if ((int) $profile->user_id === $reviewerUserId) {
                throw ValidationException::withMessages(['claim' => 'You cannot review your own claim.']);
            }
            ApplicantAuditLog::create([
                'actor_user_id' => $reviewerUserId,
                'applicant_profile_id' => $claim->applicant_profile_id,
                'application_id' => $claim->application_id,
                'event_type' => 'historical_claim_rejected',
                'metadata' => ['claim_request_id' => $claim->id],
            ]);
            $claim->update([
                'status' => 'rejected',
                'reviewed_by' => $reviewerUserId,
                'reviewed_at' => now(),
                'review_notes' => $reason,
            ]);
        });
    }
}
