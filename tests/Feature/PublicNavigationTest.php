<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Template;
use App\Models\User;
use App\Models\Vacancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicNavigationTest extends TestCase
{
    use RefreshDatabase;

    private function vacancy(string $title, array $fields = []): Vacancy
    {
        $template = Template::create([
            'type' => 'Navigation Integration Fixture',
            'template' => json_encode(['Interview' => 100]),
            'status' => 1,
        ]);

        return Vacancy::create(array_merge([
            'cycle' => 2026,
            'position_title' => $title,
            'salary_grade' => 11,
            'base_pay' => 27000,
            'office_level' => 1,
            'qualifications' => 'Required',
            'vacancy' => 1,
            'status' => 1,
            'template_id' => $template->id,
            'level1_status' => 1,
            'level2_status' => 0,
        ], $fields));
    }

    public function test_public_home_shows_open_vacancy_and_sign_in_without_private_user_controls(): void
    {
        $open = $this->vacancy('Public Teaching Vacancy');
        $this->vacancy('Not Public Position', ['posting_end_at' => now()->subMinute()]);

        $this->get(route('guest.index'))
            ->assertOk()
            ->assertSee('Recruitment opportunities, in one place.')
            ->assertSee('Latest Open Vacancies')
            ->assertSee($open->position_title)
            ->assertSee('href="'.route('guest.vacancies.show', $open).'"', false)
            ->assertSee('href="'.route('guest.vacancies.index').'"', false)
            ->assertSee('Sign In')
            ->assertSee('href="'.route('applicant.login').'"', false)
            ->assertSee('Staff Sign In')
            ->assertSee('href="'.route('login').'"', false)
            ->assertDontSee('Not Public Position')
            ->assertDontSee('id="rmsUserMenu"', false)
            ->assertDontSee('rms-nav-sign-out', false);
    }

    public function test_public_home_has_clear_no_vacancies_state_and_legacy_lookup_link(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('No recruitment vacancies are currently open')
            ->assertSee('Open Application Lookup')
            ->assertSee('href="'.route('guest.applications.index').'"', false);
        $this->get(route('guest.applications.index'))->assertOk()
            ->assertSee('Application Lookup');
    }

    public function test_authenticated_header_displays_name_with_dashboard_profile_applications_and_post_logout(): void
    {
        $user = User::factory()->create([
            'name' => 'Applicant Test User',
            'email' => 'applicant@example.test',
        ]);

        $this->actingAs($user)->get(route('guest.index'))
            ->assertOk()
            ->assertSee('Applicant Test User')
            ->assertSee('id="rmsUserMenu"', false)
            ->assertSee('aria-label="Account menu for Applicant Test User"', false)
            ->assertSee('href="'.route('applicant.dashboard').'"', false)
            ->assertSee('href="'.route('applicant.profile.edit').'"', false)
            ->assertSee('href="'.route('applicant.dashboard').'#applicationsTitle"', false)
            ->assertSee('My Applications')
            ->assertSee('My Profile')
            ->assertSee('method="POST" action="'.route('logout').'"', false)
            ->assertSee('Sign Out')
            ->assertDontSee('rms-nav-sign-in', false)
            ->assertDontSee('>Staff Portal</a>', false);

        $this->actingAs($user)->post(route('logout'))->assertRedirect('/');
        $this->assertGuest();
        $this->get(route('guest.index'))->assertSee('Sign In')
            ->assertDontSee('id="rmsUserMenu"', false);
    }

    public function test_staff_menu_only_shows_staff_portal_for_active_authorized_staff(): void
    {
        $staff = User::factory()->create(['name' => 'Division Reviewer']);
        Role::create(['user_id' => $staff->id, 'level' => 1, 'status' => 1]);
        $this->actingAs($staff)->get(route('guest.index'))
            ->assertOk()
            ->assertSee('Division Reviewer')
            ->assertSee('>Staff Portal</a>', false)
            ->assertSee('href="'.route('admin.index').'"', false);

        $staff->role->update(['status' => 0]);
        $this->actingAs($staff)->get(route('guest.index'))
            ->assertOk()->assertDontSee('>Staff Portal</a>', false);
    }

    public function test_missing_account_name_falls_back_to_email_local_part(): void
    {
        $user = User::factory()->create([
            'name' => '',
            'email' => 'sample.user@example.test',
        ]);
        $this->actingAs($user)->get(route('guest.index'))
            ->assertOk()
            ->assertSee('aria-label="Account menu for sample.user"', false);
    }
}
