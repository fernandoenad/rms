<?php

namespace App\Http\Middleware;

use App\Models\ExamAttempt;
use App\Models\SkillTestAttempt;
use App\Services\Applicant\ApplicationOwnershipService;
use App\Services\AssessmentDeviceSessionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireAssessmentDeviceSession
{
    public function handle(Request $request, Closure $next, string $kind): Response
    {
        abort_unless(in_array($kind, ['written', 'skills'], true), 404);
        abort_unless($request->user(), 401);

        $attempt = $request->route('attempt');
        abort_unless(($kind === 'written' && $attempt instanceof ExamAttempt)
            || ($kind === 'skills' && $attempt instanceof SkillTestAttempt), 404);

        $attempt->loadMissing('application');
        app(ApplicationOwnershipService::class)->authorize($request, $attempt->application);

        app(AssessmentDeviceSessionService::class)->claim($request, $kind, (int) $attempt->id);
        return $next($request);
    }
}
