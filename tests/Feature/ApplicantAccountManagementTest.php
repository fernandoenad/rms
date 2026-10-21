<?php

namespace Tests\Feature;

use App\Models\ApplicantApplicationLink;
use App\Models\ApplicantAuditLog;
use App\Models\ApplicantProfile;
use App\Models\Application;
use App\Models\OAuthIdentity;
use App\Models\Role;
use App\Models\Template;
use App\Models\User;
use App\Models\Vacancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicantAccountManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(int $level = 1): User
    {
        $u = User::factory()->create();
        Role::create(['user_id' => $u->id, 'level' => $level, 'status' => 1]);
        return $u;
    }

    private function account(string $email): ApplicantProfile
    {
        return ApplicantProfile::create(['user_id' => User::factory()->create(['email' => $email])->id,
            'first_name' => 'Applicant', 'last_name' => 'Tester']);
    }

    private function application(string $code, string $email): Application
    {
        $template = Template::first() ?: Template::create([
            'type' => 'Admin Account Test', 'template' => json_encode(['Interview'=>100]), 'status' => 1,
        ]);
        $vacancy = Vacancy::first() ?: Vacancy::create([
            'cycle'=>2026,'position_title'=>'Account Test Vacancy','salary_grade'=>11,
            'base_pay'=>27000,'office_level'=>1,'qualifications'=>'Required',
            'vacancy'=>1,'status'=>1,'template_id'=>$template->id,
            'level1_status'=>1,'level2_status'=>0,
        ]);
        return Application::create([
            'vacancy_id'=>$vacancy->id, 'application_code'=>$code,
            'first_name'=>'Applicant','middle_name'=>'A','last_name'=>'Tester',
            'sitio'=>'Sitio','barangay'=>'Barangay','municipality'=>'Sagbayan',
            'zip'=>6300,'age'=>30,'gender'=>'Female','civil_status'=>'Single',
            'religion'=>'None','disability'=>'None','ethnic_group'=>'None',
            'email'=>$email,'phone'=>'09123456789',
        ]);
    }

    private function verifiedGoogle(ApplicantProfile $profile, string $email): void
    {
        OAuthIdentity::create([
            'user_id'=>$profile->user_id,'provider'=>'google',
            'provider_subject'=>'subject-'.$profile->id,'provider_email'=>$email,
            'email_verified'=>true,
        ]);
    }

    public function test_admin_can_find_applicants_and_inspect_email_ownership_without_mutating_records(): void
    {
        $admin = $this->admin();
        $profile = $this->account('new@example.test');
        $a = $this->application('MATCH-1', ' new@example.test ');
        $b = $this->application('MATCH-2', 'NEW@example.test');
        $c = $this->application('MATCH-3', 'new@example.test');
        $other = $this->account('previous@example.test');
        ApplicantApplicationLink::create([
            'application_id'=>$a->id,'applicant_profile_id'=>$profile->id,
            'link_method'=>'google_email_match','verified_at'=>now(),
        ]);
        ApplicantApplicationLink::create([
            'application_id'=>$b->id,'applicant_profile_id'=>$other->id,
            'link_method'=>'google_email_match','verified_at'=>now(),
        ]);
        $this->actingAs($admin)->get(route('admin.applicant_accounts.index', ['search'=>'new@example.test']))
            ->assertOk()->assertSee('Applicant Management')->assertSee('new@example.test');
        $this->get(route('admin.applicant_accounts.show', $profile))
            ->assertOk()->assertSee('MATCH-1')->assertSee('MATCH-2')
            ->assertSee('MATCH-3')->assertSee('Linked to another account')
            ->assertSee('Unlinked')->assertSee('This account');
        $this->assertDatabaseCount('applicant_application_links', 2);
    }

    public function test_verified_google_reconciliation_links_only_unowned_matches_and_is_audited(): void
    {
        $hr = $this->admin();
        $profile = $this->account('new@example.test');
        $this->verifiedGoogle($profile, 'new@example.test');
        $own = $this->application('OWN-1', 'NEW@example.test');
        $unowned = $this->application('OWN-2', 'new@example.test');
        $conflict = $this->application('OWN-3', 'new@example.test');
        $other = $this->account('other@example.test');
        ApplicantApplicationLink::create([
            'application_id'=>$own->id,'applicant_profile_id'=>$profile->id,
            'link_method'=>'google_email_match','verified_at'=>now(),
        ]);
        ApplicantApplicationLink::create([
            'application_id'=>$conflict->id,'applicant_profile_id'=>$other->id,
            'link_method'=>'hr_approved_claim','verified_at'=>now(),
        ]);
        $this->actingAs($hr)->post(route('admin.applicant_accounts.reconcile',$profile),[
            'reason'=>'I independently verified the current matching Google account.',
        ])->assertRedirect(route('admin.applicant_accounts.show',$profile));
        $this->assertDatabaseHas('applicant_application_links',[
            'application_id'=>$unowned->id,'applicant_profile_id'=>$profile->id,
            'link_method'=>'google_email_match',
        ]);
        $this->assertDatabaseHas('applicant_application_links',[
            'application_id'=>$conflict->id,'applicant_profile_id'=>$other->id,
        ]);
        $this->assertDatabaseCount('applicant_application_links', 3);
        $this->assertDatabaseHas('applicant_audit_logs',[
            'actor_user_id'=>$hr->id,'event_type'=>'admin_google_history_reconciliation',
        ]);
        $this->post(route('admin.applicant_accounts.reconcile',$profile),[
            'reason'=>'Second documented reconciliation must not add duplicates.',
        ])->assertRedirect();
        $this->assertDatabaseCount('applicant_application_links',3);
    }

    public function test_reconciliation_requires_matching_verified_google_identity(): void
    {
        $profile = $this->account('updated@example.test');
        $this->verifiedGoogle($profile,'previous@example.test');
        $a = $this->application('UNOWNED-1','updated@example.test');
        $this->actingAs($this->admin())->post(route('admin.applicant_accounts.reconcile',$profile), [
            'reason'=>'Check whether an outdated verified email grants ownership.',
        ])->assertSessionHasErrors('reason');
        $this->assertDatabaseMissing('applicant_application_links',['application_id'=>$a->id]);
    }

    public function test_impersonation_is_disabled_by_default_and_for_non_hr_staff(): void
    {
        $profile = $this->account('test@example.test');
        config()->set('app.applicant_impersonation_enabled',false);
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.applicant_accounts.impersonation.start',$profile),[
            'reason'=>'Test a read only applicant dashboard without changing records.',
        ])->assertForbidden();
        $this->assertAuthenticatedAs($admin);
        config()->set('app.applicant_impersonation_enabled',true);
        $staff = $this->admin(2);
        $this->actingAs($staff)->post(route('admin.applicant_accounts.impersonation.start',$profile),[
            'reason'=>'Attempt to impersonate from an unauthorized staff account.',
        ])->assertForbidden();
    }

    public function test_read_only_impersonation_displays_banner_blocks_changes_and_restores_admin(): void
    {
        config()->set('app.applicant_impersonation_enabled',true);
        $admin = $this->admin();
        $profile = $this->account('testperson@example.test');
        $this->actingAs($admin)->post(route('admin.applicant_accounts.impersonation.start',$profile),[
            'reason'=>'Verify the historical application dashboard with synthetic data.',
        ])->assertRedirect(route('applicant.dashboard'));
        $this->assertAuthenticatedAs($profile->user);
        $this->get(route('applicant.dashboard'))->assertOk()
            ->assertSee('TEST IMPERSONATION')->assertSee('Return to Administrator');
        $this->get(route('applicant.profile.edit'))->assertOk();
        $this->get(route('admin.applicant_accounts.index'))->assertForbidden();
        $this->post(route('logout'))->assertForbidden();
        $this->put(route('applicant.profile.update'),[])->assertForbidden();
        $this->post(route('applicant.claims.store'),[])->assertForbidden();
        $this->post(route('admin.applicant_accounts.impersonation.stop'))
            ->assertRedirect(route('admin.applicant_accounts.index'));
        $this->assertAuthenticatedAs($admin);
        $this->assertDatabaseHas('applicant_audit_logs',[
            'event_type'=>'test_impersonation_started','actor_user_id'=>$admin->id,
        ]);
        $this->assertDatabaseHas('applicant_audit_logs',[
            'event_type'=>'test_impersonation_ended','actor_user_id'=>$admin->id,
        ]);
    }

    public function test_staff_accounts_cannot_be_targets_of_test_impersonation(): void
    {
        config()->set('app.applicant_impersonation_enabled',true);
        $admin = $this->admin();
        $target = $this->admin(2);
        $profile = ApplicantProfile::create(['user_id'=>$target->id]);
        $this->actingAs($admin)->post(route('admin.applicant_accounts.impersonation.start',$profile),[
            'reason'=>'Attempted role escalation must fail before session switch.',
        ])->assertForbidden();
        $this->assertAuthenticatedAs($admin);
    }
}
