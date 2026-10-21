<?php

namespace Tests\Feature;

use App\Models\ApplicantApplicationLink;
use App\Models\ApplicantProfile;
use App\Models\Assessment;
use App\Models\Application;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Role;
use App\Models\SkillTest;
use App\Models\SkillTestAttempt;
use App\Models\Template;
use App\Models\User;
use App\Models\Vacancy;
use App\Services\AssessmentDeviceSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class AssessmentDeviceSessionTest extends TestCase
{
    use RefreshDatabase;

    private function requestFor(User $user, string $sessionId): Request
    {
        $request = Request::create('/synthetic-assessment-attempt', 'GET');
        $session = new Store('assessment-device-test', new ArraySessionHandler(120));
        $session->setId($sessionId);
        $request->setLaravelSession($session);
        $request->setUserResolver(fn () => $user);
        return $request;
    }

    private function attempts(): array
    {
        $user = User::factory()->create();
        $template = Template::create([
            'type' => 'Testing', 'template' => json_encode(['Written_Examination' => 20]),
            'status' => 1,
        ]);
        $vacancy = Vacancy::create([
            'cycle' => 2026, 'position_title' => 'Synthetic Assessment Session Test',
            'salary_grade' => 11, 'base_pay' => 27000, 'office_level' => 1,
            'qualifications' => 'Required', 'vacancy' => 1, 'status' => 1,
            'template_id' => $template->id, 'level1_status' => 1, 'level2_status' => 0,
        ]);
        $application = Application::create([
            'vacancy_id' => $vacancy->id, 'application_code' => 'DEVICE-SESSION-TEST',
            'first_name' => 'Synthetic', 'middle_name' => 'A', 'last_name' => 'Applicant',
            'sitio' => 'Sample', 'barangay' => 'Sample', 'municipality' => 'Sample',
            'zip' => 6300, 'age' => 30, 'gender' => 'Female',
            'civil_status' => 'Single', 'religion' => 'None', 'disability' => 'None',
            'ethnic_group' => 'None', 'email' => $user->email, 'phone' => '09000000000',
        ]);
        $profile = ApplicantProfile::create(['user_id' => $user->id]);
        ApplicantApplicationLink::create([
            'applicant_profile_id' => $profile->id, 'application_id' => $application->id,
            'link_method' => 'google_email_match', 'verified_at' => now(),
        ]);
        $exam = Exam::create([
            'vacancy_id' => $vacancy->id, 'title' => 'Written Device Test',
            'code' => 'WRITTEN-SESSION', 'enrollment_key' => 'WRITTEN-SESSION-KEY',
            'start_date' => now()->subHour(), 'end_date' => now()->addHour(),
            'duration' => 40, 'access_mode' => 'all_taken_in',
            'shuffle_items' => true, 'shuffle_options' => true, 'status' => 1,
            'approval_status' => 'approved',
        ]);
        $skills = SkillTest::create([
            'vacancy_id' => $vacancy->id, 'title' => 'Skills Device Test',
            'code' => 'SKILLS-SESSION', 'instructions' => 'Complete the task',
            'expected_output' => 'Completed task',
            'start_date' => now()->subHour(), 'end_date' => now()->addHour(),
            'duration' => 40, 'access_mode' => 'all_taken_in',
            'submission_modes' => ['inline'], 'allowed_extensions' => ['docx'],
            'max_file_size_kb' => 10240, 'ai_scoring' => false,
            'score_release_policy' => 'manual', 'status' => 1,
            'approval_status' => 'approved',
        ]);
        $written = ExamAttempt::create([
            'exam_id' => $exam->id, 'application_id' => $application->id,
            'started_at' => now(), 'expires_at' => now()->addMinutes(30), 'status' => 1,
        ]);
        $skill = SkillTestAttempt::create([
            'skill_test_id' => $skills->id, 'application_id' => $application->id,
            'started_at' => now(), 'expires_at' => now()->addMinutes(30), 'status' => 1,
        ]);

        return [$user, $written, $skill];
    }

    public function test_second_device_cannot_claim_written_or_skills_until_first_device_logs_out(): void
    {
        [$user, $written, $skills] = $this->attempts();
        $first = $this->requestFor($user, 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa');
        $second = $this->requestFor($user, 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb');
        $service = app(AssessmentDeviceSessionService::class);

        foreach (['written' => $written, 'skills' => $skills] as $kind => $attempt) {
            $service->claim($first, $kind, $attempt->id);
            $service->claim($first, $kind, $attempt->id); // Same browser may refresh or autosave.
            try {
                $service->claim($second, $kind, $attempt->id);
                $this->fail('Second device should be blocked for '.$kind);
            } catch (ConflictHttpException $e) {
                $this->assertStringContainsString('Sign out on the first device', $e->getMessage());
            }
        }

        $this->assertSame(0, $service->releaseForSession($second));
        $this->assertSame(2, $service->releaseForSession($first));
        $service->claim($second, 'written', $written->id);
        $service->claim($second, 'skills', $skills->id);
        $this->assertDatabaseCount('assessment_device_sessions', 2);
    }

    public function test_cross_assessment_device_lock_blocks_skills_when_written_open_elsewhere(): void
    {
        [$user, $written, $skills] = $this->attempts();
        $service = app(AssessmentDeviceSessionService::class);
        $first = $this->requestFor($user, '1111111111111111111111111111111111111111');
        $second = $this->requestFor($user, '2222222222222222222222222222222222222222');
        $service->claim($first, 'written', $written->id);

        $this->expectException(ConflictHttpException::class);
        $service->claim($second, 'skills', $skills->id);
    }

    public function test_session_hashes_are_not_raw_browser_session_identifiers(): void
    {
        [$user, $written] = $this->attempts();
        $service = app(AssessmentDeviceSessionService::class);
        $service->claim($this->requestFor($user, 'cccccccccccccccccccccccccccccccccccccccc'), 'written', $written->id);
        $this->assertDatabaseMissing('assessment_device_sessions', [
            'kind' => 'written', 'attempt_id' => $written->id,
            'session_hash' => 'cccccccccccccccccccccccccccccccccccccccc',
        ]);
    }

    public function test_hr_can_audit_recovery_but_other_staff_cannot_reset_a_device_lock(): void
    {
        [$user, $written] = $this->attempts();
        $service = app(AssessmentDeviceSessionService::class);
        $first = $this->requestFor($user, 'eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee');
        $second = $this->requestFor($user, 'ffffffffffffffffffffffffffffffffffffffff');
        $service->claim($first, 'written', $written->id);

        $staff = User::factory()->create();
        Role::create(['user_id' => $staff->id, 'level' => 2, 'status' => 1]);
        $this->actingAs($staff)->post(route('admin.assessment_device_sessions.release', $user), [
            'reason' => 'Non-HR staff must never release applicant assessment locks.',
        ])->assertForbidden();

        $hr = User::factory()->create();
        Role::create(['user_id' => $hr->id, 'level' => 1, 'status' => 1]);
        $this->actingAs($hr)->get(route('admin.assessment_device_sessions.index'))
            ->assertOk()->assertSee('Device Sessions');
        $this->post(route('admin.assessment_device_sessions.release', $user), [
            'reason' => 'Original examination device was lost and identity was independently verified.',
        ])->assertRedirect(route('admin.assessment_device_sessions.index'));

        $this->assertDatabaseHas('applicant_audit_logs', [
            'actor_user_id' => $hr->id,
            'event_type' => 'assessment_device_sessions_reset_by_hr',
        ]);
        $this->assertSame(0, $service->releaseForSession($first));
        $service->claim($second, 'written', $written->id);
    }

    public function test_completed_attempt_never_acquires_a_live_session_lock(): void
    {
        [$user, $written, $skills] = $this->attempts();
        $written->update(['status' => 2]);
        $skills->update(['status' => 2]);
        $req = $this->requestFor($user, 'dddddddddddddddddddddddddddddddddddddddd');
        app(AssessmentDeviceSessionService::class)->claim($req, 'written', $written->id);
        app(AssessmentDeviceSessionService::class)->claim($req, 'skills', $skills->id);
        $this->assertDatabaseCount('assessment_device_sessions', 0);
    }

    public function test_real_logout_releases_current_authenticated_browser_locks(): void
    {
        [$user, $written, $skills] = $this->attempts();
        Assessment::create([
            'application_id' => $written->application_id,
            'template_id' => $written->exam->vacancy->template_id,
            'assessment' => json_encode(['Written_Examination' => 0]),
            'status' => 2,
        ]);

        $this->actingAs($user)->get(route('guest.assessments.attempts.time_status', $written))
            ->assertOk();
        $this->assertDatabaseHas('assessment_device_sessions', [
            'kind' => 'written', 'attempt_id' => $written->id, 'released_at' => null,
        ]);
        $this->post(route('logout'))->assertRedirect('/');
        $this->assertDatabaseMissing('assessment_device_sessions', [
            'kind' => 'written', 'attempt_id' => $written->id, 'released_at' => null,
        ]);
    }

    public function test_attempt_routes_check_both_ownership_and_session_before_controller(): void
    {
        [$user, $written, $skills] = $this->attempts();
        $stranger = User::factory()->create();
        $this->actingAs($stranger)
            ->get(route('guest.assessments.attempts.time_status', $written))
            ->assertForbidden();
        $this->get(route('guest.skills.attempts.time_status', $skills))->assertForbidden();
        $this->assertDatabaseCount('assessment_device_sessions', 0);
    }
}
