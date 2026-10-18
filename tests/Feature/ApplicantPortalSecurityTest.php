<?php

namespace Tests\Feature;

use App\Models\ApplicantProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicantPortalSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_unauthenticated_visitor_cannot_access_applicant_dashboard(): void
    {
        $this->get('/applicant/dashboard')->assertRedirect('/login');
    }

    public function test_applicant_login_uses_google_sign_in_and_shows_deped_reset_guidance(): void
    {
        $this->get(route('applicant.login'))
            ->assertOk()
            ->assertSee('Sign in to start your session')
            ->assertSee('Sign in with Google')
            ->assertSee(route('applicant.google'))
            ->assertDontSee('https://hrms.depedbohol.org/help/reset')
            ->assertDontSee('Staff login');
    }

    public function test_staff_user_menu_contains_applicant_portal_switch(): void
    {
        $menu = config('adminlte.menu');
        $switch = collect($menu)->first(fn ($item) =>
            is_array($item) && ($item['text'] ?? null) === 'Switch to Applicant Portal'
        );

        $this->assertNotNull($switch);
        $this->assertSame('applicant/dashboard', $switch['url']);
        $this->assertTrue($switch['topnav_user']);
    }

    public function test_public_footer_does_not_expose_administration_link(): void
    {
        $this->get(route('applicant.login'))
            ->assertOk()
            ->assertDontSee('>Administration</a>', false);
    }

    public function test_historical_claim_review_is_discoverable_from_admin_menu(): void
    {
        $item = collect(config('adminlte.menu'))->first(fn ($item) =>
            is_array($item) && ($item['text'] ?? null) === 'Historical Claims'
        );

        $this->assertNotNull($item);
        $this->assertSame('admin/applicant-claims', $item['url']);
        $this->assertSame('review-applicant-claims', $item['can']);
        $this->assertContains('admin/applicant-claims', $item['active']);
    }

    public function test_guest_cannot_open_hr_historical_claim_review(): void
    {
        $this->get(route('admin.applicant_claims.index'))->assertRedirect();
    }

    public function test_historical_claim_review_menu_permission_rejects_regular_staff(): void
    {
        $user = User::factory()->create(['email' => 'regular.staff@deped.gov.ph']);
        \App\Models\Role::create(['user_id' => $user->id, 'level' => 2, 'status' => 1]);

        $this->assertFalse(\Illuminate\Support\Facades\Gate::forUser($user)->allows('review-applicant-claims'));
        $this->assertSame(401, $this->actingAs($user)->get(route('admin.applicant_claims.index'))->status());
    }

    public function test_level_one_admin_sees_an_empty_historical_claim_review_queue(): void
    {
        $user = User::factory()->create(['email' => 'reviewer@deped.gov.ph']);
        \App\Models\Role::create(['user_id' => $user->id, 'level' => 1, 'status' => 1]);

        $this->actingAs($user)->get(route('admin.applicant_claims.index'))
            ->assertOk()
            ->assertSee('Historical Application Claim Review')
            ->assertSee('No pending or clarification-requested application claims');
    }

    public function test_level_one_admin_can_view_read_only_claim_history_tabs(): void
    {
        $admin = User::factory()->create(['email' => 'audit@deped.gov.ph']);
        \App\Models\Role::create(['user_id' => $admin->id, 'level' => 1, 'status' => 1]);

        foreach (['approved', 'rejected'] as $status) {
            $this->actingAs($admin)
                ->get(route('admin.applicant_claims.index', ['status' => $status]))
                ->assertOk()
                ->assertSee('Approved history')
                ->assertSee('Rejected history')
                ->assertDontSee('Approve ownership')
                ->assertDontSee('Reject claim');
        }
    }

    public function test_completed_claim_history_shows_decision_notes_without_review_forms(): void
    {
        $admin = User::factory()->create(['email' => 'history.reviewer@deped.gov.ph']);
        \App\Models\Role::create(['user_id' => $admin->id, 'level' => 1, 'status' => 1]);
        $claimant = User::factory()->create();
        $profile = ApplicantProfile::create([
            'user_id' => $claimant->id,
            'first_name' => 'Ana',
            'last_name' => 'Reyes',
        ]);
        foreach (['approved', 'rejected'] as $status) {
            \App\Models\ApplicationClaimRequest::create([
                'applicant_profile_id' => $profile->id,
                'claimed_application_code' => 'HIST-'.strtoupper($status),
                'claimed_email' => 'old@example.test',
                'status' => $status,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'review_notes' => 'Historical identity documents reviewed for '.$status.'.',
            ]);
        }

        foreach (['approved', 'rejected'] as $status) {
            $other = $status === 'approved' ? 'rejected' : 'approved';
            $this->actingAs($admin)
                ->get(route('admin.applicant_claims.index', ['status' => $status]))
                ->assertOk()
                ->assertSee('HIST-'.strtoupper($status))
                ->assertDontSee('HIST-'.strtoupper($other))
                ->assertSee('Historical identity documents reviewed for '.$status.'.')
                ->assertSee('Ana Reyes')
                ->assertDontSee('Approve ownership')
                ->assertDontSee('Reject claim')
                ->assertDontSee('Request clarification (no access granted)');
        }
    }

    public function test_hr_claim_queue_shows_claimant_and_hides_own_review_actions(): void
    {
        $admin = User::factory()->create(['email' => 'reviewer@deped.gov.ph']);
        \App\Models\Role::create(['user_id' => $admin->id, 'level' => 1, 'status' => 1]);
        $profile = ApplicantProfile::create([
            'user_id' => $admin->id, 'first_name' => 'Maria', 'last_name' => 'Reyes',
        ]);
        \App\Models\ApplicationClaimRequest::create([
            'applicant_profile_id' => $profile->id,
            'claimed_application_code' => 'HIST-SELF-1',
            'claimed_email' => 'previous@example.test',
            'status' => 'pending',
        ]);
        $this->actingAs($admin)->get(route('admin.applicant_claims.index'))
            ->assertOk()
            ->assertSee('Maria Reyes')
            ->assertSee('You cannot review your own claim')
            ->assertDontSee('Approve ownership')
            ->assertDontSee('Reject claim');
    }

    public function test_active_staff_can_switch_between_staff_and_applicant_portals(): void
    {
        $user = User::factory()->create(['email' => 'staff@deped.gov.ph']);
        \App\Models\Role::create(['user_id' => $user->id, 'level' => 2, 'status' => 1]);

        $this->actingAs($user)->get(route('admin.index'))->assertOk();
        $this->actingAs($user)->get(route('applicant.dashboard'))
            ->assertOk()
            ->assertSee('Switch to Staff Portal')
            ->assertSee(route('admin.index'));

        $this->assertDatabaseHas('applicant_profiles', ['user_id' => $user->id]);
        $this->assertAuthenticatedAs($user);
    }

    public function test_applicant_without_staff_role_cannot_access_staff_portal(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('applicant.dashboard'))
            ->assertOk()
            ->assertDontSee('Switch to Staff Portal');

        // Legacy active middleware redirects non-staff users rather than returning 403.
        $this->actingAs($user)->get(route('admin.index'))->assertRedirect();
    }

    public function test_an_authenticated_applicant_can_open_profile_editor(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/applicant/profile')->assertOk();
        $this->assertDatabaseHas('applicant_profiles', ['user_id' => $user->id]);
    }

    public function test_an_authenticated_applicant_cannot_submit_a_claim_without_required_inputs(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/applicant/claims', [])
            ->assertSessionHasErrors(['application_code', 'email']);
    }

    public function test_first_time_applicant_is_redirected_to_profile_instead_of_404(): void
    {
        $template = \App\Models\Template::create([
            'type' => 'First Time Profile Test',
            'template' => json_encode(['Written_Examination' => 20, 'Interview' => 80, 'Remarks' => 'Remarks']),
            'status' => 1,
        ]);
        $vacancy = \App\Models\Vacancy::create([
            'cycle' => 2026, 'position_title' => 'Teacher I',
            'salary_grade' => 11, 'base_pay' => 27000,
            'office_level' => 1, 'qualifications' => 'Required',
            'vacancy' => 1, 'status' => 1,
            'template_id' => $template->id,
            'level1_status' => 1, 'level2_status' => 0,
        ]);
        $user = User::factory()->create();
        $this->actingAs($user)
            ->get(route('applicant.apply.review', $vacancy))
            ->assertRedirect(route('applicant.profile.edit'));
        $this->assertDatabaseHas('applicant_profiles', ['user_id' => $user->id]);
        $this->assertDatabaseCount('applications', 0);
    }

    public function test_closed_vacancy_does_not_prompt_new_applicant_to_complete_profile(): void
    {
        $template = \App\Models\Template::create([
            'type' => 'Closed Vacancy Test',
            'template' => json_encode(['Written_Examination' => 20, 'Interview' => 80, 'Remarks' => 'Remarks']),
            'status' => 1,
        ]);
        $vacancy = \App\Models\Vacancy::create([
            'cycle' => 2026, 'position_title' => 'Closed Teacher I',
            'salary_grade' => 11, 'base_pay' => 27000,
            'office_level' => 1, 'qualifications' => 'Required',
            'vacancy' => 1, 'status' => 1,
            'template_id' => $template->id,
            'posting_end_at' => now()->subMinute(),
            'level1_status' => 1, 'level2_status' => 0,
        ]);
        $user = User::factory()->create();
        $this->actingAs($user)
            ->get(route('applicant.apply.review', $vacancy))
            ->assertNotFound();
        $this->assertDatabaseCount('applications', 0);
    }

    public function test_saving_profile_resumes_pending_open_vacancy_review(): void
    {
        $template = \App\Models\Template::create([
            'type' => 'Onboarding Resume',
            'template' => json_encode(['Interview' => 100, 'Remarks' => 'Remarks']),
            'status' => 1,
        ]);
        $vacancy = \App\Models\Vacancy::create([
            'cycle' => 2026, 'position_title' => 'Applicant Onboarding',
            'salary_grade' => 11, 'base_pay' => 27000, 'office_level' => 1,
            'qualifications' => 'Required', 'vacancy' => 1, 'status' => 1,
            'template_id' => $template->id, 'level1_status' => 1, 'level2_status' => 0,
        ]);
        $user = User::factory()->create();
        $this->actingAs($user)
            ->get(route('applicant.apply.review', $vacancy))
            ->assertRedirect(route('applicant.profile.edit'));

        $this->actingAs($user)->put(route('applicant.profile.update'), $this->validProfile())
            ->assertRedirect(route('applicant.apply.review', $vacancy));
        $this->assertDatabaseHas('applicant_profiles', ['user_id' => $user->id, 'first_name' => 'Ana']);
        $this->assertDatabaseCount('applications', 0);
    }

    public function test_profile_save_does_not_resume_a_vacancy_that_closed_mid_onboarding(): void
    {
        $template = \App\Models\Template::create([
            'type' => 'Closed Onboarding Resume',
            'template' => json_encode(['Interview' => 100, 'Remarks' => 'Remarks']),
            'status' => 1,
        ]);
        $vacancy = \App\Models\Vacancy::create([
            'cycle' => 2026, 'position_title' => 'Closing Vacancy',
            'salary_grade' => 11, 'base_pay' => 27000, 'office_level' => 1,
            'qualifications' => 'Required', 'vacancy' => 1, 'status' => 1,
            'template_id' => $template->id, 'level1_status' => 1, 'level2_status' => 0,
        ]);
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('applicant.apply.review', $vacancy))
            ->assertRedirect(route('applicant.profile.edit'));
        $vacancy->update(['posting_end_at' => now()->subMinute()]);

        $this->actingAs($user)->put(route('applicant.profile.update'), $this->validProfile())
            ->assertRedirect(route('applicant.dashboard'));
        $this->assertDatabaseCount('applications', 0);
    }

    public function test_pending_vacancy_from_another_account_does_not_redirect_after_profile_save(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)
            ->withSession(['applicant_pending_vacancy_id' => 1234, 'applicant_pending_vacancy_user_id' => $user->id + 100])
            ->put(route('applicant.profile.update'), $this->validProfile())
            ->assertRedirect(route('applicant.dashboard'));

        $this->assertDatabaseHas('applicant_profiles', ['user_id' => $user->id]);
        $this->assertDatabaseCount('applications', 0);
        $this->assertNull(session('applicant_pending_vacancy_id'));
        $this->assertNull(session('applicant_pending_vacancy_user_id'));
    }

    public function test_expired_pending_vacancy_does_not_resume_after_profile_save(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->withSession([
            'applicant_pending_vacancy_id' => 1234,
            'applicant_pending_vacancy_user_id' => $user->id,
            'applicant_pending_vacancy_started_at' => now()->subMinutes(31)->timestamp,
        ])->put(route('applicant.profile.update'), $this->validProfile())
            ->assertRedirect(route('applicant.dashboard'));

        $this->assertNull(session('applicant_pending_vacancy_id'));
        $this->assertNull(session('applicant_pending_vacancy_user_id'));
        $this->assertNull(session('applicant_pending_vacancy_started_at'));
        $this->assertDatabaseCount('applications', 0);
    }

    private function validProfile(): array
    {
        return [
            'first_name' => 'Ana', 'middle_name' => 'M', 'last_name' => 'Reyes',
            'sitio' => 'Poblacion', 'barangay' => 'Central', 'municipality' => 'Sagbayan',
            'zip' => 6331, 'age' => 30, 'gender' => 'Female',
            'civil_status' => 'Single', 'religion' => 'None',
            'disability' => 'None', 'ethnic_group' => 'None',
            'email' => 'applicant@example.test', 'phone' => '09123456789',
        ];
    }

    public function test_guest_cannot_read_a_legacy_application_record(): void
    {
        $this->get('/applications/999999')->assertRedirect('/login');
    }
}
