<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Models\ApplicantProfile;
use App\Models\ApplicantApplicationLink;
use App\Models\ApplicationClaimRequest;
use App\Services\Applicant\ApplicationClaimService;
use Illuminate\Http\Request;
use App\Models\ApplicantAuditLog;

class PortalController extends Controller
{
    private const PROFILE_FIELDS = [
        'first_name','middle_name','last_name','sitio','barangay','municipality','zip',
        'age','gender','civil_status','religion','disability','ethnic_group','email','phone',
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
        return view('applicant.dashboard', compact('profile', 'links', 'claims'));
    }

    public function edit(Request $request)
    {
        $profile = ApplicantProfile::firstOrCreate(['user_id' => $request->user()->id]);
        return view('applicant.profile', compact('profile'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'first_name'=>'required|string|min:2|max:255',
            'middle_name'=>'required|string|min:1|max:255',
            'last_name'=>'required|string|min:2|max:255',
            'sitio'=>'required|string|max:255',
            'barangay'=>'required|string|max:255',
            'municipality'=>'required|string|max:255',
            'zip'=>'required|integer|between:1000,9999',
            'age'=>'required|integer|between:18,70',
            'gender'=>'required|string|max:255',
            'civil_status'=>'required|string|max:255',
            'religion'=>'required|string|max:255',
            'disability'=>'required|string|max:255',
            'ethnic_group'=>'required|string|max:255',
            'email'=>'required|email|max:255',
            'phone'=>['required','regex:/^09\\d{9}$/'],
        ]);
        $profile = ApplicantProfile::firstOrCreate(['user_id' => $request->user()->id]);
        $profile->update($data + ['profile_completed_at' => now()]);
        ApplicantAuditLog::create([
            'actor_user_id' => $request->user()->id,
            'applicant_profile_id' => $profile->id,
            'event_type' => 'master_profile_updated',
            'metadata' => ['fields' => array_keys($data)],
        ]);
        // Do not update any existing application record.
        return redirect()->route('applicant.dashboard')->with('status', 'Profile saved. Existing applications remain unchanged.');
    }

    public function claim(Request $request, ApplicationClaimService $claims)
    {
        $data = $request->validate([
            'application_code'=>'required|string|max:255',
            'email'=>'required|email|max:255',
        ]);
        $profile = ApplicantProfile::firstOrCreate(['user_id' => $request->user()->id]);
        $claims->request($profile, $data['application_code'], $data['email']);
        return redirect()->route('applicant.dashboard')
            ->with('status', 'Claim request received. HR will review it in RMS.');
    }
}
