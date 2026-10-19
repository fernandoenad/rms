<?php

namespace Tests\Feature;

use App\Models\ApplicantApplicationLink;
use App\Models\ApplicantProfile;
use App\Models\Application;
use App\Models\User;
use App\Models\Vacancy;
use App\Models\Template;
use App\Services\Applicant\ApplicationClaimService;
use App\Services\Applicant\HistoricalApplicationLinkingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ApplicantLifecycleIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function profile(string $email = 'applicant@example.test'): ApplicantProfile
    {
        return ApplicantProfile::create([
            'user_id' => User::factory()->create()->id,
            'first_name' => 'Ana', 'middle_name' => 'M', 'last_name' => 'Reyes',
            'sitio' => 'Poblacion', 'barangay' => 'Central',
            'municipality' => 'Sagbayan', 'zip' => 6331, 'age' => 30,
            'gender' => 'Female', 'civil_status' => 'Single', 'religion' => 'None',
            'disability' => 'None', 'ethnic_group' => 'None',
            'email' => $email, 'phone' => '09123456789',
            'profile_completed_at' => now(),
        ]);
    }

    private function vacancy(): Vacancy
    {
        $template = Template::create([
            'type' => 'Applicant Lifecycle Test Template',
            'template' => json_encode(['Written_Examination' => 20, 'Skills_Test' => 20, 'Interview' => 60, 'Remarks' => 'Remarks']),
            'status' => 1,
        ]);

        return Vacancy::create([
            'cycle' => 2026, 'position_title' => 'Teacher I',
            'salary_grade' => 11, 'base_pay' => 27000,
            'office_level' => 1, 'qualifications' => 'Required',
            'vacancy' => 1, 'status' => 1, 'template_id' => $template->id, 'level1_status' => 1,
            'level2_status' => 0,
        ]);
    }

    private function historical(Vacancy $vacancy, string $email): Application
    {
        return Application::create([
            'vacancy_id' => $vacancy->id,
            'application_code' => 'HIST-'.uniqid(),
            'first_name' => 'Old', 'middle_name' => 'B', 'last_name' => 'Applicant',
            'sitio' => 'Old Sitio', 'barangay' => 'Old Barangay',
            'municipality' => 'Old Town', 'zip' => 6300, 'age' => 29,
            'gender' => 'Female', 'civil_status' => 'Single', 'religion' => 'None',
            'disability' => 'None', 'ethnic_group' => 'None',
            'email' => $email, 'phone' => '09111111111',
        ]);
    }

    public function test_matching_google_email_auto_links_once_without_rewriting_historical_data(): void
    {
        $profile = $this->profile();
        $application = $this->historical($this->vacancy(), ' APPlicant@example.test ');
        $linker = app(HistoricalApplicationLinkingService::class);

        $this->assertSame(1, $linker->linkMatchingEmail($profile, 'applicant@example.test'));
        $this->assertSame(0, $linker->linkMatchingEmail($profile, 'applicant@example.test'));
        $this->assertDatabaseCount('applicant_application_links', 1);
        $this->assertSame('Old', $application->fresh()->first_name);
        $this->assertSame('Old Town', $application->fresh()->municipality);
    }

    public function test_google_auto_link_resolves_competing_pending_hr_claims(): void
    {
        $verified = $this->profile('verified@example.test');
        $other = $this->profile('other@example.test');
        $application = $this->historical($this->vacancy(), 'verified@example.test');
        $claims = app(ApplicationClaimService::class);
        $ownClaim = $claims->request($verified, $application->application_code, 'verified@example.test');
        $competingClaim = $claims->request($other, $application->application_code, 'verified@example.test');

        $this->assertSame(1, app(HistoricalApplicationLinkingService::class)
            ->linkMatchingEmail($verified, 'verified@example.test'));
        $this->assertSame('approved', $ownClaim->fresh()->status);
        $this->assertSame('rejected', $competingClaim->fresh()->status);
        $this->assertDatabaseHas('applicant_application_links', [
            'application_id' => $application->id,
            'applicant_profile_id' => $verified->id,
        ]);
        $this->assertDatabaseHas('applicant_audit_logs', [
            'event_type' => 'historical_claim_conflict_closed_by_google',
            'applicant_profile_id' => $other->id,
        ]);
        $this->assertDatabaseCount('applicant_application_links', 1);
    }

    public function test_matching_email_cannot_take_over_an_already_linked_application(): void
    {
        $first = $this->profile();
        $other = $this->profile('different@example.test');
        $application = $this->historical($this->vacancy(), 'applicant@example.test');
        $linker = app(HistoricalApplicationLinkingService::class);

        $this->assertSame(1, $linker->linkMatchingEmail($first, 'applicant@example.test'));
        $this->assertSame(0, $linker->linkMatchingEmail($other, 'applicant@example.test'));
        $this->assertDatabaseHas('applicant_application_links', [
            'application_id' => $application->id, 'applicant_profile_id' => $first->id,
        ]);
    }

    public function test_other_email_claim_requires_hr_approval_and_preserves_historical_record(): void
    {
        $profile = $this->profile();
        $application = $this->historical($this->vacancy(), 'previous@example.test');
        $service = app(ApplicationClaimService::class);

        $claim = $service->request($profile, $application->application_code, 'previous@example.test');
        $this->assertSame('pending', $claim->status);
        $this->assertDatabaseCount('applicant_application_links', 0);

        $reviewer = User::factory()->create();
        $service->approve($claim, $reviewer->id, 'Official identification and historical supporting records verified.');
        $this->assertSame('approved', $claim->fresh()->status);
        $this->assertDatabaseHas('applicant_application_links', [
            'application_id' => $application->id,
            'applicant_profile_id' => $profile->id,
            'link_method' => 'hr_approved_claim',
        ]);
        $this->assertSame('Old', $application->fresh()->first_name);
        $this->assertDatabaseHas('applicant_audit_logs', ['event_type' => 'historical_claim_approved']);
    }

    public function test_hr_approval_http_flow_grants_only_the_verified_claimant_access(): void
    {
        $claimant = $this->profile('current@example.test');
        $unrelated = $this->profile('other@example.test');
        $application = $this->historical($this->vacancy(), 'previous@example.test');
        $admin = User::factory()->create(['email' => 'approver@deped.gov.ph']);
        \App\Models\Role::create(['user_id' => $admin->id, 'level' => 1, 'status' => 1]);

        $this->actingAs($claimant->user)->post(route('applicant.claims.store'), [
            'application_code' => $application->application_code,
            'email' => 'previous@example.test',
        ])->assertRedirect(route('applicant.dashboard'));

        $claim = \App\Models\ApplicationClaimRequest::where('applicant_profile_id', $claimant->id)->firstOrFail();
        $this->assertSame('pending', $claim->status);
        $this->actingAs($claimant->user)->get(route('guest.applications.show', $application))->assertForbidden();
        $this->actingAs($unrelated->user)->get(route('guest.applications.show', $application))->assertForbidden();

        $this->actingAs($admin)->post(route('admin.applicant_claims.approve', $claim), [
            'review_notes' => 'Verified government identification and original application documents.',
            'identity_verified' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('application_claim_requests', ['id' => $claim->id, 'status' => 'approved']);
        $this->assertDatabaseHas('applicant_application_links', [
            'application_id' => $application->id,
            'applicant_profile_id' => $claimant->id,
            'link_method' => 'hr_approved_claim',
        ]);
        $this->actingAs($claimant->user)->get(route('applicant.dashboard'))
            ->assertOk()->assertSee($application->application_code);
        // The unassigned legacy application must render without opening the
        // secondary station database; assigned-station views remain staging checks.
        $this->actingAs($claimant->user)
            ->get(route('guest.applications.show', $application))
            ->assertOk()
            ->assertSee($application->application_code);
        $this->actingAs($unrelated->user)->get(route('guest.applications.show', $application))->assertForbidden();
        $this->assertSame('Old', $application->fresh()->first_name);
    }

    public function test_hr_approval_requires_explicit_identity_verification(): void
    {
        $profile = $this->profile('current@example.test');
        $application = $this->historical($this->vacancy(), 'old@example.test');
        $claim = app(ApplicationClaimService::class)->request($profile, $application->application_code, 'old@example.test');
        $admin = User::factory()->create(['email' => 'admin@deped.gov.ph']);
        \App\Models\Role::create(['user_id' => $admin->id, 'level' => 1, 'status' => 1]);

        $this->actingAs($admin)->post(route('admin.applicant_claims.approve', $claim), [
            'review_notes' => 'Original documents have been inspected in person.',
        ])->assertSessionHasErrors('identity_verified');

        $this->assertSame('pending', $claim->fresh()->status);
        $this->assertDatabaseMissing('applicant_application_links', ['application_id' => $application->id]);
        $this->assertDatabaseMissing('applicant_audit_logs', [
            'application_id' => $application->id, 'event_type' => 'historical_claim_approved',
        ]);
    }

    public function test_http_clarification_then_verified_hr_approval_preserves_legacy_record(): void
    {
        $claimant = $this->profile('current@example.test');
        $outsider = $this->profile('outsider@example.test');
        $application = $this->historical($this->vacancy(), 'previous@example.test');
        $admin = User::factory()->create(['email' => 'hr.reviewer@deped.gov.ph']);
        \App\Models\Role::create(['user_id' => $admin->id, 'level' => 1, 'status' => 1]);

        $this->actingAs($claimant->user)->post(route('applicant.claims.store'), [
            'application_code' => $application->application_code,
            'email' => 'previous@example.test',
        ])->assertRedirect(route('applicant.dashboard'));

        $claim = \App\Models\ApplicationClaimRequest::where('applicant_profile_id', $claimant->id)->sole();

        $this->actingAs($admin)->get(route('admin.applicant_claims.index'))
            ->assertOk()->assertSee($application->application_code)->assertSee('Ana Reyes');

        $this->actingAs($admin)->post(route('admin.applicant_claims.clarification', $claim), [
            'review_notes' => 'Please present original identification to the HR reviewer.',
        ])->assertRedirect();

        $this->assertSame('clarification', $claim->fresh()->status);
        $this->assertDatabaseCount('applicant_application_links', 0);
        $this->actingAs($claimant->user)->get(route('applicant.dashboard'))
            ->assertOk()->assertSee('Please present original identification to the HR reviewer.');
        $this->actingAs($claimant->user)
            ->get(route('guest.applications.show', $application))->assertForbidden();

        $this->actingAs($admin)->post(route('admin.applicant_claims.approve', $claim), [
            'identity_verified' => '1',
            'review_notes' => 'Original ID and archived recruitment records independently verified.',
        ])->assertRedirect();

        $this->assertDatabaseHas('application_claim_requests', [
            'id' => $claim->id, 'status' => 'approved', 'reviewed_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('applicant_application_links', [
            'application_id' => $application->id,
            'applicant_profile_id' => $claimant->id,
            'link_method' => 'hr_approved_claim',
            'verified_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('applicant_audit_logs', [
            'application_id' => $application->id,
            'event_type' => 'historical_claim_clarification_requested',
        ]);
        $this->assertDatabaseHas('applicant_audit_logs', [
            'application_id' => $application->id,
            'event_type' => 'historical_claim_approved',
        ]);

        $this->actingAs($claimant->user)->get(route('applicant.dashboard'))
            ->assertOk()->assertSee($application->application_code);
        $this->actingAs($outsider->user)->get(route('guest.applications.show', $application))
            ->assertForbidden();
        $this->actingAs($admin)->get(route('admin.applicant_claims.index', ['status' => 'approved']))
            ->assertOk()
            ->assertSee('Original ID and archived recruitment records independently verified.')
            ->assertDontSee('Approve ownership');

        $this->assertSame('Old', $application->fresh()->first_name);
        $this->assertSame('previous@example.test', $application->fresh()->email);
        $this->assertDatabaseCount('applicant_application_links', 1);
    }

    public function test_lower_level_staff_cannot_approve_historical_claim_by_posting_directly(): void
    {
        $profile = $this->profile('current@example.test');
        $application = $this->historical($this->vacancy(), 'previous@example.test');
        $claim = app(ApplicationClaimService::class)
            ->request($profile, $application->application_code, 'previous@example.test');
        $staff = User::factory()->create(['email' => 'staff@deped.gov.ph']);
        \App\Models\Role::create(['user_id' => $staff->id, 'level' => 2, 'status' => 1]);

        $this->actingAs($staff)->post(route('admin.applicant_claims.approve', $claim), [
            'identity_verified' => '1',
            'review_notes' => 'Attempted approval without HR administration permission.',
        ])->assertStatus(401);

        $this->assertSame('pending', $claim->fresh()->status);
        $this->assertDatabaseCount('applicant_application_links', 0);
        $this->assertDatabaseMissing('applicant_audit_logs', [
            'application_id' => $application->id,
            'event_type' => 'historical_claim_approved',
        ]);
    }

    public function test_claimant_cannot_approve_their_own_application(): void
    {
        $profile = $this->profile();
        $application = $this->historical($this->vacancy(), 'previous@example.test');
        $service = app(ApplicationClaimService::class);
        $claim = $service->request($profile, $application->application_code, 'previous@example.test');

        $this->expectException(ValidationException::class);
        $service->approve($claim, $profile->user_id, 'Official supporting records verified.');
    }

    public function test_claimant_cannot_reject_their_own_claim(): void
    {
        $profile = $this->profile();
        $application = $this->historical($this->vacancy(), 'previous@example.test');
        $service = app(ApplicationClaimService::class);
        $claim = $service->request($profile, $application->application_code, 'previous@example.test');

        try {
            $service->reject($claim, $profile->user_id, 'Attempt to reject own claim.');
            $this->fail('Claimants must not be permitted to review their own claim.');
        } catch (ValidationException $exception) {
            $this->assertSame('pending', $claim->fresh()->status);
            $this->assertDatabaseCount('applicant_application_links', 0);
            $this->assertDatabaseMissing('applicant_audit_logs', [
                'event_type' => 'historical_claim_rejected',
                'application_id' => $application->id,
            ]);
        }
    }

    public function test_hr_clarification_does_not_grant_ownership_and_can_later_be_approved(): void
    {
        $profile = $this->profile();
        $application = $this->historical($this->vacancy(), 'previous@example.test');
        $service = app(ApplicationClaimService::class);
        $claim = $service->request($profile, $application->application_code, 'previous@example.test');
        $reviewer = User::factory()->create();

        $service->requestClarification($claim, $reviewer->id, 'Present a valid government identification document to HR.');
        $this->assertDatabaseHas('application_claim_requests', [
            'id' => $claim->id, 'status' => 'clarification', 'reviewed_by' => $reviewer->id,
        ]);
        $this->assertDatabaseCount('applicant_application_links', 0);
        $this->assertDatabaseHas('applicant_audit_logs', [
            'event_type' => 'historical_claim_clarification_requested',
            'application_id' => $application->id,
        ]);

        $service->approve($claim, $reviewer->id, 'Government identification and archived application verified.');
        $this->assertDatabaseHas('applicant_application_links', [
            'application_id' => $application->id, 'applicant_profile_id' => $profile->id,
        ]);
        $this->assertSame('approved', $claim->fresh()->status);
    }

    public function test_rejecting_claim_records_reason_without_linking_application(): void
    {
        $profile = $this->profile();
        $application = $this->historical($this->vacancy(), 'previous@example.test');
        $service = app(ApplicationClaimService::class);
        $claim = $service->request($profile, $application->application_code, 'previous@example.test');
        $reviewer = User::factory()->create();

        $service->reject($claim, $reviewer->id, 'The supporting documents did not establish original ownership.');

        $this->assertDatabaseHas('application_claim_requests', [
            'id' => $claim->id, 'status' => 'rejected', 'reviewed_by' => $reviewer->id,
        ]);
        $this->assertDatabaseCount('applicant_application_links', 0);
        $this->assertDatabaseHas('applicant_audit_logs', [
            'event_type' => 'historical_claim_rejected', 'application_id' => $application->id,
        ]);
    }

    public function test_duplicate_pending_request_is_idempotent(): void
    {
        $profile = $this->profile();
        $application = $this->historical($this->vacancy(), 'previous@example.test');
        $service = app(ApplicationClaimService::class);

        $first = $service->request($profile, $application->application_code, 'previous@example.test');
        $second = $service->request($profile, $application->application_code, 'PREVIOUS@example.test');

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('application_claim_requests', 1);
        $this->assertDatabaseCount('applicant_application_links', 0);
    }

    public function test_competing_claim_cannot_override_prior_approval(): void
    {
        $first = $this->profile('first@example.test');
        $second = $this->profile('second@example.test');
        $application = $this->historical($this->vacancy(), 'previous@example.test');
        $service = app(ApplicationClaimService::class);
        $claimA = $service->request($first, $application->application_code, 'previous@example.test');
        $claimB = $service->request($second, $application->application_code, 'previous@example.test');
        $reviewer = User::factory()->create();
        $service->approve($claimA, $reviewer->id, 'Independent identification and historical documentation verified.');

        try {
            $service->approve($claimB, $reviewer->id, 'Competing claim submitted with supporting documents.');
            $this->fail('Competing claim must never replace existing ownership.');
        } catch (ValidationException $exception) {
            $this->assertDatabaseHas('applicant_application_links', [
                'application_id' => $application->id, 'applicant_profile_id' => $first->id,
            ]);
            $this->assertDatabaseHas('application_claim_requests', [
                'id' => $claimB->id, 'status' => 'rejected', 'reviewed_by' => $reviewer->id,
            ]);
            $this->assertDatabaseHas('applicant_audit_logs', [
                'event_type' => 'historical_claim_conflict_closed',
                'applicant_profile_id' => $second->id,
                'application_id' => $application->id,
            ]);
            $this->assertDatabaseCount('applicant_application_links', 1);
        }
    }

    public function test_finalized_claim_cannot_be_approved_twice(): void
    {
        $profile = $this->profile();
        $application = $this->historical($this->vacancy(), 'previous@example.test');
        $service = app(ApplicationClaimService::class);
        $claim = $service->request($profile, $application->application_code, 'previous@example.test');
        $reviewer = User::factory()->create();
        $service->reject($claim, $reviewer->id, 'The submitted evidence is insufficient to establish ownership.');

        $this->expectException(ValidationException::class);
        $service->approve($claim, $reviewer->id, 'Attempt to reverse finalized decision without review.');
    }

    public function test_submission_recomputes_age_from_birthdate_in_legacy_snapshot(): void
    {
        $profile = $this->profile();
        $profile->update([
            'date_of_birth' => now()->subYears(26)->toDateString(),
            'age' => 65, // Stale cached value must never enter a new submission.
        ]);
        $vacancy = $this->vacancy();

        $this->actingAs($profile->user)->post(route('applicant.apply.store', $vacancy), [
            'confirm' => 1,
        ])->assertRedirect(route('applicant.dashboard'));

        $application = Application::where('vacancy_id', $vacancy->id)->sole();
        $this->assertSame(26, (int) $application->age);
        $this->assertSame(65, (int) $profile->fresh()->age);
    }

    public function test_profile_based_submission_creates_legacy_application_and_owner_link(): void
    {
        $profile = $this->profile();
        $vacancy = $this->vacancy();
        $this->actingAs($profile->user)
            ->post(route('applicant.apply.store', $vacancy), ['confirm' => 1])
            ->assertRedirect(route('applicant.dashboard'));

        $application = Application::where('vacancy_id', $vacancy->id)->sole();
        $this->assertSame('Ana', $application->first_name);
        $this->assertSame('2026-'.$vacancy->id.'-'.$application->id, $application->application_code);
        $this->assertDatabaseHas('applicant_application_links', [
            'application_id' => $application->id,
            'applicant_profile_id' => $profile->id,
            'link_method' => 'new_submission',
        ]);

        $this->actingAs($profile->user)
            ->post(route('applicant.apply.store', $vacancy), ['confirm' => 1])
            ->assertSessionHasErrors('application');
        $this->assertDatabaseCount('applications', 1);
    }

    public function test_repeat_vacancy_review_redirects_to_existing_application(): void
    {
        $profile = $this->profile();
        $vacancy = $this->vacancy();
        $this->actingAs($profile->user)->post(
            route('applicant.apply.store', $vacancy), ['confirm' => 1]
        )->assertRedirect(route('applicant.dashboard'));

        $application = Application::where('vacancy_id', $vacancy->id)->sole();
        $this->actingAs($profile->user)
            ->get(route('applicant.apply.review', $vacancy))
            ->assertRedirect(route('guest.applications.show', $application));
        $this->assertDatabaseCount('applications', 1);
    }

    public function test_existing_application_remains_accessible_after_vacancy_closes(): void
    {
        $profile = $this->profile();
        $vacancy = $this->vacancy();
        $this->actingAs($profile->user)->post(
            route('applicant.apply.store', $vacancy), ['confirm' => 1]
        )->assertRedirect(route('applicant.dashboard'));
        $application = Application::where('vacancy_id', $vacancy->id)->sole();

        $vacancy->update(['posting_end_at' => now()->subMinute()]);
        $profile->update(['profile_completed_at' => null]);

        $this->actingAs($profile->user)
            ->get(route('applicant.apply.review', $vacancy))
            ->assertRedirect(route('guest.applications.show', $application));

        $this->assertDatabaseCount('applications', 1);
    }

    public function test_application_cannot_be_submitted_after_vacancy_closes(): void
    {
        $profile = $this->profile();
        $vacancy = $this->vacancy();
        $vacancy->update(['posting_end_at' => now()->subMinute()]);

        $this->actingAs($profile->user)
            ->post(route('applicant.apply.store', $vacancy), ['confirm' => 1])
            ->assertForbidden();

        $this->assertDatabaseCount('applications', 0);
        $this->assertDatabaseCount('applicant_application_links', 0);
    }

    public function test_submission_rejects_historical_duplicate_email_with_whitespace_and_case(): void
    {
        $profile = $this->profile('applicant@example.test');
        $vacancy = $this->vacancy();
        $existing = $this->historical($vacancy, '  APPLICANT@EXAMPLE.TEST  ');

        $this->actingAs($profile->user)
            ->post(route('applicant.apply.store', $vacancy), ['confirm' => 1])
            ->assertSessionHasErrors('application');

        $this->assertDatabaseCount('applications', 1);
        $this->assertDatabaseCount('applicant_application_links', 0);
        $this->assertSame('Old', $existing->fresh()->first_name);
    }

    public function test_profile_marked_incomplete_cannot_create_an_application(): void
    {
        $profile = $this->profile();
        $profile->update(['profile_completed_at' => null]);
        $vacancy = $this->vacancy();

        $this->actingAs($profile->user)
            ->post(route('applicant.apply.store', $vacancy), ['confirm' => 1])
            ->assertSessionHasErrors('profile');

        $this->assertDatabaseCount('applications', 0);
        $this->assertDatabaseCount('applicant_application_links', 0);
    }

    public function test_completed_profile_can_review_submit_and_reopen_application(): void
    {
        $profile = $this->profile();
        $vacancy = $this->vacancy();

        $this->actingAs($profile->user)
            ->get(route('applicant.apply.review', $vacancy))
            ->assertOk()
            ->assertSee('Review Application')
            ->assertSee('Ana');

        $this->actingAs($profile->user)
            ->post(route('applicant.apply.store', $vacancy), ['confirm' => 1])
            ->assertRedirect(route('applicant.dashboard'));

        $application = Application::where('vacancy_id', $vacancy->id)->sole();
        $this->actingAs($profile->user)
            ->get(route('applicant.dashboard'))
            ->assertOk()
            ->assertSee($application->application_code);

        $this->actingAs($profile->user)
            ->get(route('applicant.apply.review', $vacancy))
            ->assertRedirect(route('guest.applications.show', $application));

        $this->assertDatabaseCount('applications', 1);
        $this->assertDatabaseHas('applicant_application_links', [
            'applicant_profile_id' => $profile->id,
            'application_id' => $application->id,
        ]);
    }

    public function test_other_applicant_cannot_read_an_owned_application_detail(): void
    {
        $owner = $this->profile('owner@example.test');
        $intruder = $this->profile('intruder@example.test');
        $application = $this->historical($this->vacancy(), 'owner@example.test');
        ApplicantApplicationLink::create([
            'applicant_profile_id' => $owner->id,
            'application_id' => $application->id,
            'link_method' => 'google_email_match',
            'verified_at' => now(),
        ]);

        $this->actingAs($intruder->user)
            ->get(route('guest.applications.show', $application))
            ->assertForbidden();
    }

    public function test_profile_changes_never_rewrite_a_historical_application(): void
    {
        $profile = $this->profile();
        $old = $this->historical($this->vacancy(), 'previous@example.test');
        $profile->update(['first_name' => 'Updated', 'email' => 'new@example.test']);

        $this->assertSame('Old', $old->fresh()->first_name);
        $this->assertSame('previous@example.test', $old->fresh()->email);
    }
}
