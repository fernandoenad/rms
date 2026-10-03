<?php

namespace App\Http\Middleware;

use App\Services\AssessmentAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AssessmentCapability
{
    public function __construct(protected AssessmentAccessService $access) {}

    public function handle(Request $request, Closure $next, string $capability): Response
    {
        abort_unless(
            $this->access->allows($request->user(), $capability),
            403,
            'You do not have the required Assessment Center capability for this action.'
        );

        return $next($request);
    }
}
