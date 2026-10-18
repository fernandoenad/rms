<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ApplicantPortalReadiness extends Command
{
    protected $signature = 'rms:applicant-readiness
        {--skip-secondary : Do not connect to the HRMIS secondary station database}
        {--strict : Return a nonzero exit code for warnings as well as failures}';

    protected $description = 'Read-only checks for RMS applicant portal staging readiness; no applicant data or secrets are printed.';

    public function handle(): int
    {
        $failed = 0;
        $warned = 0;

        $record = function (string $status, string $name, string $message) use (&$failed, &$warned): void {
            if ($status === 'FAIL') {
                $failed++;
                $this->error("[FAIL] {$name}: {$message}");
            } elseif ($status === 'WARN') {
                $warned++;
                $this->warn("[WARN] {$name}: {$message}");
            } else {
                $this->line("[{$status}] {$name}: {$message}");
            }
        };

        $this->info('RMS Applicant Portal — Read-only readiness checks');
        $this->line('No data will be inserted, updated, deleted, or displayed.');

        $requiredRoutes = [
            'applicant.login',
            'applicant.google',
            'applicant.google.callback',
            'applicant.staff.google.link',
            'applicant.staff.google.callback',
            'applicant.dashboard',
            'applicant.apply.store',
            'admin.applicant_claims.index',
            'admin.applicant_claims.approve',
            'admin.applicant_claims.clarification',
            'admin.applicant_claims.reject',
            'guest.applications.show',
        ];

        $missingRoutes = array_values(array_filter(
            $requiredRoutes,
            fn (string $name) => !Route::has($name)
        ));
        $record(
            $missingRoutes ? 'FAIL' : 'PASS',
            'Routes',
            $missingRoutes ? 'Missing route names: '.implode(', ', $missingRoutes) : 'Required applicant and HR route names are registered.'
        );

        // Routes can exist and still be unsafe if a group middleware is
        // accidentally removed during refactoring. Check the effective
        // middleware stack for every privilege-sensitive entry point.
        $routeGuards = [
            'applicant.dashboard' => ['auth'],
            'applicant.profile.update' => ['auth'],
            'applicant.apply.store' => ['auth'],
            'applicant.claims.store' => ['auth', 'throttle:5,1'],
            'applicant.staff.google.link' => ['auth', 'active'],
            'applicant.staff.google.callback' => ['auth', 'active'],
            'admin.applicant_claims.index' => ['auth', 'active', 'admin'],
            'admin.applicant_claims.approve' => ['auth', 'active', 'admin'],
            'admin.applicant_claims.reject' => ['auth', 'active', 'admin'],
            'admin.applicant_claims.clarification' => ['auth', 'active', 'admin'],
        ];
        $unguarded = [];
        foreach ($routeGuards as $name => $required) {
            $route = Route::getRoutes()->getByName($name);
            if (!$route) {
                $unguarded[] = $name.' (not registered)';
                continue;
            }
            $actual = $route->gatherMiddleware();
            $missing = array_values(array_diff($required, $actual));
            if ($missing) {
                $unguarded[] = $name.' (missing '.implode(', ', $missing).')';
            }
        }
        $record(
            $unguarded ? 'FAIL' : 'PASS',
            'Route access controls',
            $unguarded
                ? 'Required middleware absent: '.implode('; ', $unguarded)
                : 'Authentication, staff, HR review and claim rate-limit middleware are present.'
        );

        $oauthMissing = [];
        foreach (['client_id', 'client_secret'] as $key) {
            if (!filled(config("services.google.{$key}"))) {
                $oauthMissing[] = $key;
            }
        }
        $record(
            $oauthMissing ? 'WARN' : 'PASS',
            'Google OAuth',
            $oauthMissing
                ? 'Google configuration missing: '.implode(', ', $oauthMissing).'.'
                : 'Google client ID and secret are configured (values not displayed).'
        );
        $this->line('  Google Console redirect URI registration and real-browser sign-in require separate manual verification.');

        try {
            DB::connection()->select('SELECT 1 AS ready');
            $record('PASS', 'Primary database', 'Connection succeeded.');
        } catch (Throwable $e) {
            // Deliberately avoid outputting connection exception text, which may
            // contain credentials, hostnames, or other sensitive details.
            $record('FAIL', 'Primary database', 'Connection failed; check protected application logs.');
            $this->line("Summary: {$failed} failed, {$warned} warning(s).");
            return self::FAILURE;
        }

        $requiredTables = [
            'applications',
            'applicant_profiles',
            'oauth_identities',
            'applicant_application_links',
            'application_claim_requests',
            'applicant_audit_logs',
        ];

        try {
            $missingTables = array_values(array_filter(
                $requiredTables,
                fn (string $table) => !Schema::hasTable($table)
            ));
            $record(
                $missingTables ? 'FAIL' : 'PASS',
                'Portal tables',
                $missingTables ? 'Missing tables: '.implode(', ', $missingTables) : 'Legacy and new portal tables exist.'
            );

            if (!in_array('applications', $missingTables, true)) {
                $legacyFields = [
                    'id', 'vacancy_id', 'application_code', 'first_name',
                    'middle_name', 'last_name', 'sitio', 'barangay',
                    'municipality', 'zip', 'age', 'gender', 'civil_status',
                    'religion', 'disability', 'ethnic_group', 'email',
                    'phone', 'station_id', 'created_at', 'updated_at',
                ];
                $columns = Schema::getColumnListing('applications');
                $missingFields = array_values(array_diff($legacyFields, $columns));

                $record(
                    $missingFields ? 'FAIL' : 'PASS',
                    'Legacy applications schema',
                    $missingFields
                        ? 'Missing legacy columns: '.implode(', ', $missingFields)
                        : 'Original applications column contract is present.'
                );

                // A LIMIT 0 SELECT checks the consumer-facing read contract
                // without fetching or printing even one applicant record.
                try {
                    DB::select(
                        'SELECT application_code, first_name, last_name, email, '.
                        'vacancy_id, station_id, zip FROM applications LIMIT 0'
                    );
                    $record('PASS', 'External-style read', 'Legacy-column SELECT succeeded with zero records retrieved.');
                } catch (Throwable $e) {
                    $record('FAIL', 'External-style read', 'Legacy-column SELECT failed; consult protected logs.');
                }
            }
        } catch (Throwable $e) {
            $record('FAIL', 'Schema inspection', 'Could not inspect table schema; consult protected logs.');
        }

        if ($this->option('skip-secondary')) {
            $record('WARN', 'Secondary station database', 'Skipped explicitly; assigned-station detail pages remain unverified.');
        } else {
            try {
                DB::connection('mysql_2')->select('SELECT 1 AS ready');
                if (Schema::connection('mysql_2')->hasTable('stations')) {
                    $record('PASS', 'Secondary station database', 'Connection and stations table verified.');
                } else {
                    $record('WARN', 'Secondary station database', 'Connected but stations table is missing.');
                }
            } catch (Throwable $e) {
                $record('WARN', 'Secondary station database', 'Connection/table check failed; assigned-station detail pages require investigation.');
            }
        }

        $this->line("Summary: {$failed} failed, {$warned} warning(s).");
        $this->line('This is NOT a substitute for Google browser UAT, HR acceptance, or the actual third-party reader test.');

        return ($failed > 0 || ($this->option('strict') && $warned > 0))
            ? self::FAILURE
            : self::SUCCESS;
    }
}
