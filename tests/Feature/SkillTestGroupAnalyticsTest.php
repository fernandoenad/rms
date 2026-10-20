<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Role;
use App\Models\SkillTest;
use App\Models\SkillTestAttempt;
use App\Models\SkillTestGroup;
use App\Models\SkillTestRubricCriterion;
use App\Models\Template;
use App\Models\User;
use App\Models\Vacancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SkillTestGroupAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private function createGroup(): array
    {
        $template = Template::create([
            'type' => 'Skills Analytics Fixture',
            'template' => json_encode(['Skills_Test' => 100]),
            'status' => 1,
        ]);

        $vacancy = Vacancy::create([
            'cycle' => 2026,
            'position_title' => 'Skills Analytics Position',
            'salary_grade' => 11,
            'base_pay' => 27000,
            'office_level' => 1,
            'qualifications' => 'Required',
            'vacancy' => 1,
            'status' => 1,
            'template_id' => $template->id,
            'level1_status' => 1,
            'level2_status' => 0,
        ]);

        $group = SkillTestGroup::create([
            'vacancy_id' => $vacancy->id,
            'title' => 'Skills Analytics Group',
            'code' => 'SG-ANALYTICS',
            'expected_sets' => 2,
            'status' => 1,
            'score_release_policy' => 'manual',
        ]);

        $staff = User::factory()->create();
        Role::create(['user_id' => $staff->id, 'level' => 1, 'status' => 1]);
        $this->actingAs($staff);

        return [$vacancy, $group];
    }

    private function createApplicant(Vacancy $vacancy, string $code): Application
    {
        return Application::create([
            'vacancy_id' => $vacancy->id,
            'application_code' => $code,
            'first_name' => 'Example',
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
            'email' => strtolower($code).'@example.test',
            'phone' => '09000000000',
        ]);
    }

    public function test_edit_header_has_four_working_action_links(): void
    {
        [, $group] = $this->createGroup();

        $this->get(route('admin.skill_groups.edit', $group))
            ->assertOk()
            ->assertSee('href="'.route('admin.assessment_center.index').'"', false)
            ->assertSee('href="'.route('admin.skill_groups.results', $group).'"', false)
            ->assertSee('href="'.route('admin.skill_groups.analytics', $group).'"', false)
            ->assertSee('href="'.route('admin.skill_groups.export', $group).'"', false)
            ->assertSee('Export CSV')
            ->assertSee('Analytics');
    }

    public function test_analytics_page_renders_without_any_sets(): void
    {
        [, $group] = $this->createGroup();

        $this->get(route('admin.skill_groups.analytics', $group))
            ->assertOk()
            ->assertSee('Skills Analytics Group')
            ->assertSee('No Skills Test sets have been configured yet.');
    }

    public function test_analytics_calculates_human_final_score_and_ai_gap(): void
    {
        [$vacancy, $group] = $this->createGroup();

        $set = SkillTest::create([
            'vacancy_id' => $vacancy->id,
            'skill_test_group_id' => $group->id,
            'title' => 'Skills Set A',
            'code' => 'SG-A-ANALYTICS',
            'set_code' => 'A',
            'instructions' => 'Perform the task',
            'expected_output' => 'Completed output',
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

        SkillTestRubricCriterion::create([
            'skill_test_id' => $set->id,
            'criterion' => 'Task Performance',
            'max_points' => 100,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        foreach ([['ANALYTICS-1', 60, 58], ['ANALYTICS-2', 80, 75]] as [$code, $final, $ai]) {
            $application = $this->createApplicant($vacancy, $code);
            SkillTestAttempt::create([
                'skill_test_id' => $set->id,
                'application_id' => $application->id,
                'started_at' => now()->subMinutes(20),
                'submitted_at' => now()->subMinutes(5),
                'status' => 2,
                'final_score' => $final,
                'ai_proposed_score' => $ai,
                'evaluated_at' => now(),
            ]);
        }

        $this->get(route('admin.skill_groups.analytics', $group))
            ->assertOk()
            ->assertSee('Per-Set Assessment Analytics')
            ->assertSee('70.00')
            ->assertSee('60.00 / 80.00')
            ->assertSee('3.50')
            ->assertSee('href="'.route('admin.skills.results', $set).'"', false);
    }
}
