<?php

namespace Tests\Feature;

use App\Models\OAuthIdentity;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use Tests\TestCase;

class StaffGoogleCallbackIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function mockGoogle(string $subject, string $email, bool $verified = true): void
    {
        $google = (new GoogleUser())->setRaw(['email_verified' => $verified]);
        $google->map(['id' => $subject, 'email' => $email, 'name' => 'Test User']);

        $driver = Mockery::mock();
        $driver->shouldReceive('redirectUrl')->once()->andReturnSelf();
        $driver->shouldReceive('user')->once()->andReturn($google);
        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($driver);
    }

    public function test_staff_login_binds_verified_subject_to_existing_authorized_user(): void
    {
        $user = User::factory()->create(['email' => 'teacher@deped.gov.ph']);
        Role::create(['user_id' => $user->id, 'level' => 2, 'status' => 1]);
        $this->mockGoogle('google-staff-001', 'teacher@deped.gov.ph');

        $this->get('/auth/google/callback')->assertRedirect();
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('oauth_identities', [
            'provider' => 'google', 'provider_subject' => 'google-staff-001',
            'user_id' => $user->id, 'email_verified' => 1,
        ]);
    }

    public function test_unverified_email_cannot_sign_in_as_staff(): void
    {
        $user = User::factory()->create(['email' => 'teacher@deped.gov.ph']);
        Role::create(['user_id' => $user->id, 'level' => 2, 'status' => 1]);
        $this->mockGoogle('unverified-subject', 'teacher@deped.gov.ph', false);

        $this->get('/auth/google/callback')->assertRedirect('/login');
        $this->assertGuest();
        $this->assertDatabaseCount('oauth_identities', 0);
    }

    public function test_applicant_without_staff_role_cannot_sign_in_through_staff_callback(): void
    {
        $user = User::factory()->create(['email' => 'teacher@deped.gov.ph']);
        $this->mockGoogle('applicant-only-subject', 'teacher@deped.gov.ph');

        $this->get('/auth/google/callback')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_google_subject_belonging_to_another_user_cannot_be_rebound(): void
    {
        $staff = User::factory()->create(['email' => 'teacher@deped.gov.ph']);
        Role::create(['user_id' => $staff->id, 'level' => 2, 'status' => 1]);
        $other = User::factory()->create();
        OAuthIdentity::create([
            'user_id' => $other->id, 'provider' => 'google',
            'provider_subject' => 'subject-already-owned',
            'provider_email' => $other->email, 'email_verified' => true,
        ]);
        $this->mockGoogle('subject-already-owned', 'teacher@deped.gov.ph');

        $this->get('/auth/google/callback')->assertForbidden();
        $this->assertGuest();
        $this->assertDatabaseHas('oauth_identities', [
            'provider_subject' => 'subject-already-owned', 'user_id' => $other->id,
        ]);
    }
}
