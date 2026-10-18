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
        return $profile->only(self::FIELDS);
    }
}
