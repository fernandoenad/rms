<?php

namespace App\Services\Applicant;

use App\Models\ApplicantAuditLog;
use App\Models\ApplicantProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ApplicantImpersonationSession
{
    public function start(Request $request, User $admin, ApplicantProfile $profile, string $mode, string $reason): Response
    {
        $target = User::with('role')->findOrFail($profile->user_id);
        abort_if($target->role !== null || $target->id === $admin->id, 403,
            'Only non-staff applicant accounts may be viewed in support mode.');
        abort_if($request->session()->has('rms_impersonation_admin_id'), 403,
            'Nested applicant impersonation is prohibited.');

        $sessionId = (string) Str::uuid();
        $expiresAt = now()->addMinutes(10)->timestamp;
        ApplicantAuditLog::create([
            'actor_user_id' => $admin->id,
            'applicant_profile_id' => $profile->id,
            'event_type' => $mode === 'support' ? 'support_impersonation_started' : 'test_impersonation_started',
            'metadata' => [
                'target_user_id' => $target->id, 'reason' => $reason,
                'read_only' => true, 'mode' => $mode,
                'session_id' => $sessionId, 'expires_at' => $expiresAt,
                'ip' => $request->ip(),
            ],
            'created_at' => now(),
        ]);
        $request->session()->put([
            'rms_impersonation_admin_id' => $admin->id,
            'rms_impersonation_target_id' => $target->id,
            'rms_impersonation_profile_id' => $profile->id,
            'rms_impersonation_mode' => $mode,
            'rms_impersonation_expires_at' => $expiresAt,
            'rms_impersonation_session_id' => $sessionId,
        ]);
        Auth::login($target);
        $request->session()->regenerate();

        return redirect()->route('applicant.dashboard')
            ->header('Cache-Control', 'private, no-store');
    }

    public function stop(Request $request, string $reason = 'manual'): Response
    {
        $session = $request->session();
        $adminId = (int) $session->get('rms_impersonation_admin_id');
        $targetId = (int) $session->get('rms_impersonation_target_id');
        $profileId = (int) $session->get('rms_impersonation_profile_id');
        $mode = (string) $session->get('rms_impersonation_mode', 'test');
        abort_unless($adminId > 0 && $targetId > 0 && $profileId > 0
            && (int) $request->user()?->id === $targetId, 403);

        $admin = User::with('role')->find($adminId);
        ApplicantAuditLog::create([
            'actor_user_id' => $adminId,
            'applicant_profile_id' => $profileId,
            'event_type' => $mode === 'support' ? 'support_impersonation_ended' : 'test_impersonation_ended',
            'metadata' => [
                'target_user_id' => $targetId, 'mode' => $mode,
                'reason' => $reason,
                'session_id' => $session->get('rms_impersonation_session_id'),
                'ip' => $request->ip(),
            ],
            'created_at' => now(),
        ]);

        if (!$admin || (int) ($admin->role?->level ?? 0) !== 1
            || (int) ($admin->role?->status ?? 0) !== 1) {
            Auth::logout();
            $session->invalidate();
            $session->regenerateToken();
            return redirect()->route('login')->withErrors([
                'access' => 'Administrator authorization is no longer active; please sign in again.',
            ]);
        }

        Auth::login($admin);
        $session->forget([
            'rms_impersonation_admin_id','rms_impersonation_target_id',
            'rms_impersonation_profile_id','rms_impersonation_mode',
            'rms_impersonation_expires_at','rms_impersonation_session_id',
        ]);
        $session->regenerate();
        return redirect()->route('admin.applicant_accounts.index')
            ->with('status', $reason === 'expired'
                ? 'Support viewing session expired and your administrator account was restored.'
                : 'Read-only applicant view ended. Returned to your administrator account.');
    }
}
