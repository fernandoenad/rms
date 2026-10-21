<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApplicantAuditLog;
use App\Models\User;
use App\Support\ApplicantImpersonationPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApplicantSupportAccessController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth','admin']);
    }

    private function authorizeOwner(ApplicantImpersonationPolicy $policy): void
    {
        abort_unless($policy->isProvisioningAdmin(auth()->user()), 403,
            'Only designated support-access administrators may grant or revoke support viewing rights.');
    }

    public function index(ApplicantImpersonationPolicy $policy)
    {
        $this->authorizeOwner($policy);
        $users = User::query()->with('role')
            ->whereHas('role', fn ($q) => $q->where('level', 1)->where('status', 1))
            ->orderBy('name')->simplePaginate(30);
        $grants = DB::table('applicant_support_access_grants')
            ->whereNull('revoked_at')
            ->whereIn('user_id', $users->pluck('id'))
            ->pluck('user_id')->all();
        return view('admin.applicant-accounts.support-access', [
            'users' => $users, 'granted' => $grants,
            'policy' => $policy,
        ]);
    }

    public function grant(Request $request, User $user, ApplicantImpersonationPolicy $policy)
    {
        $this->authorizeOwner($policy);
        abort_unless($policy->isHr($user), 422, 'Only active HR administrators can receive support viewing rights.');
        // Bootstrap owners keep their privilege in environment configuration;
        // no need to create a second persisted grant for those accounts.
        abort_if($policy->isProvisioningAdmin($user), 422, 'Bootstrap administrator rights are managed in server configuration.');
        $data = $request->validate(['reason' => 'required|string|min:20|max:1000']);

        DB::transaction(function () use ($user, $data, $request) {
            DB::table('applicant_support_access_grants')->updateOrInsert(
                ['user_id' => $user->id],
                [
                    'reason' => $data['reason'], 'granted_by' => $request->user()->id,
                    'revoked_at' => null, 'revoked_by' => null, 'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
            ApplicantAuditLog::create([
                'actor_user_id' => $request->user()->id,
                'event_type' => 'support_access_granted',
                'metadata' => ['recipient_user_id' => $user->id, 'reason' => $data['reason']],
                'created_at' => now(),
            ]);
        });

        return back()->with('status', 'Support access granted. An existing verified staff Google identity is still required.');
    }

    public function revoke(Request $request, User $user, ApplicantImpersonationPolicy $policy)
    {
        $this->authorizeOwner($policy);
        abort_if($policy->isProvisioningAdmin($user), 403,
            'Bootstrap administrator rights must be removed from server configuration.');
        $data = $request->validate(['reason' => 'required|string|min:20|max:1000']);

        DB::transaction(function () use ($request, $user, $data) {
            $updated = DB::table('applicant_support_access_grants')
                ->where('user_id', $user->id)->whereNull('revoked_at')
                ->update(['revoked_at' => now(), 'revoked_by' => $request->user()->id, 'updated_at' => now()]);
            if ($updated) {
                ApplicantAuditLog::create([
                    'actor_user_id' => $request->user()->id,
                    'event_type' => 'support_access_revoked',
                    'metadata' => ['recipient_user_id' => $user->id, 'reason' => $data['reason']],
                    'created_at' => now(),
                ]);
            }
        });

        return back()->with('status', 'Support access revoked. Any active support view is stopped on its next request.');
    }
}
