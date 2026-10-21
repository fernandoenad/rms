<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keep test impersonation read-only, irrespective of what controls individual
 * applicant controllers expose. The only permitted write is POST Stop.
 */
class LimitApplicantImpersonation
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->session()->has('rms_impersonation_admin_id')) {
            return $next($request);
        }

        // The switch-back action must always remain available even if the
        // feature is subsequently disabled by configuration.
        if ($request->routeIs('admin.applicant_accounts.impersonation.stop')) {
            return $next($request);
        }

        if (!app()->environment(['local', 'testing', 'staging'])
            || !config('app.applicant_impersonation_enabled', false)) {
            abort(403, 'Test impersonation is unavailable. Use Return to Administrator.');
        }

        $allowed = [
            'guest.index', 'guest.vacancies.index', 'guest.vacancies.show',
            'applicant.dashboard', 'applicant.profile.edit',
            'guest.applications.show',
        ];
        $actor = (int) $request->session()->get('rms_impersonation_target_id');
        if ($actor < 1 || (int) $request->user()?->id !== $actor
            || !$request->isMethod('GET')
            || !$request->routeIs(...$allowed)) {
            abort(403, 'Read-only test impersonation: this action is not available. Return to Administrator first.');
        }

        return $next($request);
    }
}
