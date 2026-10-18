<?php

namespace Tests\Feature;

use App\Models\ApplicantApplicationLink;
use App\Models\ApplicantProfile;
use App\Models\Application;
use App\Models\Template;
use App\Models\User;
use App\Models\Vacancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

class ApplicantSecondaryDatabaseIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_station_detail_page_renders_with_separate_mysql_connection(): void
    {
        // Never create or drop a table in a real HRMIS/production secondary database.
        // This test is enabled only for GitHub Actions' disposable auxiliary MySQL DB.
        if (config('database.connections.mysql_2.driver') !== 'mysql'
            || config('database.connections.mysql_2.database') !== 'rms_test_aux') {
            $this->markTestSkipped('Requires disposable rms_test_aux MySQL database.');
        }

        $schema = Schema::connection('mysql_2');
        $this->assertFalse($schema->hasTable('stations'), 'The test DB must be fresh and disposable.');
        $schema->create('stations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        try {
            DB::connection('mysql_2')->table('stations')->insert([
                'id' => 1,
                'name' => 'Synthetic Station Integration Test',
            ]);

            $template = Template::create([
                'type' => 'Secondary MySQL Integration',
                'template' => json_encode(['Interview' => 100]),
                'status' => 1,
            ]);
            $vacancy = Vacancy::create([
                'cycle' => 2026,
                'position_title' => 'Teaching Position',
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
            $owner = User::factory()->create();
            $profile = ApplicantProfile::create([
                'user_id' => $owner->id,
                'first_name' => 'Synthetic',
                'last_name' => 'Applicant',
                'email' => $owner->email,
            ]);
            $application = Application::create([
                'vacancy_id' => $vacancy->id,
                'application_code' => 'SYNTHETIC-MYSQL-STATION-001',
                'first_name' => 'Synthetic',
                'middle_name' => 'T',
                'last_name' => 'Applicant',
                'sitio' => 'Synthetic',
                'barangay' => 'Synthetic',
                'municipality' => 'Synthetic',
                'zip' => 6300,
                'age' => 30,
                'gender' => 'Female',
                'civil_status' => 'Single',
                'religion' => 'None',
                'disability' => 'None',
                'ethnic_group' => 'None',
                'email' => $owner->email,
                'phone' => '09123456789',
                'station_id' => 1,
            ]);
            ApplicantApplicationLink::create([
                'application_id' => $application->id,
                'applicant_profile_id' => $profile->id,
                'link_method' => 'hr_approved_claim',
                'verified_at' => now(),
            ]);

            $this->actingAs($owner)->get(route('guest.applications.show', $application))
                ->assertOk()
                ->assertSee('Synthetic Station Integration Test')
                ->assertSee($application->application_code);
        } finally {
            $schema->drop('stations');
        }
    }
}
