<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Models\ApplicantProfile;
use App\Models\ApplicantNotification;
use App\Models\ApplicantNotificationPreference;
use App\Models\ApplicantApplicationLink;
use App\Models\ApplicationClaimRequest;
use App\Services\Applicant\ApplicationClaimService;
use Illuminate\Http\Request;
use App\Models\ApplicantAuditLog;
use App\Services\Applicant\ProfileReferenceData;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class PortalController extends Controller
{
    private const PROFILE_FIELDS = [
        'first_name','middle_name','last_name','sitio','barangay','municipality','zip',
        'date_of_birth','age','gender','civil_status','religion','disability','ethnic_group','email','phone','district_id',
    ];

    public function login()
    {
        return view('applicant.login');
    }

    public function dashboard(Request $request)
    {
        $profile = ApplicantProfile::firstOrCreate(['user_id' => $request->user()->id]);
        $links = ApplicantApplicationLink::with('application.vacancy')
            ->where('applicant_profile_id', $profile->id)->latest()->get();
        $claims = ApplicationClaimRequest::where('applicant_profile_id', $profile->id)
            ->latest()->get();
        // Build a private, read-only activity feed from the applicant's own records.
        // This does not expose another applicant's applications or change legacy data.
        $updates = collect();
        if (!$profile->profile_completed_at || !$profile->date_of_birth) {
            $updates->push([
                'type' => 'action',
                'title' => 'Complete your master profile',
                'message' => 'Save your profile before submitting a new application.',
                'url' => route('applicant.profile.edit'),
                'date' => null,
            ]);
        }
        foreach ($claims as $claim) {
            $copy = match ($claim->status) {
                'clarification' => ['action', 'HR requests clarification', 'Contact HR through the official channel about your claim.'],
                'approved' => ['success', 'Historical claim approved', 'Your approved historical application is available in My Applications.'],
                'rejected' => ['warning', 'Historical claim not approved', 'Contact HR if you need to dispute this decision.'],
                'cancelled' => ['warning', 'Historical claim approval cancelled', 'Your historical application access was revoked. Contact HR for guidance.'],
                default => ['info', 'Historical claim under review', 'HR has not yet completed verification.'],
            };
            $updates->push([
                'type' => $copy[0],
                'title' => $copy[1],
                'message' => $claim->claimed_application_code.' — '.$copy[2],
                'url' => route('applicant.dashboard').'#claimHistoryTitle',
                'date' => $claim->updated_at,
            ]);
        }
        $updates = $updates->sortByDesc(fn ($update) => $update['date']?->timestamp ?? PHP_INT_MAX)->take(8)->values();
        $notifications = ApplicantNotification::where('applicant_profile_id', $profile->id)
            ->latest()->limit(15)->get();
        $unreadCount = ApplicantNotification::where('applicant_profile_id', $profile->id)
            ->whereNull('read_at')->count();
        // Viewing an applicant during support must never initialize a new
        // preference row; use a transient model when it does not yet exist.
        $notificationPreferences = $request->session()->has('rms_impersonation_admin_id')
            ? ApplicantNotificationPreference::firstOrNew(['applicant_profile_id' => $profile->id])
            : ApplicantNotificationPreference::firstOrCreate(['applicant_profile_id' => $profile->id]);
        return view('applicant.dashboard', compact('profile', 'links', 'claims', 'updates', 'notifications', 'unreadCount', 'notificationPreferences'));
    }

    public function edit(Request $request)
    {
        $profile = ApplicantProfile::firstOrCreate(['user_id' => $request->user()->id]);
        return view('applicant.profile', compact('profile'));
    }

    public function options(ProfileReferenceData $references)
    {
        try {
            return response()->json($references->all())
                ->header('Cache-Control', 'private, no-store');
        } catch (Throwable $e) {
            report($e);
            return response()->json([
                'message' => 'Location and choice lists are temporarily unavailable. Please retry later.',
            ], 503);
        }
    }

    public function update(Request $request, ProfileReferenceData $references)
    {
        // These options are authoritative values from the existing HRMIS
        // secondary database, not arbitrary strings supplied by the browser.
        try {
            $options = $references->all();
        } catch (Throwable $e) {
            report($e);
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'The district and dropdown references are temporarily unavailable. Please retry.',
                ], 503);
            }
            return back()->withInput()->withErrors([
                'municipality' => 'Reference lists are temporarily unavailable. Please try again later.',
            ]);
        }

        $data = $request->validate([
            'first_name' => 'required|string|min:2|max:255',
            'middle_name' => 'required|string|min:1|max:255',
            'last_name' => 'required|string|min:2|max:255',
            'sitio' => 'required|string|max:255',
            'barangay' => 'required|string|max:255',
            'municipality' => ['required', 'string', Rule::in($options['municipalities'])],
            'district_id' => ['nullable', 'integer', Rule::in(array_column($options['districts'], 'id'))],
            'zip' => 'required|integer|between:1000,9999',
            'date_of_birth' => 'required|date_format:Y-m-d|before_or_equal:today',
            'gender' => ['required', 'string', Rule::in($options['genders'])],
            'civil_status' => ['required', 'string', Rule::in($options['civil_statuses'])],
            'religion' => 'required|string|max:255',
            'disability' => 'required|string|max:255',
            'ethnic_group' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => ['required','regex:/^09\d{9}$/'],
        ]);
        // An age sent by the browser must NEVER be trusted or persisted.
        $data['age'] = Carbon::createFromFormat('!Y-m-d', $data['date_of_birth'])->age;
        if ($data['age'] < 18 || $data['age'] > 70) {
            throw ValidationException::withMessages([
                'date_of_birth' => 'Applicants must be between 18 and 70 years old.',
            ]);
        }

        $profile = ApplicantProfile::firstOrCreate(['user_id' => $request->user()->id]);
        $profile->update($data + ['profile_completed_at' => now()]);
        ApplicantAuditLog::create([
            'actor_user_id' => $request->user()->id,
            'applicant_profile_id' => $profile->id,
            'event_type' => 'master_profile_updated',
            'metadata' => ['fields' => array_keys($data)],
        ]);
        // Do not update any existing application record.
        // Resume only an internally recorded vacancy ID, never an arbitrary return URL.
        $pendingVacancyId = $request->session()->pull('applicant_pending_vacancy_id');
        $pendingUserId = $request->session()->pull('applicant_pending_vacancy_user_id');
        $pendingStartedAt = $request->session()->pull('applicant_pending_vacancy_started_at');
        $pendingIsRecent = is_numeric($pendingStartedAt)
            && (int) $pendingStartedAt <= now()->timestamp
            && (int) $pendingStartedAt >= now()->subMinutes(30)->timestamp;
        if ($pendingVacancyId && $pendingIsRecent && (int) $pendingUserId === (int) $request->user()->id) {
            $vacancy = \App\Models\Vacancy::find($pendingVacancyId);
            if ($vacancy && $vacancy->isOpenForApplications()) {
                $destination = route('applicant.apply.review', $vacancy);
                $message = 'Profile saved. Review your application before submitting.';
                if ($request->expectsJson()) {
                    return response()->json(['message' => $message, 'redirect' => $destination, 'age' => $data['age']]);
                }
                return redirect($destination)->with('status', $message);
            }
        }
        $destination = route('applicant.dashboard');
        $message = 'Profile saved. Existing applications remain unchanged.';
        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'redirect' => $destination, 'age' => $data['age']]);
        }
        return redirect($destination)->with('status', $message);
    }

    public function claim(Request $request, ApplicationClaimService $claims)
    {
        $data = $request->validate([
            'application_code'=>'required|string|max:255',
            'email'=>'required|email|max:255',
        ]);
        $profile = ApplicantProfile::firstOrCreate(['user_id' => $request->user()->id]);
        $claim = $claims->request($profile, $data['application_code'], $data['email']);
        $message = 'Claim request received. HR will review it in RMS.';
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'application_code' => $claim->claimed_application_code,
                'status' => $claim->status,
            ]);
        }
        return redirect()->route('applicant.dashboard')->with('status', $message);
    }
}
