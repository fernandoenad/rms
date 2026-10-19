<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Models\ApplicantNotification;
use App\Models\ApplicantProfile;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function read(Request $request, ApplicantNotification $notification)
    {
        $profile = ApplicantProfile::where('user_id', $request->user()->id)->firstOrFail();
        abort_unless((int) $notification->applicant_profile_id === (int) $profile->id, 404);
        if (!$notification->read_at) {
            $notification->update(['read_at' => now()]);
        }
        return redirect(route('applicant.dashboard').'#updatesTitle');
    }
}
