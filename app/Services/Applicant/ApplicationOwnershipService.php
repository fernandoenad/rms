<?php

namespace App\Services\Applicant;

use App\Models\ApplicantApplicationLink;
use App\Models\ApplicantProfile;
use App\Models\Application;
use Illuminate\Http\Request;

class ApplicationOwnershipService
{
    public function owns(Request $request, Application $application): bool
    {
        $user = $request->user();
        if (!$user) return false;
        return ApplicantApplicationLink::query()
            ->where('application_id', $application->id)
            ->whereHas('profile', fn ($query) => $query->where('user_id', $user->id))
            ->exists();
    }

    public function authorize(Request $request, Application $application): void
    {
        abort_unless($this->owns($request, $application), 403, 'You do not own this application.');
    }
}
