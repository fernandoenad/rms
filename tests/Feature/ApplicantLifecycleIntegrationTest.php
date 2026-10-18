<?php

namespace Tests\Feature;

use App\Models\ApplicantApplicationLink;
use App\Models\ApplicantProfile;
use App\Models\Application;
use App\Models\User;
use App\Models\Vacancy;
use App\Models\Template;
use App\Services\Applicant\ApplicationClaimService;
use App\Services\Applicant\HistoricalApplicationLinkingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ApplicantLifecycleIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function profile(string $email = 'applicant@example.test'): ApplicantProfile
    {
        return ApplicantProfile::create([
            'user_id' => User::factory()->create()->id,
            'first_name' => 'Ana', 'middle_name' => 'M', 'last_name' => 'Reyes',
            'sitio' => 'Poblacion', 'barangay' => 'Central',
            'municipality' => 'Sagbayan', 'zip' => 6331, 'age' => 30,
            'gender' => 'Female', 'civil_status' => 'Single', 'religion' => 'None',
            'disability' => 'None', 'ethnic_group' => 'None',
            'email' => $email, 'phone' => '09123456789',
            'profile_completed_at' => now(),
        ]);
    }

    private function vacancy(): Vacancy
    {
        $template = Template::create([
            'type' => 'Applicant Lifecycle Test Template',
            'template' => json_encode(['Written_Examination' => 20, 'Skills_Test' => 20, 'Interview' => 60, 'Remarks' => 'Remarks']),
            'status' => 1,
        ]);

        return Vacancy::create([
            'cycle' => 2026, 'position_title' => 'Teacher I',
            'salary_grade' => 11, 'base_pay' => 27000,
            'office_level' => 1, 'qualifications' => 'Required',
            'vacancy' => 1, 'status' => 1, 'template_id' => $template->id, 'level1_status' => 1,
            'level2_status' => 0,
        ]);
    }

    private function historical(Vacancy $vacancy, string $email): Application
    {
        return Application::create([
            'vacancy_id' => $vacancy->id,
            'application_code' => 'HIST-'.uniqid(),
            'first_name' => 'Old', 'middle_name' => 'B', 'last_name' => 'Applicant',
            'sitio' => 'Old Sitio', 'barangay' => 'Old Barangay',
            'municipality' => 'Old Town', 'zip' => 6300, 'age' => 29,
            'gender' => 'Female', 'civil_status' => 'Single', 'religion' => 'None',
            'disability' => 'None', 'ethnic_group' => 'None',
            'email' => $email, 'phone' => '09111111111',
        ]);
    }

    public function test_matching_google_email_auto_links_once_without_rewriting_historical_data(): void
    {
        $profile = $this->profile();
        $application = $this->historical($this->vacancy(), ' APPlicant@example.test ');
        $linker = app(HistoricalApplicationLinkingService::class);

        $this->assertSame(1, $linker->linkMatchingEmail($profile, 'applicant@example.test'));
        $this->assertSame(0, $linker->linkMatchingEmail($profile, 'applicant@example.test'));
        $this->assertDatabaseCount('applicant_application_links', 1);
        $this->assertSame('Old', $application->fresh()->first_name);
        $this->assertSame('Old Town', $application->fresh()->municipality);
    }

    public function test_matching_email_cannot_take_over_an_already_linked_application(): void
    {
        $first = $this->profile();
        $other = $this->profile('different@example.test');
        $application = $this->historical($this->vacancy(), 'applicant@example.test');
        $linker = app(HistoricalApplicationLinkingService::class);

        $this->assertSame(1, $linker->linkMatchingEmail($first, 'applicant@example.test'));
        $this->assertSame(0, $linker->linkMatchingEmail($other, 'applicant@example.test'));
        $this->assertDatabaseHas('applicant_application_links', [
            'application_id' => $application->id, 'applicant_profile_id' => $first->id,
        ]);
    }

    public function test_other_email_claim_requires_hr_approval_and_preserves_historical_record(): void
    {
        $profile = $this->profile();
        $application = $this->historical($this->vacancy(), 'previous@example.test');
        $service = app(ApplicationClaimService::class);

        $claim = $service->request($profile, $application->application_code, 'previous@example.test');
        $this->assertSame('pending', $claim->status);
        $this->assertDatabaseCount('applicant_application_links', 0);

        $reviewer = User::factory()->create();
        $service->approve($claim, $reviewer->id, 'Official identification and historical supporting records verified.');
        $this->assertSame('approved', $claim->fresh()->status);
        $this->assertDatabaseHas('applicant_application_links', [
            'application_id' => $application->id,
            'applicant_profile_id' => $profile->id,
            'link_method' => 'hr_approved_claim',
        ]);
        $this->assertSame('Old', $application->fresh()->first_name);
        $this->assertDatabaseHas('applicant_audit_logs', ['event_type' => 'historical_claim_approved']);
    }

    public function test_claimant_cannot_approve_their_own_application(): void
    {
        $profile = $this->profile();
        $application = $this->historical($this->vacancy(), 'previous@example.test');
        $service = app(ApplicationClaimService::class);
        $claim = $service->request($profile, $application->application_code, 'previous@example.test');

        $this->expectException(ValidationException::class);
        $service->approve($claim, $profile->user_id, 'Official supporting records verified.');
    }

    public function test_profile_based_submission_creates_legacy_application_and_owner_link(): void
    {
        $profile = $this->profile();
        $vacancy = $this->vacancy();
        $this->actingAs($profile->user)
            ->post(route('applicant.apply.store', $vacancy), ['confirm' => 1])
            ->assertRedirect(route('applicant.dashboard'));

        $application = Application::where('vacancy_id', $vacancy->id)->sole();
        $this->assertSame('Ana', $application->first_name);
        $this->assertSame('2026-'.$vacancy->id.'-'.$application->id, $application->application_code);
        $this->assertDatabaseHas('applicant_application_links', [
            'application_id' => $application->id,
            'applicant_profile_id' => $profile->id,
            'link_method' => 'new_submission',
        ]);

        $this->actingAs($profile->user)
            ->post(route('applicant.apply.store', $vacancy), ['confirm' => 1])
            ->assertSessionHasErrors('application');
        $this->assertDatabaseCount('applications', 1);
    }

    public function test_profile_changes_never_rewrite_a_historical_application(): void
    {
        $profile = $this->profile();
        $old = $this->historical($this->vacancy(), 'previous@example.test');
        $profile->update(['first_name' => 'Updated', 'email' => 'new@example.test']);

        $this->assertSame('Old', $old->fresh()->first_name);
        $this->assertSame('previous@example.test', $old->fresh()->email);
    }
}
