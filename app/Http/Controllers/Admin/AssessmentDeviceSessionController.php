<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApplicantAuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssessmentDeviceSessionController extends Controller
{
    private function authorizeHr(): void
    {
        $role = auth()->user()?->role;
        abort_unless($role && (int)$role->level === 1 && (int)$role->status === 1, 403);
    }

    public function index()
    {
        $this->authorizeHr();

        $users = DB::table('assessment_device_sessions as s')
            ->join('users as u', 'u.id', '=', 's.user_id')
            ->whereNull('s.released_at')
            ->select('u.id', 'u.name', 'u.email',
                DB::raw('COUNT(*) AS active_attempts'), DB::raw('MAX(s.updated_at) AS last_activity'))
            ->groupBy('u.id','u.name','u.email')
            ->orderByDesc('last_activity')
            ->simplePaginate(30);

        return view('admin.assessment_center.device_sessions', ['users' => $users]);
    }

    public function release(Request $request, User $user)
    {
        $this->authorizeHr();
        $data = $request->validate([
            'reason' => 'required|string|min:20|max:1000',
        ]);

        $count = DB::transaction(function () use ($request, $user, $data) {
            // Take the same user-row lock that assessment session claims take.
            // This prevents a race between a support reset and a new claim.
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $affected = DB::table('assessment_device_sessions')
                ->where('user_id', $user->id)->whereNull('released_at')
                ->update(['released_at' => now(), 'updated_at' => now()]);
            if ($affected) {
                ApplicantAuditLog::create([
                    'actor_user_id' => $request->user()->id,
                    'event_type' => 'assessment_device_sessions_reset_by_hr',
                    'metadata' => [
                        'target_user_id' => $user->id,
                        'released_attempts' => $affected,
                        'reason' => $data['reason'],
                    ],
                    'created_at' => now(),
                ]);
            }
            return $affected;
        });

        return redirect()->route('admin.assessment_device_sessions.index')
            ->with('status', $count.' device-session lock(s) released. This does not reset exam time, attempts, submissions or scores.');
    }
}
