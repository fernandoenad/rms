<?php

namespace App\Services;

use App\Models\AssessmentPermission;
use App\Models\User;

class AssessmentAccessService
{
    public const CAPABILITIES = [
        'author'=>'Author assessment content and settings',
        'reviewer'=>'Review/approve and publish assessment content',
        'monitor'=>'Monitor live attempts, incidents, extensions and retakes',
        'evaluator'=>'Finalize or approve skills-test scores',
        'release'=>'Release results, archive/freeze assessments',
    ];

    public function allows(?User $user, string $capability): bool
    {
        if (!$user) return false;

        if ((int) optional($user->role)->level === 1) {
            return true;
        }

        $permissions = AssessmentPermission::where('user_id',$user->id)->pluck('capability');

        // Backward-compatible rollout: users with no explicit assessment
        // capability records retain their existing access until permissions
        // are intentionally configured for them.
        if ($permissions->isEmpty()) {
            return true;
        }

        return $permissions->contains($capability);
    }
}
