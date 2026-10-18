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

    public function test_guest_cannot_read_a_legacy_application_record(): void
    {
        $this->get('/applications/999999')->assertRedirect('/login');
    }
}
