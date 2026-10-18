<?php

namespace Tests\Feature;

use App\Models\ApplicantProfile;
use App\Models\Application;
use App\Models\Template;
use App\Models\User;
use App\Models\Vacancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * This test deliberately does not use RefreshDatabase: migrations may only
 * run after the explicit disposable-CI guard has been checked.
 */
class ApplicantPrimaryMySqlCompatibilityTest extends TestCase
{
    public function test_legacy_application_reader_contract_with_disposable_primary_mysql(): void
    {
        if (getenv('RMS_DISPOSABLE_MYSQL_CI') !== '1'
            || config('database.default') !== 'mysql'
            || config('database.connections.mysql.database') !== 'rms_test_primary'
            || config('database.connections.mysql.host') !== '127.0.0.1') {
            $this->markTestSkipped('Only allowed against the disposable rms_test_primary CI database.');
        }

        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);

        // Explicitly track the original applications schema, independent of
        // the new applicant ownership tables. Existing indexes are allowed.
        $originalColumns = [
            'id', 'vacancy_id', 'application_code', 'first_name', 'middle_name',
            'last_name', 'sitio', 'barangay', 'municipality', 'zip', 'age',
            'gender', 'civil_status', 'religion', 'disability', 'ethnic_group',
            'email', 'phone', 'station_id', 'created_at', 'updated_at',
        ];
        $actualColumns = Schema::getColumnListing('applications');
        sort($originalColumns);
        sort($actualColumns);
        $this->assertSame($originalColumns, $actualColumns);

        $template = Template::create([
            'type' => 'Isolated Legacy Consumer Test',
            'template' => json_encode(['Interview' => 100]),
            'status' => 1,
        ]);
        $vacancy = Vacancy::create([
            'cycle' => 2026, 'position_title' => 'Synthetic Teacher I',
            'salary_grade' => 11, 'base_pay' => 27000, 'office_level' => 1,
            'qualifications' => 'Synthetic requirements', 'vacancy' => 1,
            'status' => 1, 'template_id' => $template->id,
            'level1_status' => 1, 'level2_status' => 0,
        ]);

        // A previously created unowned application must remain readable.
        $legacy = Application::create([
            'vacancy_id' => $vacancy->id,
            'application_code' => 'LEGACY-CONSUMER-001',
            'first_name' => 'Legacy', 'middle_name' => 'A',
            'last_name' => 'Record', 'sitio' => 'Old Sitio',
            'barangay' => 'Old Barangay', 'municipality' => 'Old Town',
            'zip' => 6300, 'age' => 31, 'gender' => 'Female',
            'civil_status' => 'Single', 'religion' => 'None',
            'disability' => 'None', 'ethnic_group' => 'None',
            'email' => 'legacy@example.test', 'phone' => '09110000000',
        ]);

        $owner = User::factory()->create(['email' => 'new@applicant.test']);
        ApplicantProfile::create([
            'user_id' => $owner->id,
            'first_name' => 'Synthetic', 'middle_name' => 'B',
            'last_name' => 'Applicant', 'sitio' => 'New Sitio',
            'barangay' => 'New Barangay', 'municipality' => 'New Town',
            'zip' => 6331, 'age' => 25, 'gender' => 'Male',
            'civil_status' => 'Single', 'religion' => 'None',
            'disability' => 'None', 'ethnic_group' => 'None',
            'email' => 'new@applicant.test', 'phone' => '09120000000',
            'profile_completed_at' => now(),
        ]);

        $this->actingAs($owner)
            ->post(route('applicant.apply.store', $vacancy), ['confirm' => 1])
            ->assertRedirect(route('applicant.dashboard'));
        $newApplication = Application::where('email', 'new@applicant.test')->sole();

        // Emulate an independently written reader: no Eloquent models,
        // new portal tables, ownership joins, or application schema changes.
        $rows = DB::select(
            'SELECT application_code, first_name, last_name, email, ' .
            'vacancy_id, station_id, zip FROM applications ' .
            'WHERE application_code IN (?, ?) ORDER BY application_code',
            [$legacy->application_code, $newApplication->application_code]
        );
        $this->assertCount(2, $rows);
        $byCode = collect($rows)->keyBy('application_code');
        $this->assertSame('Legacy', $byCode[$legacy->application_code]->first_name);
        $this->assertSame('legacy@example.test', $byCode[$legacy->application_code]->email);
        $this->assertNull($byCode[$legacy->application_code]->station_id);
        $this->assertSame('Synthetic', $byCode[$newApplication->application_code]->first_name);
        $this->assertSame('new@applicant.test', $byCode[$newApplication->application_code]->email);
        $this->assertDatabaseHas('applicant_application_links', [
            'application_id' => $newApplication->id, 'link_method' => 'new_submission',
        ]);

        // Master-profile changes must not rewrite an already submitted row.
        ApplicantProfile::where('user_id', $owner->id)->update(['first_name' => 'Changed']);
        $snapshot = DB::table('applications')
            ->where('id', $newApplication->id)
            ->first(['first_name', 'email']);
        $this->assertSame('Synthetic', $snapshot->first_name);
        $this->assertSame('new@applicant.test', $snapshot->email);
    }
}
