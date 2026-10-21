<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Applicant\ApplicantImpersonationSession;
use App\Support\ApplicantImpersonationPolicy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A fail-closed web-layer boundary for both local and production support
 * impersonation, including expiry/revocation and route allowlisting.
 */
class LimitApplicantImpersonation
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->session()->has('rms_impersonation_admin_id')) {
            return $next($request);
        }

        // Always preserve the explicit POST exit even when expired/disabled.
        if ($request->routeIs('admin.applicant_accounts.impersonation.stop')
            && $request->isMethod('POST')) {
            return $next($request);
        }

        $policy = app(ApplicantImpersonationPolicy::class);
        $lifecycle = app(ApplicantImpersonationSession::class);
        $admin = User::with('role')->find((int) $request->session()->get('rms_impersonation_admin_id'));
        $mode = (string) $request->session()->get('rms_impersonation_mode', 'test');
        $targetId = (int) $request->session()->get('rms_impersonation_target_id');
        $expiresAt = (int) $request->session()->get('rms_impersonation_expires_at');
        $current = $request->user();

        // The session must refer to exactly the currently authenticated target.
        if ($targetId < 1 || !$current || (int) $current->id !== $targetId) {
            abort(403, 'Impersonation session identity mismatch.');
        }
        if ($expiresAt <= now()->timestamp || $expiresAt === 0) {
            return $lifecycle->stop($request, 'expired');
        }
        if (!$admin || !$policy->isActiveModeAllowed($mode, $admin)
            || $current->role !== null) {
            return $lifecycle->stop($request, 'revoked');
        }

        $allowed = [
            'guest.index','guest.vacancies.index','guest.vacancies.show',
            'applicant.dashboard','applicant.profile.edit','guest.applications.show',
        ];
        if (!$request->isMethod('GET') || !$request->routeIs(...$allowed)) {
            abort(403, 'Read-only applicant support: this action is prohibited. Return to Administrator first.');
        }

        $response = $next($request);
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        return $response;
    }
}
