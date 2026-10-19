<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Models\ApplicantProfile;
use App\Models\ApplicantApplicationLink;
use App\Models\ApplicantNotification;
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
        $profile = ApplicantProfile::firstOrCreate(['user_id' => $request->user()->id]);
        $existingLink = ApplicantApplicationLink::with('application')
            ->where('applicant_profile_id', $profile->id)
            ->whereHas('application', fn ($query) => $query->where('vacancy_id', $vacancy->id))
            ->first();
        if ($existingLink) {
            $request->session()->forget(['applicant_pending_vacancy_id', 'applicant_pending_vacancy_user_id', 'applicant_pending_vacancy_started_at']);
            return redirect()->route('guest.applications.show', $existingLink->application)
                ->with('status', 'You already submitted an application for this vacancy.');
        }
        abort_unless($vacancy->isOpenForApplications(), 404);
        if (!$profile->profile_completed_at) {
            $request->session()->put('applicant_pending_vacancy_id', $vacancy->id);
            $request->session()->put('applicant_pending_vacancy_user_id', $request->user()->id);
            $request->session()->put('applicant_pending_vacancy_started_at', now()->timestamp);
            return redirect()->route('applicant.profile.edit')
                ->with('status', 'Complete your master profile before applying.');
        }
        $request->session()->forget(['applicant_pending_vacancy_id', 'applicant_pending_vacancy_user_id']);
        return view('applicant.review', compact('vacancy', 'profile'));
    }

    public function store(Request $request, Vacancy $vacancy, ApplicantProfileToApplicationMapper $mapper)
    {
        $request->validate(['confirm' => 'accepted']);
        $profile = ApplicantProfile::firstOrCreate(['user_id' => $request->user()->id]);
        if (!$profile->profile_completed_at) {
            throw ValidationException::withMessages(['profile' => 'Complete your master profile first.']);
        }

        $submittedApplication = DB::transaction(function () use ($vacancy, $profile, $mapper) {
            // Re-read the profile under lock so submission reflects saved values at commit time.
            $profile = ApplicantProfile::whereKey($profile->id)->lockForUpdate()->firstOrFail();
            if (!$profile->profile_completed_at) {
                throw ValidationException::withMessages(['profile' => 'Complete your master profile first.']);
            }
            // Serialize submissions for the same vacancy and prevent duplicate account submissions.
            $lockedVacancy = Vacancy::whereKey($vacancy->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedVacancy->isOpenForApplications(), 403);
            $alreadyOwned = ApplicantApplicationLink::where('applicant_profile_id', $profile->id)
                ->whereHas('application', fn ($q) => $q->where('vacancy_id', $vacancy->id))->exists();
            if ($alreadyOwned || Application::where('vacancy_id', $vacancy->id)
                ->whereRaw('LOWER(TRIM(email)) = ?', [mb_strtolower(trim((string) $profile->email))])->exists()) {
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
            ApplicantNotification::firstOrCreate(
                ['event_key' => 'application:'.$application->id.':submitted'],
                ['applicant_profile_id' => $profile->id, 'type' => 'application_submitted',
                 'title' => 'Application received',
                 'message' => 'Application '.$application->application_code.' has been submitted.']
            );
            return $application;
        });

        $message = 'Application submitted successfully.';
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'application_code' => $submittedApplication->application_code,
                'redirect' => route('guest.applications.show', $submittedApplication),
            ], 201);
        }
        return redirect()->route('applicant.dashboard')->with('status', $message);
    }
}
