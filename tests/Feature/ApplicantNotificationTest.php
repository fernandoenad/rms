<?php

namespace Tests\Feature;

use App\Models\ApplicantNotification;
use App\Models\ApplicantProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicantNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_applicant_can_only_see_and_mark_own_notifications(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $ownerProfile = ApplicantProfile::create(['user_id' => $owner->id]);
        $otherProfile = ApplicantProfile::create(['user_id' => $other->id]);

        $mine = ApplicantNotification::create([
            'applicant_profile_id' => $ownerProfile->id,
            'event_key' => 'test:owner',
            'type' => 'approved',
            'title' => 'My approval',
            'message' => 'Your claim was approved.',
        ]);
        $theirs = ApplicantNotification::create([
            'applicant_profile_id' => $otherProfile->id,
            'event_key' => 'test:other',
            'type' => 'rejected',
            'title' => 'Other private decision',
            'message' => 'Private message.',
        ]);

        $this->actingAs($owner)->get(route('applicant.dashboard'))
            ->assertOk()->assertSee('My approval')->assertSee('1 unread notifications')
            ->assertDontSee('Other private decision');

        $this->actingAs($owner)->post(route('applicant.notifications.read', $theirs))
            ->assertNotFound();
        $this->assertNull($theirs->fresh()->read_at);

        $this->actingAs($owner)->post(route('applicant.notifications.read', $mine))
            ->assertRedirect(route('applicant.dashboard').'#updatesTitle');
        $this->assertNotNull($mine->fresh()->read_at);
    }

    public function test_guest_cannot_read_notifications(): void
    {
        $profile = ApplicantProfile::create(['user_id' => User::factory()->create()->id]);
        $notification = ApplicantNotification::create([
            'applicant_profile_id' => $profile->id,
            'event_key' => 'test:guest',
            'type' => 'info',
            'title' => 'Private',
            'message' => 'Private',
        ]);
        $this->post(route('applicant.notifications.read', $notification))->assertRedirect();
        $this->assertNull($notification->fresh()->read_at);
    }
}
