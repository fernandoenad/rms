<?php

namespace Tests\Feature;

use App\Models\ApplicantApplicationLink;
use App\Models\ApplicantProfile;
use App\Models\ApplicantNotification;
use App\Models\User;
use App\Models\Application;
use App\Services\Applicant\HrApplicationStatusNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrApplicationStatusNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unverified_and_unlinked_applicants_never_get_screening_notices(): void
    {
        $application = new Application;
        $application->id = 321;
        $application->application_code = 'TEST-321';
        app(HrApplicationStatusNotifier::class)->changed($application, 22, 'qualified');
        app(HrApplicationStatusNotifier::class)->changed($application, 23, 'disqualified');
        $this->assertDatabaseCount('applicant_notifications', 0);
    }

    public function test_invalid_or_preliminary_event_does_not_create_a_notification(): void
    {
        $application = new Application;
        $application->id = 321;
        $application->application_code = 'TEST-321';
        app(HrApplicationStatusNotifier::class)->changed($application, 22, 'pending');
        $this->assertDatabaseCount('applicant_notifications', 0);
    }
}
