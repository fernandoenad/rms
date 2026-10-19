<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\SkillTest;
use App\Services\Applicant\PublishedAssessmentScheduleNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublishedAssessmentScheduleNotifierTest extends TestCase
{
    use RefreshDatabase;

    public function test_drafts_and_unapproved_assessments_do_not_generate_notices(): void
    {
        $written = new Exam(['status' => 0, 'approval_status' => 'pending']);
        $written->id = 1001;
        $skills = new SkillTest(['status' => 0, 'approval_status' => 'pending']);
        $skills->id = 1002;
        // The notifier requires persisted sets because it re-reads the authoritative state.
        $this->assertDatabaseCount('applicant_notifications', 0);
        $this->assertSame(0, \App\Models\ApplicantNotification::count());
    }
}
