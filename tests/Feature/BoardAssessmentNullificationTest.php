<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Assessment;
use App\Models\AssessmentGroup;
use App\Models\AssessmentNullificationBatch;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Role;
use App\Models\SkillTest;
use App\Models\SkillTestAttempt;
use App\Models\SkillTestGroup;
use App\Models\Template;
use App\Models\User;
use App\Models\Vacancy;
use App\Services\AssessmentIntegrityService;
use App\Services\AssessmentNullificationService;
use App\Services\AssessmentScoreSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BoardAssessmentNullificationTest extends TestCase
{
    use RefreshDatabase;

    private function staff(int $level = 1): User
    {
        $user = User::factory()->create();
        Role::create(['user_id' => $user->id, 'level' => $level, 'status' => 1]);
        return $user;
    }

    private function fixture(): array
    {
        $template = Template::create([
            'type' => 'Board Nullification Regression',
            'template' => json_encode([
                'Written_Examination' => 20, 'Skills_Test' => 20, 'Interview' => 60,
            ]),
            'status' => 1,
        ]);
        $vacancy = Vacancy::create([
            'cycle' => 2026, 'position_title' => 'Board Test Position',
            'salary_grade' => 11, 'base_pay' => 27000,
            'office_level' => 1, 'qualifications' => 'Required',
            'vacancy' => 1, 'status' => 1,
            'template_id' => $template->id,
            'level1_status' => 1, 'level2_status' => 0,
        ]);
        $app = Application::create([
            'vacancy_id' => $vacancy->id,
            'application_code' => 'BOARD-'.uniqid(),
            'first_name' => 'Synthetic', 'middle_name' => 'A', 'last_name' => 'Applicant',
            'sitio' => 'Test', 'barangay' => 'Test', 'municipality' => 'Test',
            'zip' => 6300, 'age' => 30, 'gender' => 'Male',
            'civil_status' => 'Single', 'religion' => 'N/A', 'disability' => 'None',
            'ethnic_group' => 'N/A', 'email' => uniqid().'@example.test', 'phone' => '09000000000',
        ]);
        $assessment = Assessment::create([
            'application_id' => $app->id, 'template_id' => $template->id,
            'assessment' => json_encode([
                'Written_Examination' => 15, 'Skills_Test' => 18, 'Interview' => 30,
            ]),
            'score' => 63, 'status' => 3,
        ]);
        $written = AssessmentGroup::create([
            'vacancy_id' => $vacancy->id, 'title' => 'Written Cycle',
            'code' => 'BOARD-W-'.uniqid(), 'expected_sets' => 1, 'status' => 1,
            'score_release_policy' => 'immediate', 'assessment_score_key' => 'Written_Examination',
        ]);
        $skills = SkillTestGroup::create([
            'vacancy_id' => $vacancy->id, 'title' => 'Skills Cycle',
            'code' => 'BOARD-S-'.uniqid(), 'expected_sets' => 1, 'status' => 1,
            'score_release_policy' => 'immediate', 'assessment_score_key' => 'Skills_Test',
        ]);
        $exam = Exam::create([
            'vacancy_id' => $vacancy->id, 'assessment_group_id' => $written->id,
            'title' => 'Written Set A', 'code' => 'BOARD-W-A-'.uniqid(),
            'set_code' => 'A', 'enrollment_key' => 'BOARD-KEY-'.uniqid(),
            'start_date' => now()->subHour(), 'end_date' => now()->addHour(),
            'duration' => 30, 'access_mode' => 'all_taken_in',
            'shuffle_items' => true, 'shuffle_options' => true,
            'status' => 1, 'approval_status' => 'approved',
        ]);
        $writtenAttempt = ExamAttempt::create([
            'application_id' => $app->id, 'exam_id' => $exam->id,
            'started_at' => now()->subMinutes(30), 'ended_at' => now()->subMinute(),
            'status' => 2, 'correct_answers' => 15, 'total_items' => 20,
            'percentage' => 75, 'scored_at' => now()->subMinute(),
        ]);
        $skillTest = SkillTest::create([
            'vacancy_id' => $vacancy->id, 'skill_test_group_id' => $skills->id,
            'title' => 'Skills Set A', 'code' => 'BOARD-S-A-'.uniqid(),
            'set_code' => 'A', 'instructions' => 'Perform task',
            'expected_output' => 'Completed task',
            'start_date' => now()->subHour(), 'end_date' => now()->addHour(),
            'duration' => 30, 'access_mode' => 'all_taken_in',
            'submission_modes' => ['inline'], 'allowed_extensions' => ['docx'],
            'max_file_size_kb' => 10240, 'ai_scoring' => false, 'status' => 1,
            'score_release_policy' => 'immediate', 'approval_status' => 'approved',
        ]);
        $skillsAttempt = SkillTestAttempt::create([
            'application_id' => $app->id, 'skill_test_id' => $skillTest->id,
            'started_at' => now()->subMinutes(30), 'submitted_at' => now()->subMinute(),
            'status' => 2, 'final_score' => 90, 'evaluated_at' => now()->subMinute(),
        ]);
        return compact('vacancy', 'app', 'assessment', 'written', 'skills', 'writtenAttempt', 'skillsAttempt');
    }

    private function input(array $f): array
    {
        $scope = app(AssessmentNullificationService::class)->preview(
            $f['vacancy']->id, $f['written']->id, $f['skills']->id
        );
        return [
            'vacancy_id' => $f['vacancy']->id,
            'written_group_id' => $f['written']->id,
            'skills_group_id' => $f['skills']->id,
            'scope_fingerprint' => $scope['fingerprint'],
            'board_reference' => 'HRMPSB Resolution 2026-101',
            'board_date' => now()->toDateString(),
            'reason' => 'Board nullification applies uniformly to the selected examination cycle.',
            'confirmation' => 'NULLIFY',
        ];
    }

    public function test_hr_can_preview_nullify_and_export_without_destroying_original_results(): void
    {
        $f = $this->fixture();
        $this->actingAs($this->staff());
        $input = $this->input($f);
        $this->get(route('admin.assessment_nullifications.index', [
            'vacancy_id' => $f['vacancy']->id,
            'written_group_id' => $f['written']->id,
            'skills_group_id' => $f['skills']->id,
        ]))->assertOk()->assertSee('Board-Authorized Assessment Nullification')
            ->assertSee('Apply Board Nullification');

        $this->post(route('admin.assessment_nullifications.store'), $input)
            ->assertRedirect(route('admin.assessment_nullifications.index'));

        $batch = AssessmentNullificationBatch::firstOrFail();
        $this->assertSame('active', $batch->status);
        $this->assertCount(2, $batch->items);
        $scores = json_decode($f['assessment']->fresh()->assessment, true);
        $this->assertSame(0.0, (float) $scores['Written_Examination']);
        $this->assertSame(0.0, (float) $scores['Skills_Test']);
        $this->assertSame(30.0, (float) $scores['Interview']);
        $this->assertSame(30.0, (float) $f['assessment']->fresh()->score);
        $this->assertSame(3, (int) $f['assessment']->fresh()->status);
        $this->assertTrue((bool) $f['written']->fresh()->is_paused);
        $this->assertTrue((bool) $f['skills']->fresh()->is_paused);
        $this->assertSame(75.0, (float) $f['writtenAttempt']->fresh()->percentage);
        $this->assertSame(90.0, (float) $f['skillsAttempt']->fresh()->final_score);

        $this->get(route('admin.assessment_nullifications.export', $batch))
            ->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_synchronization_and_score_integrity_cannot_resurrect_nullified_scores(): void
    {
        $f = $this->fixture();
        $this->actingAs($this->staff());
        app(AssessmentNullificationService::class)->nullify($this->input($f), (int) auth()->id());
        $sync = app(AssessmentScoreSyncService::class);

        $this->assertFalse($sync->syncWrittenAttempt($f['writtenAttempt']));
        $this->assertFalse($sync->syncSkillAttempt($f['skillsAttempt']));
        $this->assertSame(0, $sync->syncWrittenGroup($f['written']->fresh())['skipped']);
        $this->assertSame(0, $sync->syncSkillGroup($f['skills']->fresh())['skipped']);
        $this->assertCount(0, app(AssessmentIntegrityService::class)->anomalies());
        $this->assertSame(30.0, (float) $f['assessment']->fresh()->score);
    }

    public function test_board_restoration_requires_reference_and_preserves_other_scores(): void
    {
        $f = $this->fixture();
        $this->actingAs($this->staff());
        $batch = app(AssessmentNullificationService::class)->nullify($this->input($f), (int) auth()->id());

        $this->post(route('admin.assessment_nullifications.restore', $batch), [
            'restoration_reference' => 'Supplemental Board Resolution 2026-102',
            'restoration_reason' => 'New decision authorizes restoration of previous criterion values.',
            'confirmation' => 'RESTORE',
        ])->assertRedirect();

        $this->assertSame('restored', $batch->fresh()->status);
        $this->assertSame(63.0, (float) $f['assessment']->fresh()->score);
        $this->assertTrue((bool) $f['written']->fresh()->is_paused);
        $this->assertTrue((bool) $f['skills']->fresh()->is_paused);
    }

    public function test_an_unauthorized_staff_member_cannot_preview_apply_or_export(): void
    {
        $f = $this->fixture();
        $this->actingAs($this->staff(2));
        $this->getJson(route('admin.assessment_nullifications.index'))->assertForbidden();
        $this->postJson(route('admin.assessment_nullifications.store'), [
            'vacancy_id' => $f['vacancy']->id,
        ])->assertForbidden();
    }

    public function test_duplicate_action_and_stale_preview_fail_closed(): void
    {
        $f = $this->fixture();
        $this->actingAs($this->staff());
        $input = $this->input($f);
        app(AssessmentNullificationService::class)->nullify($input, (int) auth()->id());
        $this->post(route('admin.assessment_nullifications.store'), $input)->assertSessionHasErrors();
        $this->assertSame(1, AssessmentNullificationBatch::count());
    }

    public function test_manual_score_edit_cannot_override_a_board_nullification(): void
    {
        $f = $this->fixture();
        $this->actingAs($this->staff());
        app(AssessmentNullificationService::class)->nullify($this->input($f), (int) auth()->id());
        $service = app(AssessmentNullificationService::class);
        try {
            $service->updateManualAssessment($f['assessment'], [
                'Written_Examination' => 20, 'Skills_Test' => 0, 'Interview' => 30,
            ]);
            $this->fail('Tampered official scores should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('Written_Examination', $exception->errors());
        }

        $service->updateManualAssessment($f['assessment'], [
            'Written_Examination' => 0, 'Skills_Test' => 0, 'Interview' => 35,
        ]);
        $this->assertSame(35.0, (float) $f['assessment']->fresh()->score);
        $this->assertSame(3, (int) $f['assessment']->fresh()->status);
    }

    public function test_unfinished_attempt_prevents_bulk_nullification(): void
    {
        $f = $this->fixture();
        $f['writtenAttempt']->update(['status' => 1, 'ended_at' => null]);
        $this->actingAs($this->staff());
        $this->post(route('admin.assessment_nullifications.store'), [
            'vacancy_id' => $f['vacancy']->id,
            'written_group_id' => $f['written']->id,
            'skills_group_id' => $f['skills']->id,
            'scope_fingerprint' => str_repeat('a', 64),
            'board_reference' => 'Test memo',
            'board_date' => now()->toDateString(),
            'reason' => 'Reclassifying a set that is currently in progress is unsafe.',
            'confirmation' => 'NULLIFY',
        ])->assertSessionHasErrors();
        $this->assertDatabaseCount('assessment_nullification_batches', 0);
    }
}
