<?php

namespace Tests\Feature;

use App\Models\ApplicantIntegrityFlag;
use App\Models\Application;
use App\Models\Assessment;
use App\Models\AssessmentAttemptEvent;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\IntegrityRuleSet;
use App\Models\Role;
use App\Models\Template;
use App\Models\User;
use App\Models\Vacancy;
use App\Services\ApplicantIntegrityFlagService;
use App\Support\AssessmentHiddenDuration;
use App\Support\RqaIntegrityRemarks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicantIntegrityFlagTest extends TestCase
{
    use RefreshDatabase;

    private function hr(int $level = 1): User
    {
        $user = User::factory()->create();
        Role::create(['user_id' => $user->id, 'level' => $level, 'status' => 1]);
        return $user;
    }

    private function makeApplication(): array
    {
        $template = Template::create([
            'type' => 'Integrity Review Test', 'status' => 1,
            'template' => json_encode(['Written_Examination' => 20, 'Interview' => 80, 'Remarks' => 'Remarks']),
        ]);
        $vacancy = Vacancy::create([
            'cycle' => 2026, 'position_title' => 'Review Test Vacancy',
            'salary_grade' => 11, 'base_pay' => 27000, 'office_level' => 1,
            'qualifications' => 'Required', 'vacancy' => 1, 'status' => 1,
            'template_id' => $template->id, 'level1_status' => 1, 'level2_status' => 0,
        ]);
        $app = Application::create([
            'vacancy_id' => $vacancy->id,
            'application_code' => 'FLAG-'.uniqid(),
            'first_name' => 'Review', 'middle_name' => 'A', 'last_name' => 'Applicant',
            'sitio' => 'Test', 'barangay' => 'Test', 'municipality' => 'Test', 'zip' => 6300,
            'age' => 30, 'gender' => 'Male', 'civil_status' => 'Single', 'religion' => 'N/A',
            'disability' => 'None', 'ethnic_group' => 'N/A',
            'email' => uniqid().'@example.test', 'phone' => '09000000000',
        ]);
        $assessment = Assessment::create([
            'application_id' => $app->id, 'template_id' => $template->id,
            'assessment' => json_encode(['Written_Examination'=>16,'Interview'=>65,'Remarks'=>'Qualified']),
            'score' => 81, 'status' => 3,
        ]);
        $exam = Exam::create([
            'vacancy_id' => $vacancy->id, 'title' => 'Visibility Test', 'code' => 'V-'.uniqid(),
            'enrollment_key' => 'K-'.uniqid(),
            'start_date' => now()->subHour(), 'end_date' => now()->addHour(),
            'duration' => 30, 'access_mode' => 'all_taken_in',
            'shuffle_items' => false, 'shuffle_options' => false,
            'status' => 1, 'approval_status' => 'approved',
        ]);
        $attempt = ExamAttempt::create([
            'exam_id' => $exam->id, 'application_id' => $app->id,
            'status' => 2, 'started_at' => now()->subMinutes(30),
            'ended_at' => now()->subMinutes(15), 'percentage' => 80,
            'total_items' => 20, 'correct_answers' => 16,
        ]);
        return compact('vacancy','app','assessment','attempt');
    }

    private function rule(Vacancy $vacancy): IntegrityRuleSet
    {
        return app(ApplicantIntegrityFlagService::class)->createRuleSet($vacancy->id, [
            'switch_count' => 5, 'total_hidden_seconds' => 60,
            'average_hidden_seconds' => 30, 'longest_hidden_seconds' => 120,
            'authority_reference' => 'HRMPSB Screening Protocol 2026',
        ], (int) auth()->id());
    }

    public function test_duration_summary_calculates_completed_intervals_and_longest_without_guessing_unpaired_duration(): void
    {
        $event = fn ($type, $stamp) => (object)[
            'event_type' => $type, 'event_at' => now()->parse($stamp),
        ];
        $m = AssessmentHiddenDuration::summarize([
            $event('tab_hidden','2026-10-21 09:00:00'),
            $event('tab_hidden','2026-10-21 09:00:01'), // duplicate event is not a second switch
            $event('tab_visible','2026-10-21 09:00:08'),
            $event('tab_hidden','2026-10-21 09:01:00'),
            $event('tab_visible','2026-10-21 09:02:12'),
            $event('tab_hidden','2026-10-21 09:05:00'),
        ]);
        $this->assertSame(80, $m['seconds']);
        $this->assertSame(2, $m['completed_intervals']);
        $this->assertSame(3, $m['hidden_event_count']);
        $this->assertSame(72, $m['longest_seconds']);
        $this->assertSame(40.0, $m['average_seconds']);
        $this->assertSame(1, $m['unpaired_hidden_intervals']);
    }

    public function test_auto_detection_is_pending_and_does_not_change_score_or_assessment_remarks(): void
    {
        $f = $this->makeApplication();
        $this->actingAs($this->hr());
        $rule = $this->rule($f['vacancy']);
        AssessmentAttemptEvent::create([
            'exam_attempt_id' => $f['attempt']->id,
            'event_type' => 'tab_hidden', 'event_at' => now()->subMinutes(12),
        ]);
        AssessmentAttemptEvent::create([
            'exam_attempt_id' => $f['attempt']->id,
            'event_type' => 'tab_visible', 'event_at' => now()->subMinutes(10),
        ]);
        $service = app(ApplicantIntegrityFlagService::class);
        $result = $service->scan($rule);
        $this->assertSame(1, $result['review_cases']);
        $this->assertSame(0, $result['already_recorded']);
        $flag = ApplicantIntegrityFlag::firstOrFail();
        $this->assertSame('pending', $flag->status);
        $this->assertContains('extended_average_hidden_time', $flag->signals);
        $this->assertSame([], $service->confirmedForVacancy($f['vacancy']->id));
        $this->assertSame(81.0, (float) $f['assessment']->fresh()->score);
        $this->assertSame('Qualified', json_decode((string) $f['assessment']->fresh()->assessment,true)['Remarks']);
        $again = $service->scan($rule);
        $this->assertSame(0, $again['review_cases']);
        $this->assertSame(1, $again['already_recorded']);
    }

    public function test_authorized_confirm_then_clear_controls_internal_marker_and_leaves_scores_intact(): void
    {
        $f = $this->makeApplication();
        $hr = $this->hr();
        $this->actingAs($hr);
        $flag = app(ApplicantIntegrityFlagService::class)->addManual(
            $f['app'], $hr->id, 'Proctor requested individual review of this attempt.'
        );
        $this->assertSame('pending', $flag->status);
        $this->assertSame([], app(ApplicantIntegrityFlagService::class)->confirmedForVacancy($f['vacancy']->id));

        $this->post(route('admin.integrity_flags.decide', $flag), [
            'decision' => 'confirmed',
            'reason' => 'Board reviewer authorizes confidential RQA verification notation.',
        ])->assertRedirect();
        $this->assertTrue((bool) (app(ApplicantIntegrityFlagService::class)
            ->confirmedForVacancy($f['vacancy']->id)[$f['app']->id] ?? false));
        $this->assertSame('Qualified *', RqaIntegrityRemarks::format('Qualified', true));
        $this->assertSame('Qualified *', RqaIntegrityRemarks::format('Qualified *', true));

        $this->post(route('admin.integrity_flags.decide', $flag), [
            'decision' => 'cleared',
            'reason' => 'Subsequent review found a documented innocent interruption.',
        ])->assertRedirect();
        $this->assertSame([], app(ApplicantIntegrityFlagService::class)->confirmedForVacancy($f['vacancy']->id));
        $this->assertCount(3, $flag->fresh()->actions);
        $this->assertSame(81.0, (float) $f['assessment']->fresh()->score);
    }

    public function test_private_car_rqa_shows_marker_only_after_hr_confirmation(): void
    {
        $f = $this->makeApplication();
        $hr = $this->hr();
        $this->actingAs($hr);
        $service = app(ApplicantIntegrityFlagService::class);
        $flag = $service->addManual($f['app'], $hr->id, 'Proctor requests a documented technical review.');

        $url = route('admin.applications.vacancy.show.carview', $f['vacancy']);
        $this->get($url)->assertOk()->assertDontSee('Qualified *');

        $service->review($flag, 'confirmed', 'This application requires confidential HRMPSB verification.', $hr->id);
        $this->get($url)->assertOk()->assertSee('Qualified *');

        $service->review($flag, 'cleared', 'Documentation confirms there was no actionable concern.', $hr->id);
        $this->get($url)->assertOk()->assertDontSee('Qualified *');
    }

    public function test_all_internal_rqa_layouts_use_confirmed_only_marker(): void
    {
        $variants = [
            'carview', 'carviewnt', 'carview2', 'carview2nt',
            'carview3', 'carview3nt', 'carview4', 'carview4nt',
            'carview5nt', 'careerview', 'careerviewb',
        ];
        foreach ($variants as $variant) {
            $blade = file_get_contents(resource_path('views/admin/applications/list/'.$variant.'.blade.php'));
            $this->assertStringContainsString('RqaIntegrityRemarks::format', $blade, $variant);
            $this->assertStringContainsString('confirmedForVacancy', $blade, $variant);
        }
        $public = file_get_contents(resource_path('views/guest/rqas/carview.blade.php'));
        $this->assertStringNotContainsString('RqaIntegrityRemarks::format', $public);
        $this->assertStringNotContainsString('confirmedForVacancy', $public);
    }

    public function test_non_hr_staff_cannot_read_or_change_private_flags(): void
    {
        $f = $this->makeApplication();
        $this->actingAs($this->hr(2));
        $this->getJson(route('admin.integrity_flags.index', ['vacancy_id'=>$f['vacancy']->id]))
            ->assertForbidden();
        $this->postJson(route('admin.integrity_flags.rules'), ['vacancy_id'=>$f['vacancy']->id])
            ->assertForbidden();
        $this->getJson(route('admin.integrity_flags.export',['vacancy_id'=>$f['vacancy']->id]))
            ->assertForbidden();
    }

    public function test_rule_changes_are_versioned_and_older_decisions_remain(): void
    {
        $f = $this->makeApplication();
        $hr = $this->hr();
        $this->actingAs($hr);
        $first = $this->rule($f['vacancy']);
        $second = app(ApplicantIntegrityFlagService::class)->createRuleSet($f['vacancy']->id, [
            'switch_count'=>10,'total_hidden_seconds'=>90,
            'average_hidden_seconds'=>45,'longest_hidden_seconds'=>180,
            'authority_reference'=>'HRMPSB Revised Screening Protocol 2026',
        ], $hr->id);
        $this->assertFalse((bool)$first->fresh()->is_active);
        $this->assertSame(2, $second->version);
    }
}
