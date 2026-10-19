<?php

namespace App\Services\Applicant;

use App\Models\ApplicantProfile;

class ApplicantProfileToApplicationMapper
{
    private const FIELDS = [
        'first_name', 'middle_name', 'last_name', 'sitio', 'barangay',
        'municipality', 'zip', 'age', 'gender', 'civil_status',
        'religion', 'disability', 'ethnic_group', 'email', 'phone',
    ];

    public function map(ApplicantProfile $profile): array
    {
        $snapshot = $profile->only(self::FIELDS);
        // The legacy applications table stores only age, not date of birth.
        // Recompute it on submission so a saved profile does not grow stale.
        if ($profile->date_of_birth) {
            $snapshot['age'] = $profile->date_of_birth->age;
        }
        return $snapshot;
    }
}
