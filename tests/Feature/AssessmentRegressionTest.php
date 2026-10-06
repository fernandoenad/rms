<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Assessment;
use App\Models\AssessmentGroup;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\SkillTest;
use App\Models\SkillTestAttempt;
use App\Models\SkillTestGroup;
use App\Models\Template;
use App\Models\Vacancy;
use App\Services\AssessmentScoreSyncService;
use App\Services\EquivalentSetScheduleResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AssessmentRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_written_equivalent_set_resolver_selects_current_and_next_by_schedule(): void
    {
        Carbon::setTestNow('2026-10-07 09:00:00');

        [$vacancy, $application] = $this->makeTakenInApplication();

        $group = AssessmentGroup::create([
            'vacancy_id' => $vacancy->id,
            'title' => 'Written Group',
            'code' => 'WG-1',
            'expected_sets' => 3,
            'status' => 1,
            'score_release_policy' => 'manual',
        ]);

        $setA = $this->makeExam($vacancy, $group, 'A', '2026-10-07 08:00:00', '2026-10-07 10:00:00');
        $setB = $this->makeExam($vacancy, $group, 'B', '2026-10-07 10:00:00', '2026-10-07 12:00:00');
        $this->makeExam($vacancy, $group, 'C', '2026-10-08 08:00:00', '2026-10-08 10:00:00');

        $resolver = app(EquivalentSetScheduleResolver::class);

        $this->assertSame($setA->id, $resolver->currentWrittenSet($group->id, $application)?->id);
        $this->assertSame($setB->id, $resolver->nextWrittenSet($group->id, $application)?->id);
    }

    public function test_overlapping_written_sets_use_latest_opened_set(): void
    {
        Carbon::setTestNow('2026-10-07 09:30:00');

        [$vacancy, $application] = $this->makeTakenInApplication();

        $group = AssessmentGroup::create([
            'vacancy_id' => $vacancy->id,
            'title' => 'Written Group',
            'code' => 'WG-2',
            'expected_sets' => 2,
            'status' => 1,
            'score_release_policy' => 'manual',
        ]);

        $this->makeExam($vacancy, $group, 'A', '2026-10-07 08:00:00', '2026-10-07 11:00:00');
        $setB = $this->makeExam($vacancy, $group, 'B', '2026-10-07 09:00:00', '2026-10-07 10:00:00');

        $resolver = app(EquivalentSetScheduleResolver::class);

        $this->assertSame($setB->id, $resolver->currentWrittenSet($group->id, $application)?->id);
    }

    public function test_paused_written_group_has_no_current_or_next_set(): void
    {
        Carbon::setTestNow('2026-10-07 09:00:00');

        [$vacancy, $application] = $this->makeTakenInApplication();

        $group = AssessmentGroup::create([
            'vacancy_id' => $vacancy->id,
            'title' => 'Paused Written Group',
            'code' => 'WG-PAUSED',
            'expected_sets' => 2,
            'status' => 1,
            'score_release_policy' => 'manual',
        ]);
        $group->forceFill(['is_paused' => true])->save();

        $this->makeExam($vacancy, $group, 'A', '2026-10-07 08:00:00', '2026-10-07 10:00:00');
        $this->makeExam($vacancy, $group, 'B', '2026-10-07 10:00:00', '2026-10-07 12:00:00');

        $resolver = app(EquivalentSetScheduleResolver::class);

        $this->assertNull($resolver->currentWrittenSet($group->id, $application));
        $this->assertNull($resolver->nextWrittenSet($group->id, $application));
    }

    public function test_skill_equivalent_set_resolver_matches_written_schedule_behavior(): void
    {
        Carbon::setTestNow('2026-10-07 09:00:00');

        [$vacancy, $application] = $this->makeTakenInApplication();

        $group = SkillTestGroup::create([
            'vacancy_id' => $vacancy->id,
            'title' => 'Skills Group',
            'code' => 'SG-1',
            'expected_sets' => 2,
            'status' => 1,
            'score_release_policy' => 'manual',
        ]);

        $setA = $this->makeSkillTest($vacancy, $group, 'A', '2026-10-07 08:00:00', '2026-10-07 10:00:00');
        $setB = $this->makeSkillTest($vacancy, $group, 'B', '2026-10-07 10:00:00', '2026-10-07 12:00:00');

        $resolver = app(EquivalentSetScheduleResolver::class);

        $this->assertSame($setA->id, $resolver->currentSkillSet($group->id, $application)?->id);
        $this->assertSame($setB->id, $resolver->nextSkillSet($group->id, $application)?->id);
    }

    public function test_written_score_does_not_sync_before_manual_release_but_syncs_after_release(): void
    {
        Carbon::setTestNow('2026-10-07 09:00:00');

        [$vacancy, $application, $assessment] = $this->makeTakenInApplication([
            'Written_Examination' => 20,
            'Skills_Test' => 20,
            'Interview' => 60,
        ]);

        $group = AssessmentGroup::create([
            'vacancy_id' => $vacancy->id,
            'title' => 'Written Group',
            'code' => 'WG-SCORE',
            'expected_sets' => 1,
            'status' => 1,
            'score_release_policy' => 'manual',
            'assessment_score_key' => 'Written_Examination',
        ]);

        $exam = $this->makeExam($vacancy, $group, 'A', '2026-10-07 08:00:00', '2026-10-07 10:00:00');

        $attempt = ExamAttempt::create([
            'exam_id' => $exam->id,
            'application_id' => $application->id,
            'started_at' => now()->subMinutes(20),
            'expires_at' => now()->addMinutes(10),
            'ended_at' => now(),
            'status' => 2,
            'correct_answers' => 10,
            'total_items' => 20,
            'percentage' => 50,
            'scored_at' => now(),
        ]);

        $sync = app(AssessmentScoreSyncService::class);

        $this->assertFalse($sync->syncWrittenAttempt($attempt));
        $this->assertSame([], json_decode((string) $assessment->fresh()->assessment, true));

        $group->update(['scores_released_at' => now()]);
        $this->assertTrue($sync->syncWrittenAttempt($attempt->fresh()));

        $fresh = $assessment->fresh();
        $scores = json_decode((string) $fresh->assessment, true);

        $this->assertSame(10.0, (float) $scores['Written_Examination']);
        $this->assertSame(10.0, (float) $fresh->score);
    }

    public function test_written_score_sync_clamps_percentage_before_scaling(): void
    {
        [$vacancy, $application, $assessment] = $this->makeTakenInApplication([
            'Written_Examination' => 20,
            'Interview' => 80,
        ]);

        $group = AssessmentGroup::create([
            'vacancy_id' => $vacancy->id,
            'title' => 'Written Group',
            'code' => 'WG-CLAMP',
            'expected_sets' => 1,
            'status' => 1,
            'score_release_policy' => 'manual',
            'assessment_score_key' => 'Written_Examination',
            'scores_released_at' => now(),
        ]);

        $exam = $this->makeExam($vacancy, $group, 'A', now()->subHour(), now()->addHour());

        $attempt = ExamAttempt::create([
            'exam_id' => $exam->id,
            'application_id' => $application->id,
            'started_at' => now()->subMinutes(10),
            'expires_at' => now()->addMinutes(20),
            'ended_at' => now(),
            'status' => 2,
            'correct_answers' => 25,
            'total_items' => 20,
            'percentage' => 125,
            'scored_at' => now(),
        ]);

        $this->assertTrue(app(AssessmentScoreSyncService::class)->syncWrittenAttempt($attempt));

        $scores = json_decode((string) $assessment->fresh()->assessment, true);
        $this->assertSame(20.0, (float) $scores['Written_Examination']);
    }

    public function test_skill_score_does_not_sync_until_human_final_score_exists_and_scores_are_released(): void
    {
        Carbon::setTestNow('2026-10-07 09:00:00');

        [$vacancy, $application, $assessment] = $this->makeTakenInApplication([
            'Written_Examination' => 20,
            'Skills_Test' => 30,
            'Interview' => 50,
        ]);

        $group = SkillTestGroup::create([
            'vacancy_id' => $vacancy->id,
            'title' => 'Skills Group',
            'code' => 'SG-SCORE',
            'expected_sets' => 1,
            'status' => 1,
            'score_release_policy' => 'manual',
            'assessment_score_key' => 'Skills_Test',
        ]);

        $test = $this->makeSkillTest($vacancy, $group, 'A', '2026-10-07 08:00:00', '2026-10-07 10:00:00');

        $attempt = SkillTestAttempt::create([
            'skill_test_id' => $test->id,
            'application_id' => $application->id,
            'started_at' => now()->subMinutes(20),
            'expires_at' => now()->addMinutes(10),
            'submitted_at' => now(),
            'status' => 2,
            'ai_proposed_score' => 80,
        ]);

        $sync = app(AssessmentScoreSyncService::class);

        $this->assertFalse($sync->syncSkillAttempt($attempt));

        $attempt->update([
            'final_score' => 80,
            'evaluated_at' => now(),
        ]);

        $this->assertFalse($sync->syncSkillAttempt($attempt->fresh()));

        $group->update(['scores_released_at' => now()]);
        $this->assertTrue($sync->syncSkillAttempt($attempt->fresh()));

        $fresh = $assessment->fresh();
        $scores = json_decode((string) $fresh->assessment, true);

        $this->assertSame(24.0, (float) $scores['Skills_Test']);
        $this->assertSame(24.0, (float) $fresh->score);
    }

    public function test_written_start_is_blocked_until_application_is_taken_in(): void
    {
        Carbon::setTestNow('2026-10-07 09:00:00');

        [$vacancy, $application, $assessment] = $this->makeTakenInApplication();
        $assessment->delete();

        $exam = Exam::create([
            'vacancy_id' => $vacancy->id,
            'title' => 'Standalone Written',
            'code' => 'WT-ELIGIBILITY',
            'enrollment_key' => 'ELIGIBILITY-WRITTEN',
            'start_date' => now()->subHour(),
            'end_date' => now()->addHour(),
            'duration' => 30,
            'access_mode' => 'all_taken_in',
            'shuffle_items' => true,
            'shuffle_options' => true,
            'status' => 1,
            'approval_status' => 'approved',
        ]);

        $response = $this
            ->withSession(['guest_email' => $application->email])
            ->post(route('guest.assessments.attempts.start', [$application, $exam]));

        $response->assertForbidden();
        $this->assertDatabaseMissing('exam_attempts', [
            'exam_id' => $exam->id,
            'application_id' => $application->id,
        ]);
    }

    public function test_skill_start_is_blocked_until_application_is_taken_in(): void
    {
        Carbon::setTestNow('2026-10-07 09:00:00');

        [$vacancy, $application, $assessment] = $this->makeTakenInApplication();
        $assessment->delete();

        $test = SkillTest::create([
            'vacancy_id' => $vacancy->id,
            'title' => 'Standalone Skills',
            'code' => 'ST-ELIGIBILITY',
            'instructions' => 'Complete the task.',
            'expected_output' => 'Response',
            'start_date' => now()->subHour(),
            'end_date' => now()->addHour(),
            'duration' => 30,
            'access_mode' => 'all_taken_in',
            'submission_modes' => ['inline'],
            'allowed_extensions' => ['docx'],
            'max_file_size_kb' => 10240,
            'ai_scoring' => false,
            'score_release_policy' => 'manual',
            'status' => 1,
            'approval_status' => 'approved',
        ]);

        $response = $this
            ->withSession(['guest_email' => $application->email])
            ->post(route('guest.skills.start', [$application, $test]));

        $response->assertForbidden();
        $this->assertDatabaseMissing('skill_test_attempts', [
            'skill_test_id' => $test->id,
            'application_id' => $application->id,
        ]);
    }

    public function test_group_release_policies_are_consistent_for_written_and_skills(): void
    {
        Carbon::setTestNow('2026-10-07 09:00:00');

        [$vacancy] = $this->makeTakenInApplication();

        $written = AssessmentGroup::create([
            'vacancy_id' => $vacancy->id,
            'title' => 'Written',
            'code' => 'WG-POLICY',
            'status' => 1,
            'score_release_policy' => 'manual',
        ]);

        $skills = SkillTestGroup::create([
            'vacancy_id' => $vacancy->id,
            'title' => 'Skills',
            'code' => 'SG-POLICY',
            'status' => 1,
            'score_release_policy' => 'manual',
        ]);

        $this->assertFalse($written->scoresAreReleased());
        $this->assertFalse($skills->scoresAreReleased());

        $written->update(['scores_released_at' => now()]);
        $skills->update(['scores_released_at' => now()]);

        $this->assertTrue($written->fresh()->scoresAreReleased());
        $this->assertTrue($skills->fresh()->scoresAreReleased());

        $written->update(['score_release_policy' => 'hidden']);
        $skills->update(['score_release_policy' => 'hidden']);

        $this->assertFalse($written->fresh()->scoresAreReleased());
        $this->assertFalse($skills->fresh()->scoresAreReleased());

        $written->update(['score_release_policy' => 'immediate']);
        $skills->update(['score_release_policy' => 'immediate']);

        $this->assertTrue($written->fresh()->scoresAreReleased());
        $this->assertTrue($skills->fresh()->scoresAreReleased());
    }

    protected function makeTakenInApplication(array $criteria = ['Written_Examination' => 20, 'Skills_Test' => 20, 'Interview' => 60]): array
    {
        $template = Template::create([
            'type' => 'Regression Template',
            'template' => json_encode(array_merge($criteria, ['Remarks' => 'Remarks'])),
            'status' => 1,
        ]);

        $vacancy = Vacancy::create([
            'cycle' => 2026,
            'position_title' => 'Regression Test Position',
            'salary_grade' => 10,
            'base_pay' => 30000,
            'office_level' => 1,
            'qualifications' => 'Testing qualification',
            'vacancy' => 1,
            'status' => 1,
            'template_id' => $template->id,
            'level1_status' => 1,
            'level2_status' => 0,
        ]);

        $application = Application::create([
            'vacancy_id' => $vacancy->id,
            'application_code' => 'APP-'.uniqid(),
            'first_name' => 'Test',
            'middle_name' => 'A',
            'last_name' => 'Applicant',
            'sitio' => 'Sitio',
            'barangay' => 'Barangay',
            'municipality' => 'Municipality',
            'zip' => 6300,
            'age' => 30,
            'gender' => 'Male',
            'civil_status' => 'Single',
            'religion' => 'N/A',
            'disability' => 'None',
            'ethnic_group' => 'N/A',
            'email' => uniqid('applicant').'@example.test',
            'phone' => '09000000000',
        ]);

        $assessment = Assessment::create([
            'application_id' => $application->id,
            'template_id' => $template->id,
            'assessment' => json_encode([]),
            'score' => 0,
            'status' => 0,
        ]);

        return [$vacancy, $application, $assessment, $template];
    }

    protected function makeExam(
        Vacancy $vacancy,
        AssessmentGroup $group,
        string $setCode,
        $start,
        $end
    ): Exam {
        return Exam::create([
            'vacancy_id' => $vacancy->id,
            'assessment_group_id' => $group->id,
            'title' => 'Written Set '.$setCode,
            'code' => 'W-'.$setCode.'-'.uniqid(),
            'set_code' => $setCode,
            'enrollment_key' => 'KEY-'.uniqid(),
            'start_date' => $start,
            'end_date' => $end,
            'duration' => 30,
            'access_mode' => 'all_taken_in',
            'shuffle_items' => true,
            'shuffle_options' => true,
            'status' => 1,
            'approval_status' => 'approved',
        ]);
    }

    protected function makeSkillTest(
        Vacancy $vacancy,
        SkillTestGroup $group,
        string $setCode,
        $start,
        $end
    ): SkillTest {
        return SkillTest::create([
            'vacancy_id' => $vacancy->id,
            'skill_test_group_id' => $group->id,
            'title' => 'Skills Set '.$setCode,
            'code' => 'S-'.$setCode.'-'.uniqid(),
            'set_code' => $setCode,
            'instructions' => 'Complete the assigned performance task.',
            'expected_output' => 'A complete response.',
            'start_date' => $start,
            'end_date' => $end,
            'duration' => 30,
            'access_mode' => 'all_taken_in',
            'submission_modes' => ['inline'],
            'allowed_extensions' => ['docx'],
            'max_file_size_kb' => 10240,
            'ai_scoring' => true,
            'score_release_policy' => 'manual',
            'status' => 1,
            'approval_status' => 'approved',
        ]);
    }
}
