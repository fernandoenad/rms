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

    public function test_guest_cannot_read_a_legacy_application_record(): void
    {
        $this->get('/applications/999999')->assertRedirect('/login');
    }
}
