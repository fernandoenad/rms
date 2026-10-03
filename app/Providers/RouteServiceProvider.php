<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/admin';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Assessment limits are keyed to the applicant session / attempt, not IP.
        // Large testing venues commonly place many applicants behind one NAT/public IP.
        $assessmentKey = function (Request $request): string {
            $email = (string) $request->session()->get('guest_email', 'anonymous');
            $attempt = $request->route('attempt');
            $application = $request->route('application');

            $attemptId = is_object($attempt) ? ($attempt->id ?? 'none') : ($attempt ?: 'none');
            $applicationId = is_object($application) ? ($application->id ?? 'none') : ($application ?: 'none');

            return hash('sha256', $email.'|'.$applicationId.'|'.$attemptId);
        };

        RateLimiter::for('assessment-start', fn (Request $request) =>
            Limit::perMinute(30)->by($assessmentKey($request))
        );
        RateLimiter::for('assessment-take', fn (Request $request) =>
            Limit::perMinute(120)->by($assessmentKey($request))
        );
        RateLimiter::for('assessment-review', fn (Request $request) =>
            Limit::perMinute(60)->by($assessmentKey($request))
        );
        RateLimiter::for('assessment-answer', fn (Request $request) =>
            Limit::perMinute(180)->by($assessmentKey($request))
        );
        RateLimiter::for('assessment-submit', fn (Request $request) =>
            Limit::perMinute(20)->by($assessmentKey($request))
        );
        RateLimiter::for('assessment-event', fn (Request $request) =>
            Limit::perMinute(60)->by($assessmentKey($request))
        );

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}
