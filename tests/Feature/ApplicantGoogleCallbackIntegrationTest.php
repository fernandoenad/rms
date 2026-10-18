<?php

namespace Tests\Feature;

use App\Models\ApplicantProfile;
use App\Models\Application;
use App\Models\OAuthIdentity;
use App\Models\Role;
use App\Models\User;
use App\Models\Vacancy;
use App\Models\Template;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use Tests\TestCase;

class ApplicantGoogleCallbackIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function mockGoogle(string $subject, string $email, bool $verified = true): void
    {
        $google = (new GoogleUser())->setRaw(['email_verified' => $verified]);
        $google->map(['id' => $subject, 'email' => $email, 'name' => 'Applicant User']);

        $driver = Mockery::mock();
        $driver->shouldReceive('redirectUrl')->once()->andReturnSelf();
        $driver->shouldReceive('user')->once()->andReturn($google);
        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($driver);
    }

    public function test_verified_google_account_creates_applicant_without_staff_role(): void
    {
        $this->mockGoogle('applicant-new-001', 'new@example.test');

        $this->get(route('applicant.google.callback'))
            ->assertRedirect(route('applicant.dashboard'));

        $identity = OAuthIdentity::where('provider_subject', 'applicant-new-001')->sole();
        $this->assertAuthenticatedAs(User::findOrFail($identity->user_id));
        $this->assertDatabaseHas('applicant_profiles', ['user_id' => $identity->user_id]);
        $this->assertDatabaseMissing('roles', ['user_id' => $identity->user_id]);
    }

    public function test_returning_google_subject_reuses_existing_applicant(): void
    {
        $user = User::factory()->create(['email' => 'returning@example.test']);
        OAuthIdentity::create([
            'user_id' => $user->id, 'provider' => 'google',
            'provider_subject' => 'applicant-returning-001',
            'provider_email' => $user->email, 'email_verified' => true,
        ]);
        $this->mockGoogle('applicant-returning-001', 'returning@example.test');

        $this->get(route('applicant.google.callback'))
            ->assertRedirect(route('applicant.dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('oauth_identities', 1);
        $this->assertDatabaseHas('applicant_profiles', ['user_id' => $user->id]);
    }

    public function test_verified_google_login_auto_links_historical_application(): void
    {
        $template = Template::create([
            'type' => 'OAuth Test', 'template' => json_encode(['Interview' => 100]), 'status' => 1,
        ]);
        $vacancy = Vacancy::create([
            'cycle' => 2026, 'position_title' => 'Teacher I',
            'salary_grade' => 11, 'base_pay' => 27000, 'office_level' => 1,
            'qualifications' => 'Required', 'vacancy' => 1, 'status' => 1,
            'template_id' => $template->id, 'level1_status' => 1, 'level2_status' => 0,
        ]);
        $application = Application::create([
            'vacancy_id' => $vacancy->id, 'application_code' => 'GOOGLE-HISTORY-001',
            'first_name' => 'Historical', 'middle_name' => 'A',
            'last_name' => 'Applicant', 'sitio' => 'Sitio',
            'barangay' => 'Barangay', 'municipality' => 'Town',
            'zip' => 6300, 'age' => 30, 'gender' => 'Female',
            'civil_status' => 'Single', 'religion' => 'None', 'disability' => 'None',
            'ethnic_group' => 'None', 'email' => 'history@example.test',
            'phone' => '09123456789',
        ]);
        $this->mockGoogle('history-google-001', 'history@example.test');

        $this->get(route('applicant.google.callback'))
            ->assertRedirect(route('applicant.dashboard'));

        $identity = OAuthIdentity::where('provider_subject', 'history-google-001')->sole();
        $profile = ApplicantProfile::where('user_id', $identity->user_id)->sole();
        $this->assertDatabaseHas('applicant_application_links', [
            'application_id' => $application->id,
            'applicant_profile_id' => $profile->id,
            'link_method' => 'google_email_match',
        ]);
        $this->assertSame('Historical', $application->fresh()->first_name);
    }

    public function test_google_login_clears_pending_vacancy_from_another_session(): void
    {
        $this->mockGoogle('switch-user-001', 'switch@example.test');
        $this->withSession([
            'applicant_pending_vacancy_id' => 999,
            'applicant_pending_vacancy_user_id' => 123,
            'applicant_pending_vacancy_started_at' => now()->timestamp,
        ])->get(route('applicant.google.callback'))
            ->assertRedirect(route('applicant.dashboard'));

        $this->assertNull(session('applicant_pending_vacancy_id'));
        $this->assertNull(session('applicant_pending_vacancy_user_id'));
        $this->assertNull(session('applicant_pending_vacancy_started_at'));
    }

    public function test_unverified_google_email_cannot_create_applicant(): void
    {
        $this->mockGoogle('unverified-new-001', 'new@example.test', false);

        $this->get(route('applicant.google.callback'))->assertForbidden();
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('oauth_identities', 0);
    }

    public function test_staff_email_without_linked_subject_cannot_be_adopted_by_applicant_login(): void
    {
        $user = User::factory()->create(['email' => 'teacher@deped.gov.ph']);
        Role::create(['user_id' => $user->id, 'level' => 2, 'status' => 1]);
        $this->mockGoogle('unlinked-staff-subject', 'teacher@deped.gov.ph');

        $this->get(route('applicant.google.callback'))->assertForbidden();
        $this->assertGuest();
        $this->assertDatabaseCount('oauth_identities', 0);
    }

    public function test_known_staff_google_subject_can_access_applicant_portal_without_role_change(): void
    {
        $user = User::factory()->create(['email' => 'teacher@deped.gov.ph']);
        Role::create(['user_id' => $user->id, 'level' => 2, 'status' => 1]);
        OAuthIdentity::create([
            'user_id' => $user->id, 'provider' => 'google',
            'provider_subject' => 'known-staff-001',
            'provider_email' => $user->email, 'email_verified' => true,
        ]);
        $this->mockGoogle('known-staff-001', 'teacher@deped.gov.ph');

        $this->get(route('applicant.google.callback'))
            ->assertRedirect(route('applicant.dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('roles', ['user_id' => $user->id, 'level' => 2, 'status' => 1]);
        $this->assertDatabaseHas('applicant_profiles', ['user_id' => $user->id]);
    }
}
