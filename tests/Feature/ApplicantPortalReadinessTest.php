<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ApplicantPortalReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_read_only_readiness_checks_run_on_complete_portal_schema(): void
    {
        config([
            'services.google.client_id' => 'synthetic-ci-id',
            'services.google.client_secret' => 'synthetic-ci-secret',
        ]);

        $this->artisan('rms:applicant-readiness', ['--skip-secondary' => true])
            ->expectsOutput('[PASS] Routes: Required applicant and HR route names are registered.')
            ->expectsOutput('[PASS] Route access controls: Authentication, staff, HR review and claim rate-limit middleware are present.')
            ->expectsOutput('[PASS] Primary database: Connection succeeded.')
            ->expectsOutput('[PASS] Portal tables: Legacy and new portal tables exist.')
            ->expectsOutput('[PASS] Legacy applications schema: Original applications column contract is present.')
            ->expectsOutput('[PASS] External-style read: Legacy-column SELECT succeeded with zero records retrieved.')
            ->assertExitCode(0);

        $this->assertDatabaseCount('applications', 0);
        $this->assertDatabaseCount('applicant_application_links', 0);
    }

    public function test_readiness_refuses_to_pass_when_a_portal_table_is_missing(): void
    {
        Schema::drop('application_claim_requests');

        $this->artisan('rms:applicant-readiness', ['--skip-secondary' => true])
            ->expectsOutput('[FAIL] Portal tables: Missing tables: application_claim_requests')
            ->assertExitCode(1);
    }

    public function test_readiness_fails_if_hr_approval_route_loses_admin_guard(): void
    {
        $route = \Illuminate\Support\Facades\Route::getRoutes()
            ->getByName('admin.applicant_claims.approve');
        $this->assertNotNull($route);

        // Model an accidental removal of the admin route group's security
        // middleware. Restore the route even if the assertion fails.
        $original = $route->action;
        try {
            $route->action['middleware'] = ['auth', 'active'];
            $this->artisan('rms:applicant-readiness', ['--skip-secondary' => true])
                ->expectsOutput(
                    '[FAIL] Route access controls: Required middleware absent: '.
                    'admin.applicant_claims.approve (missing admin)'
                )
                ->assertExitCode(1);
        } finally {
            $route->action = $original;
        }
    }

    public function test_strict_readiness_requires_secondary_and_oauth_checks_to_be_resolved(): void
    {
        config([
            'services.google.client_id' => null,
            'services.google.client_secret' => null,
        ]);

        $this->artisan('rms:applicant-readiness', [
            '--skip-secondary' => true,
            '--strict' => true,
        ])->expectsOutput('[WARN] Google OAuth: Google configuration missing: client_id, client_secret.')
            ->expectsOutput('[WARN] Secondary station database: Skipped explicitly; assigned-station detail pages remain unverified.')
            ->assertExitCode(1);
    }
}
