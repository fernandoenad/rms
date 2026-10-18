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
