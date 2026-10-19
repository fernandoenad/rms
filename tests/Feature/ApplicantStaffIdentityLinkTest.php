<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicantStaffIdentityLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_start_staff_identity_linking(): void
    {
        $this->get('/auth/staff/link-google')->assertRedirect('/login');
    }

    public function test_applicant_without_authorized_staff_role_cannot_start_staff_identity_linking(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/auth/staff/link-google')
            ->assertRedirect(route('login'));
    }

    public function test_applicant_oauth_redirect_uses_distinct_registered_callback(): void
    {
        $driver = \Mockery::mock();
        $driver->shouldReceive('redirectUrl')->once()
            ->with(route('applicant.google.callback'))->andReturnSelf();
        $driver->shouldReceive('redirect')->once()
            ->andReturn(redirect('https://accounts.google.com/'));
        \Laravel\Socialite\Facades\Socialite::shouldReceive('driver')
            ->once()->with('google')->andReturn($driver);

        $this->get(route('applicant.google'))->assertRedirect('https://accounts.google.com/');
        $this->assertNotSame(route('applicant.google.callback'), url('/auth/google/callback'));
        $this->assertNotSame(route('applicant.google.callback'), route('applicant.staff.google.callback'));
    }

    public function test_authorized_staff_can_link_matching_verified_google_identity_without_creating_another_user(): void
    {
        $staff = User::factory()->create(['email' => 'teacher@deped.gov.ph']);
        \App\Models\Role::create(['user_id' => $staff->id, 'level' => 2, 'status' => 1]);

        $google = (new \Laravel\Socialite\Two\User())
            ->setRaw(['email_verified' => true]);
        $google->map([
            'id' => 'staff-link-subject-001',
            'email' => 'teacher@deped.gov.ph',
            'name' => 'Staff Teacher',
        ]);

        $driver = \Mockery::mock();
        $driver->shouldReceive('redirectUrl')->twice()
            ->with(route('applicant.staff.google.callback'))->andReturnSelf();
        $driver->shouldReceive('redirect')->once()
            ->andReturn(redirect('https://accounts.google.com/'));
        $driver->shouldReceive('user')->once()->andReturn($google);
        \Laravel\Socialite\Facades\Socialite::shouldReceive('driver')
            ->twice()->with('google')->andReturn($driver);

        $this->actingAs($staff)->get(route('applicant.staff.google.link'))
            ->assertRedirect('https://accounts.google.com/');
        $this->assertSame($staff->id, session('staff_google_link_user_id'));

        $this->actingAs($staff)->get(route('applicant.staff.google.callback'))
            ->assertRedirect(route('applicant.dashboard'));

        $this->assertAuthenticatedAs($staff);
        $this->assertNull(session('staff_google_link_user_id'));
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('oauth_identities', [
            'provider_subject' => 'staff-link-subject-001',
            'provider_email' => 'teacher@deped.gov.ph',
            'user_id' => $staff->id,
            'email_verified' => 1,
        ]);
        $this->assertDatabaseHas('applicant_profiles', ['user_id' => $staff->id]);
        $this->assertDatabaseHas('roles', ['user_id' => $staff->id, 'status' => 1, 'level' => 2]);
    }

    public function test_staff_link_callback_without_initiating_session_is_refused(): void
    {
        $staff = User::factory()->create(['email' => 'teacher@deped.gov.ph']);
        \App\Models\Role::create(['user_id' => $staff->id, 'level' => 2, 'status' => 1]);

        $this->actingAs($staff)->get(route('applicant.staff.google.callback'))->assertForbidden();
        $this->assertDatabaseCount('oauth_identities', 0);
    }

    public function test_staff_link_rejects_google_email_different_from_registered_staff_email(): void
    {
        $staff = User::factory()->create(['email' => 'teacher@deped.gov.ph']);
        \App\Models\Role::create(['user_id' => $staff->id, 'level' => 2, 'status' => 1]);
        $this->mockStaffLinkCallback('subject-wrong-email', 'someone.else@gmail.com');

        $this->actingAs($staff)->withSession(['staff_google_link_user_id' => $staff->id])
            ->get(route('applicant.staff.google.callback'))->assertForbidden();

        $this->assertDatabaseCount('oauth_identities', 0);
        $this->assertDatabaseMissing('applicant_profiles', ['user_id' => $staff->id]);
    }

    public function test_staff_link_cannot_reassign_google_subject_linked_to_another_user(): void
    {
        $staff = User::factory()->create(['email' => 'teacher@deped.gov.ph']);
        \App\Models\Role::create(['user_id' => $staff->id, 'level' => 2, 'status' => 1]);
        $other = User::factory()->create(['email' => 'other@example.test']);
        \App\Models\OAuthIdentity::create([
            'provider' => 'google', 'provider_subject' => 'already-linked-subject',
            'provider_email' => 'other@example.test',
            'email_verified' => true, 'user_id' => $other->id,
        ]);
        $this->mockStaffLinkCallback('already-linked-subject', 'teacher@deped.gov.ph');

        $this->actingAs($staff)->withSession(['staff_google_link_user_id' => $staff->id])
            ->get(route('applicant.staff.google.callback'))->assertForbidden();

        $this->assertDatabaseHas('oauth_identities', [
            'provider_subject' => 'already-linked-subject', 'user_id' => $other->id,
        ]);
        $this->assertDatabaseMissing('applicant_profiles', ['user_id' => $staff->id]);
    }

    private function mockStaffLinkCallback(string $subject, string $email): void
    {
        $google = (new \Laravel\Socialite\Two\User())
            ->setRaw(['email_verified' => true]);
        $google->map(['id' => $subject, 'email' => $email, 'name' => 'Staff User']);
        $driver = \Mockery::mock();
        $driver->shouldReceive('redirectUrl')->once()
            ->with(route('applicant.staff.google.callback'))->andReturnSelf();
        $driver->shouldReceive('user')->once()->andReturn($google);
        \Laravel\Socialite\Facades\Socialite::shouldReceive('driver')
            ->once()->with('google')->andReturn($driver);
    }

    public function test_applicant_callback_and_staff_callback_are_separate_routes(): void
    {
        $this->assertSame(
            'applicant.google.callback',
            \Illuminate\Support\Facades\Route::getRoutes()->getByName('applicant.google.callback')->getName()
        );
        $this->assertNotSame(
            route('applicant.google.callback'),
            route('applicant.staff.google.callback')
        );
    }
}
