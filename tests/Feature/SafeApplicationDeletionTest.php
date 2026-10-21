<?php

namespace Tests\Feature;

use App\Models\ApplicantApplicationLink;
use App\Models\ApplicantProfile;
use App\Models\Application;
use App\Models\Assessment;
use App\Models\Role;
use App\Models\Template;
use App\Models\User;
use App\Models\Vacancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SafeApplicationDeletionTest extends TestCase
{
    use RefreshDatabase;

    private function hr(): User
    {
        $user = User::factory()->create();
        Role::create(['user_id' => $user->id, 'level' => 1, 'status' => 1]);
        return $user;
    }

    private function application(string $code = 'DELETE-TEST-1'): Application
    {
        $template = Template::create([
            'type' => 'Deletion Guard Test',
            'template' => json_encode(['Interview' => 100]),
            'status' => 1,
        ]);
        $vacancy = Vacancy::create([
            'cycle' => 2026, 'position_title' => 'Deletion Guard Vacancy',
            'salary_grade' => 11, 'base_pay' => 27000, 'office_level' => 1,
            'qualifications' => 'Required', 'vacancy' => 1, 'status' => 1,
            'template_id' => $template->id, 'level1_status' => 1, 'level2_status' => 0,
        ]);
        return Application::create([
            'vacancy_id' => $vacancy->id, 'application_code' => $code,
            'first_name' => 'Synthetic', 'middle_name' => 'A', 'last_name' => 'Applicant',
            'sitio' => 'Sitio', 'barangay' => 'Barangay', 'municipality' => 'Town',
            'zip' => 6300, 'age' => 30, 'gender' => 'Female',
            'civil_status' => 'Single', 'religion' => 'None', 'disability' => 'None',
            'ethnic_group' => 'None', 'email' => 'synthetic@example.test',
            'phone' => '09123456789', 'station_id' => -1,
        ]);
    }

    public function test_truly_unused_untagged_application_deletes_with_audit_record(): void
    {
        $hr = $this->hr();
        $application = $this->application();
        $this->actingAs($hr)->get(route('admin.applications.delete', $application))
            ->assertOk()->assertSee('Yes, delete unused application');

        $this->delete(route('admin.applications.destroy', $application))
            ->assertRedirect(route('admin.applications.index'));
        $this->assertDatabaseMissing('applications', ['id' => $application->id]);
        $this->assertDatabaseHas('applicant_audit_logs', [
            'actor_user_id' => $hr->id, 'application_id' => $application->id,
            'event_type' => 'unused_application_deleted_by_hr',
        ]);
    }

    public function test_linked_application_delete_is_blocked_without_removing_the_owner(): void
    {
        $hr = $this->hr();
        $application = $this->application('LINKED-TEST');
        $applicant = User::factory()->create();
        $profile = ApplicantProfile::create(['user_id' => $applicant->id]);
        ApplicantApplicationLink::create([
            'applicant_profile_id' => $profile->id,
            'application_id' => $application->id,
            'link_method' => 'google_email_match',
            'verified_at' => now(),
        ]);
        $this->actingAs($hr)->get(route('admin.applications.delete', $application))
            ->assertOk()->assertSee('Deletion unavailable')
            ->assertSee('an applicant-account ownership link')
            ->assertDontSee('Yes, delete unused application');

        $this->delete(route('admin.applications.destroy', $application))
            ->assertRedirect(route('admin.applications.delete', $application))
            ->assertSessionHasErrors('deletion');
        $this->assertDatabaseHas('applications', ['id' => $application->id]);
        $this->assertDatabaseHas('applicant_application_links', [
            'application_id' => $application->id,
            'applicant_profile_id' => $profile->id,
        ]);
    }

    public function test_assessed_application_delete_preserves_scores_and_correspondence(): void
    {
        $hr = $this->hr();
        $application = $this->application('ASSESS-TEST');
        $assessment = Assessment::create([
            'application_id' => $application->id,
            'template_id' => $application->vacancy->template_id,
            'assessment' => json_encode(['Interview' => 42]),
            'status' => 2,
        ]);
        DB::table('inquiries')->insert([
            'application_id' => $application->id,
            'author' => 'Synthetic HR',
            'message' => 'Application assessed by HR.',
            'status' => 0,
        ]);

        $this->actingAs($hr)->delete(route('admin.applications.destroy', $application))
            ->assertRedirect(route('admin.applications.delete', $application))
            ->assertSessionHasErrors('deletion');
        $this->assertDatabaseHas('applications', ['id' => $application->id]);
        $this->assertDatabaseHas('assessments', ['id' => $assessment->id]);
        $this->assertDatabaseHas('inquiries', [
            'application_id' => $application->id, 'message' => 'Application assessed by HR.',
        ]);
    }

    public function test_integrity_flag_and_board_nullification_prevent_deleting_evidence(): void
    {
        $hr = $this->hr();
        $app = $this->application('BOARD-TEST');
        $batchId = DB::table('assessment_nullification_batches')->insertGetId([
            'vacancy_id' => $app->vacancy_id, 'board_reference' => 'SYNTHETIC-BOARD-TEST',
            'board_date' => '2026-10-21', 'reason' => 'Synthetic coverage test.',
            'status' => 'active', 'created_by' => $hr->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('assessment_nullification_items')->insert([
            'batch_id' => $batchId, 'application_id' => $app->id,
            'criterion_key' => 'Written_Examination',
            'previously_present' => true, 'previous_value_json' => '25',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('applicant_integrity_flags')->insert([
            'application_id' => $app->id, 'vacancy_id' => $app->vacancy_id,
            'source_type' => 'manual', 'status' => 'pending',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($hr)->delete(route('admin.applications.destroy', $app))
            ->assertRedirect(route('admin.applications.delete', $app))
            ->assertSessionHasErrors('deletion');
        $this->assertDatabaseHas('applications', ['id' => $app->id]);
        $this->assertDatabaseHas('assessment_nullification_items', ['application_id' => $app->id]);
        $this->assertDatabaseHas('applicant_integrity_flags', ['application_id' => $app->id]);
    }

    public function test_tagged_application_without_scores_cannot_bypass_existing_ui_restriction(): void
    {
        $hr = $this->hr();
        $app = $this->application('TAGGED-TEST');
        $app->update(['station_id' => 3]);
        $this->actingAs($hr)->delete(route('admin.applications.destroy', $app))
            ->assertRedirect(route('admin.applications.delete', $app))
            ->assertSessionHasErrors('deletion');
        $this->assertDatabaseHas('applications', ['id' => $app->id]);
    }

    public function test_non_hr_staff_cannot_permanently_delete_an_application(): void
    {
        $app = $this->application('STAFF-TEST');
        $staff = User::factory()->create();
        Role::create(['user_id' => $staff->id, 'level' => 2, 'status' => 1]);
        $this->actingAs($staff)->delete(route('admin.applications.destroy', $app))
            ->assertStatus(401);
        $this->assertDatabaseHas('applications', ['id' => $app->id]);
    }
}
