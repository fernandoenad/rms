<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Models\ApplicantProfile;
use App\Models\ApplicantNotificationPreference;
use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    public function update(Request $request)
    {
        $profile = ApplicantProfile::where('user_id', $request->user()->id)->firstOrFail();
        $data = $request->validate([
            'email_enabled' => 'nullable|boolean',
            'email_application_updates' => 'nullable|boolean',
            'email_assessment_updates' => 'nullable|boolean',
        ]);
        ApplicantNotificationPreference::updateOrCreate(
            ['applicant_profile_id' => $profile->id],
            [
                'email_enabled' => $request->boolean('email_enabled'),
                'email_application_updates' => $request->boolean('email_application_updates'),
                'email_assessment_updates' => $request->boolean('email_assessment_updates'),
            ]
        );
        return redirect(route('applicant.dashboard').'#notificationPreferences')
            ->with('status', 'Notification preferences saved. Email delivery is not yet active.');
    }
}
