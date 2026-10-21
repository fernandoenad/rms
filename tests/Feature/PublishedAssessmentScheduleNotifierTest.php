<?php

namespace Tests\Feature;

use App\Models\ApplicantApplicationLink;
use App\Models\ApplicantNotification;
use App\Models\ApplicantProfile;
use App\Models\Application;
use App\Models\Exam;
use App\Models\ExamAssignment;
use App\Models\SkillTest;
use App\Models\SkillTestAssignment;
use App\Models\Template;
use App\Models\User;
use App\Models\Vacancy;
use App\Services\Applicant\PublishedAssessmentScheduleNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublishedAssessmentScheduleNotifierTest extends TestCase
{
    use RefreshDatabase;

    private function vacancy(): Vacancy
    {
        $template = Template::create([
            'type' => 'Published Schedule Notification Test',
            'template' => json_encode(['Written_Examination' => 20, 'Skills_Test' => 20, 'Interview' => 60, 'Remarks' => 'Remarks']),
            'status' => 1,
        ]);

        return Vacancy::create([
            'cycle' => 2026, 'position_title' => 'Schedule Test Position',
            'salary_grade' => 11, 'base_pay' => 27000, 'office_level' => 1,
            'qualifications' => 'Required', 'vacancy' => 1, 'status' => 1,
            'template_id' => $template->id, 'level1_status' => 1, 'level2_status' => 0,
        ]);
    }

    private function application(Vacancy $vacancy): Application
    {
        return Application::create([
            'vacancy_id' => $vacancy->id, 'application_code' => 'NOTIFY-'.uniqid(),
            'first_name' => 'Schedule', 'middle_name' => 'A', 'last_name' => 'Applicant',
            'sitio' => 'Sample', 'barangay' => 'Barangay', 'municipality' => 'Municipality',
            'zip' => 6300, 'age' => 30, 'gender' => 'Female', 'civil_status' => 'Single',
            'religion' => 'N/A', 'disability' => 'None', 'ethnic_group' => 'N/A',
            'email' => uniqid().'@example.test', 'phone' => '09000000000',
        ]);
    }

    private function link(Application $application, bool $verified = true): ApplicantProfile
    {
        $profile = ApplicantProfile::create(['user_id' => User::factory()->create()->id]);
        ApplicantApplicationLink::create([
            'applicant_profile_id' => $profile->id,
            'application_id' => $application->id,
            'link_method' => 'hr_approved_claim',
            'verified_at' => $verified ? now() : null,
        ]);
        return $profile;
    }

    private function written(Vacancy $vacancy, int $status = 1, string $approval = 'approved'): Exam
    {
        return Exam::create([
            'vacancy_id' => $vacancy->id, 'title' => 'Written Assessment Set',
            'code' => 'WRITTEN-'.uniqid(), 'enrollment_key' => 'KEY-'.uniqid(),
            'start_date' => '2026-11-01 09:00:00', 'end_date' => '2026-11-01 10:00:00',
            'duration' => 60, 'access_mode' => 'all_taken_in',
            'shuffle_items' => true, 'shuffle_options' => true,
            'status' => $status, 'approval_status' => $approval,
        ]);
    }

    private function skills(Vacancy $vacancy, int $status = 1, string $approval = 'approved'): SkillTest
    {
        return SkillTest::create([
            'vacancy_id' => $vacancy->id, 'title' => 'Skills Assessment Set',
            'code' => 'SKILLS-'.uniqid(), 'instructions' => 'Complete the assigned task',
            'expected_output' => 'A completed performance task',
            'start_date' => '2026-11-02 09:00:00', 'end_date' => '2026-11-02 10:00:00',
            'duration' => 60, 'access_mode' => 'all_taken_in',
            'submission_modes' => ['inline'], 'allowed_extensions' => ['docx'],
            'max_file_size_kb' => 10240, 'ai_scoring' => false, 'status' => $status,
            'score_release_policy' => 'manual', 'approval_status' => $approval,
        ]);
    }

    public function test_only_verified_assigned_applicants_receive_written_schedule_notice(): void
    {
        $vacancy = $this->vacancy();
        $written = $this->written($vacancy);
        $verified = $this->application($vacancy);
        $profile = $this->link($verified);
        $unverified = $this->application($vacancy);
        $this->link($unverified, false);
        $unassigned = $this->application($vacancy);
        $this->link($unassigned);

        ExamAssignment::create(['exam_id' => $written->id, 'application_id' => $verified->id]);
        ExamAssignment::create(['exam_id' => $written->id, 'application_id' => $unverified->id]);

        $notifier = app(PublishedAssessmentScheduleNotifier::class);
        $notifier->written($written);

        $this->assertDatabaseCount('applicant_notifications', 1);
        $notice = ApplicantNotification::firstOrFail();
        $this->assertSame($profile->id, (int) $notice->applicant_profile_id);
        $this->assertSame('assessment_schedule', $notice->type);
        $this->assertStringNotContainsString('2026-11-01', (string) $notice->message);
        $this->assertStringNotContainsString($verified->getFullname(), (string) $notice->message);

        // Repeated publication of the same set and schedule is idempotent.
        $notifier->written($written);
        $this->assertDatabaseCount('applicant_notifications', 1);
    }

    public function test_draft_unapproved_and_unscheduled_written_sets_do_not_notify(): void
    {
        $vacancy = $this->vacancy();
        $app = $this->application($vacancy);
        $this->link($app);
        $notifier = app(PublishedAssessmentScheduleNotifier::class);

        $draft = $this->written($vacancy, 0);
        $unapproved = $this->written($vacancy, 1, 'pending');
        $unscheduled = $this->written($vacancy);
        $unscheduled->update(['start_date' => null]);
        foreach ([$draft, $unapproved, $unscheduled] as $exam) {
            ExamAssignment::create(['exam_id' => $exam->id, 'application_id' => $app->id]);
            $notifier->written($exam);
        }
        $this->assertDatabaseCount('applicant_notifications', 0);

        // Publishing an approved draft with a confirmed assignment sends the notice.
        $draft->update(['status' => 1]);
        $notifier->written($draft);
        $this->assertDatabaseCount('applicant_notifications', 1);
    }

    public function test_skills_notice_requires_approved_published_scheduled_and_assigned_set(): void
    {
        $vacancy = $this->vacancy();
        $app = $this->application($vacancy);
        $profile = $this->link($app);
        $skill = $this->skills($vacancy, 0);
        SkillTestAssignment::create(['skill_test_id' => $skill->id, 'application_id' => $app->id]);
        $notifier = app(PublishedAssessmentScheduleNotifier::class);

        $notifier->skills($skill);
        $this->assertDatabaseCount('applicant_notifications', 0);

        $skill->update(['status' => 1, 'approval_status' => 'pending']);
        $notifier->skills($skill);
        $this->assertDatabaseCount('applicant_notifications', 0);

        $skill->update(['approval_status' => 'approved']);
        $notifier->skills($skill);
        $notifier->skills($skill);
        $this->assertDatabaseCount('applicant_notifications', 1);
        $this->assertSame($profile->id, (int) ApplicantNotification::firstOrFail()->applicant_profile_id);

        // A genuinely changed, newly published schedule generates a new notice.
        $skill->update(['status' => 0, 'start_date' => '2026-11-03 09:00:00', 'end_date' => '2026-11-03 10:00:00']);
        $notifier->skills($skill);
        $this->assertDatabaseCount('applicant_notifications', 1);

        $skill->update(['status' => 1]);
        $notifier->skills($skill);
        $this->assertDatabaseCount('applicant_notifications', 2);
    }

    public function test_written_schedule_republication_and_changed_dates_use_distinct_event_keys(): void
    {
        $vacancy = $this->vacancy();
        $app = $this->application($vacancy);
        $this->link($app);
        $exam = $this->written($vacancy);
        ExamAssignment::create(['exam_id' => $exam->id, 'application_id' => $app->id]);
        $notifier = app(PublishedAssessmentScheduleNotifier::class);

        $notifier->written($exam);
        $exam->update(['status' => 0]);
        $notifier->written($exam);
        $exam->update(['status' => 1]);
        $notifier->written($exam);
        $this->assertDatabaseCount('applicant_notifications', 1);

        $exam->update(['status' => 0, 'start_date' => '2026-11-04 09:00:00',
            'end_date' => '2026-11-04 10:00:00']);
        $notifier->written($exam);
        $exam->update(['status' => 1]);
        $notifier->written($exam);
        $this->assertDatabaseCount('applicant_notifications', 2);
        $this->assertSame(2, ApplicantNotification::distinct('event_key')->count('event_key'));
    }

    public function test_assignment_from_unrelated_vacancy_cannot_leak_notification(): void
    {
        $firstVacancy = $this->vacancy();
        $otherVacancy = $this->vacancy();
        $app = $this->application($otherVacancy);
        $this->link($app);
        $exam = $this->written($firstVacancy);
        ExamAssignment::create(['exam_id' => $exam->id, 'application_id' => $app->id]);

        app(PublishedAssessmentScheduleNotifier::class)->written($exam);
        $this->assertDatabaseCount('applicant_notifications', 0);
    }

    public function test_unassigned_or_unverified_skills_does_not_notify(): void
    {
        $vacancy = $this->vacancy();
        $skill = $this->skills($vacancy);
        $app = $this->application($vacancy);
        $this->link($app, false);
        SkillTestAssignment::create(['skill_test_id' => $skill->id, 'application_id' => $app->id]);

        app(PublishedAssessmentScheduleNotifier::class)->skills($skill);
        $this->assertDatabaseCount('applicant_notifications', 0);
    }
}
