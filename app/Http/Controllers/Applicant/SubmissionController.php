<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Models\ApplicantProfile;
use App\Models\ApplicantApplicationLink;
use App\Models\Application;
use App\Models\Vacancy;
use App\Services\Applicant\ApplicantProfileToApplicationMapper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmissionController extends Controller
{
    public function review(Request $request, Vacancy $vacancy)
    {
        abort_unless($vacancy->isOpenForApplications(), 404);
        $profile = ApplicantProfile::where('user_id', $request->user()->id)->firstOrFail();
        if (!$profile->profile_completed_at) {
            return redirect()->route('applicant.profile.edit')
                ->with('status', 'Complete your master profile before applying.');
        }
        return view('applicant.review', compact('vacancy', 'profile'));
    }

    public function store(Request $request, Vacancy $vacancy, ApplicantProfileToApplicationMapper $mapper)
    {
        $request->validate(['confirm' => 'accepted']);
        $profile = ApplicantProfile::where('user_id', $request->user()->id)->firstOrFail();
        if (!$profile->profile_completed_at) {
            throw ValidationException::withMessages(['profile' => 'Complete your master profile first.']);
        }

        DB::transaction(function () use ($vacancy, $profile, $mapper) {
            // Re-read the profile under lock so submission reflects saved values at commit time.
            $profile = ApplicantProfile::whereKey($profile->id)->lockForUpdate()->firstOrFail();
            // Serialize submissions for the same vacancy and prevent duplicate account submissions.
            $lockedVacancy = Vacancy::whereKey($vacancy->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedVacancy->isOpenForApplications(), 403);
            $alreadyOwned = ApplicantApplicationLink::where('applicant_profile_id', $profile->id)
                ->whereHas('application', fn ($q) => $q->where('vacancy_id', $vacancy->id))->exists();
            if ($alreadyOwned || Application::where('vacancy_id', $vacancy->id)
                ->whereRaw('LOWER(email) = ?', [mb_strtolower($profile->email)])->exists()) {
                throw ValidationException::withMessages(['application' => 'An application for this vacancy already exists.']);
            }

            $data = $mapper->map($profile);
            $data['vacancy_id'] = $vacancy->id;
            $data['station_id'] = $vacancy->office_level;
            $data['application_code'] = $vacancy->cycle.'-'.$vacancy->id;
            $application = Application::create($data);
            $application->update(['application_code' => $data['application_code'].'-'.$application->id]);
            ApplicantApplicationLink::create([
                'applicant_profile_id' => $profile->id,
                'application_id' => $application->id,
                'link_method' => 'new_submission',
                'verified_at' => now(),
            ]);
        });

        return redirect()->route('applicant.dashboard')->with('status', 'Application submitted successfully.');
    }
}
