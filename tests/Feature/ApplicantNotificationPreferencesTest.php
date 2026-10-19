<?php

namespace Tests\Feature;

use App\Models\ApplicantNotificationPreference;
use App\Models\ApplicantProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicantNotificationPreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_preferences_are_scoped_to_current_applicant(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $profile = ApplicantProfile::create(['user_id' => $owner->id]);
        $otherProfile = ApplicantProfile::create(['user_id' => $other->id]);
        ApplicantNotificationPreference::create(['applicant_profile_id' => $otherProfile->id, 'email_enabled' => false]);

        $this->actingAs($owner)->post(route('applicant.notification_preferences.update'), [
            'email_enabled' => '1', 'email_application_updates' => '1',
        ])->assertRedirect(route('applicant.dashboard').'#notificationPreferences');

        $this->assertTrue((bool) ApplicantNotificationPreference::where('applicant_profile_id', $profile->id)->firstOrFail()->email_enabled);
        $this->assertFalse((bool) ApplicantNotificationPreference::where('applicant_profile_id', $otherProfile->id)->firstOrFail()->email_enabled);
    }

    public function test_invalid_preference_values_are_rejected(): void
    {
        $owner = User::factory()->create();
        ApplicantProfile::create(['user_id' => $owner->id]);
        $this->actingAs($owner)->post(route('applicant.notification_preferences.update'), [
            'email_enabled' => 'not-a-boolean',
        ])->assertSessionHasErrors('email_enabled');
    }

    public function test_guest_cannot_change_preferences(): void
    {
        $this->post(route('applicant.notification_preferences.update'), ['email_enabled' => '1'])->assertRedirect();
        $this->assertDatabaseCount('applicant_notification_preferences', 0);
    }
}
