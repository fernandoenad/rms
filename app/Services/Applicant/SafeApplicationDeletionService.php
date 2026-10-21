<?php

namespace App\Services\Applicant;

use App\Models\ApplicantAuditLog;
use App\Models\Application;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SafeApplicationDeletionService
{
    /**
     * Historical and assessment evidence must never be silently cascaded away
     * when an HR operator removes an unused application.
     */
    private const DEPENDENCIES = [
        'applicant_application_links' => 'an applicant-account ownership link',
        'application_claim_requests' => 'historical account-claim records',
        'assessments' => 'an assessment or score record',
        'exam_attempts' => 'written examination attempts',
        'skill_test_attempts' => 'skills-test attempts',
        'exam_assignments' => 'written examination assignments',
        'skill_test_assignments' => 'skills-test assignments',
        'assessment_group_attempt_locks' => 'written examination set locks',
        'skill_test_group_attempt_locks' => 'skills-test set locks',
        'assessment_accommodations' => 'approved assessment accommodations',
        'assessment_incidents' => 'assessment incident reports',
        'assessment_nullification_items' => 'Board assessment-nullification evidence',
        'applicant_integrity_flags' => 'applicant integrity-review records',
        'applicant_audit_logs' => 'applicant audit records',
        'inquiries' => 'application correspondence or processing history',
    ];

    public function blockingReasons(Application $application): array
    {
        $reasons = [];
        // Consistent with the existing UI, tagged/taken-in applicants must
        // not be permanently removed through the delete action.
        if ($application->station_id !== null && (int) $application->station_id !== -1) {
            $reasons[] = 'the application has already been tagged or taken in';
        }

        foreach (self::DEPENDENCIES as $table => $label) {
            // Old/staged databases might not yet have the latest module.
            if (Schema::hasTable($table)
                && DB::table($table)->where('application_id', $application->id)->exists()) {
                $reasons[] = $label;
            }
        }

        return $reasons;
    }

    /**
     * Returns blocking reasons if no deletion occurred, or [] on success.
     * Lock + transaction prevents partial inquiry removal if a FK blocks the
     * application deletion. Audit metadata preserves no applicant PII.
     */
    public function deleteUnused(Application $application, int $actorUserId): array
    {
        return DB::transaction(function () use ($application, $actorUserId) {
            $locked = Application::query()->whereKey($application->id)
                ->lockForUpdate()->firstOrFail();
            $reasons = $this->blockingReasons($locked);
            if ($reasons !== []) {
                return $reasons;
            }

            // A legacy application with no protected history may be deleted.
            // The inquiry table has no FK, but at this point it has no rows.
            $code = $locked->application_code;
            $id = (int) $locked->id;
            $locked->delete();

            ApplicantAuditLog::create([
                'actor_user_id' => $actorUserId,
                'application_id' => $id,
                'event_type' => 'unused_application_deleted_by_hr',
                'metadata' => ['application_code' => $code],
                'created_at' => now(),
            ]);
            return [];
        });
    }

    public function isForeignKeyFailure(QueryException $error): bool
    {
        $sqlState = (string) ($error->errorInfo[0] ?? $error->getCode());
        return in_array($sqlState, ['23000', '23503', '19'], true);
    }
}
