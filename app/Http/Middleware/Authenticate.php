<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        if ($request->expectsJson()) {
            // AJAX requests must receive 401 JSON, never staff-login HTML.
            return null;
        }

        // Staff identity linking requires a staff session. If it expires,
        // return to the staff sign-in, not applicant onboarding.
        if ($request->routeIs('applicant.staff.google.*')) {
            return route('login');
        }

        // The shared legacy auth guard also protects applicant application
        // detail and assessment routes. Distinguish them by trusted route
        // names, not URL referers, supplied query parameters or session state.
        if ($request->routeIs(
            'applicant.*',
            'guest.applications.*',
            'guest.assessments.*',
            'guest.skills.*'
        )) {
            return route('applicant.login');
        }

        return route('login');
    }
}
