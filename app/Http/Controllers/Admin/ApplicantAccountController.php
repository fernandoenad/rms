<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApplicantAuditLog;
use App\Models\ApplicantProfile;
use App\Models\Application;
use App\Models\OAuthIdentity;
use App\Models\User;
use App\Services\Applicant\HistoricalApplicationLinkingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ApplicantAccountController extends Controller
{
    private function authorizeHr(): void
    {
        $role = auth()->user()?->role;
        abort_unless($role && (int) $role->level === 1 && (int) $role->status === 1, 403);
    }

    private function impersonationEnabled(): bool
    {
        return app()->environment(['local','testing','staging'])
            && (bool) config('app.applicant_impersonation_enabled', false);
    }

    public function index(Request $request)
    {
        $this->authorizeHr();
        $data = $request->validate(['search' => 'nullable|string|max:255']);
        $search = trim((string) ($data['search'] ?? ''));
        $query = ApplicantProfile::with('user:id,name,email')
            ->withCount('applicationLinks')->orderByDesc('id');
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', fn ($u) => $u
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%'))
                ->orWhere('first_name', 'like', '%'.$search.'%')
                ->orWhere('last_name', 'like', '%'.$search.'%')
                ->orWhereHas('applicationLinks.application', fn ($a) =>
                    $a->where('application_code', $search));
            });
        }
        return view('admin.applicant-accounts.index', [
            'profiles' => $query->paginate(30)->withQueryString(),
            'search' => $search,
            'impersonationEnabled' => $this->impersonationEnabled(),
        ]);
    }

    public function show(ApplicantProfile $profile)
    {
        $this->authorizeHr();
        $profile->load([
            'user.role', 'applicationLinks.application.vacancy',
        ]);
        $email = mb_strtolower(trim((string) $profile->user?->email));
        $verifiedIdentity = $profile->user && OAuthIdentity::where('user_id', $profile->user_id)
            ->where('provider','google')->where('email_verified',true)
            ->whereRaw('LOWER(TRIM(provider_email)) = ?', [$email])->exists();

        // Show all historical records matching the verified login email, along
        // with any conflicting ownership, without silently reassigning them.
        $matches = Application::query()
            ->leftJoin('applicant_application_links as l', 'l.application_id', '=', 'applications.id')
            ->leftJoin('applicant_profiles as p', 'p.id', '=', 'l.applicant_profile_id')
            ->leftJoin('users as u', 'u.id', '=', 'p.user_id')
            ->whereRaw('LOWER(TRIM(applications.email)) = ?', [$email])
            ->select('applications.id','applications.application_code','applications.email',
                'l.applicant_profile_id as linked_profile_id','l.link_method',
                'u.email as linked_account_email')
            ->orderByDesc('applications.id')->limit(100)->get();

        return view('admin.applicant-accounts.show', [
            'profile' => $profile,
            'matchingApplications' => $matches,
            'verifiedIdentity' => $verifiedIdentity,
            'impersonationEnabled' => $this->impersonationEnabled(),
        ]);
    }

    public function reconcile(Request $request, ApplicantProfile $profile, HistoricalApplicationLinkingService $linker)
    {
        $this->authorizeHr();
        $data = $request->validate(['reason' => 'required|string|min:20|max:1000']);
        $email = mb_strtolower(trim((string) $profile->user?->email));
        $verifiedIdentity = $email && OAuthIdentity::where('user_id', $profile->user_id)
            ->where('provider','google')->where('email_verified',true)
            ->whereRaw('LOWER(TRIM(provider_email)) = ?', [$email])->exists();
        if (!$verifiedIdentity) {
            throw ValidationException::withMessages([
                'reason' => 'A verified Google identity matching the registered account email is required. No ownership changes were made.',
            ]);
        }
        $new = $linker->linkMatchingEmail($profile, $email);
        ApplicantAuditLog::create([
            'actor_user_id' => (int) $request->user()->id,
            'applicant_profile_id' => $profile->id,
            'event_type' => 'admin_google_history_reconciliation',
            'metadata' => ['new_links' => $new, 'reason' => $data['reason']],
            'created_at' => now(),
        ]);
        return redirect()->route('admin.applicant_accounts.show', $profile)
            ->with('status', "{$new} unowned matching historical application(s) linked. Existing owners were not changed.");
    }

    public function impersonate(Request $request, ApplicantProfile $profile)
    {
        $this->authorizeHr();
        abort_unless($this->impersonationEnabled(), 403, 'Impersonation is disabled outside explicitly configured non-production environments.');
        abort_if($request->session()->has('rms_impersonation_admin_id'), 403, 'Nested impersonation is not allowed.');
        $data = $request->validate(['reason' => 'required|string|min:20|max:1000']);
        $target = User::with('role')->findOrFail($profile->user_id);
        // A staff account must never be impersonated through this feature,
        // even if the staff member also has an applicant profile.
        abort_if($target->role !== null, 403, 'Staff account impersonation is prohibited.');
        abort_if((int) $target->id === (int) $request->user()->id, 403);
        $adminId = (int) $request->user()->id;

        ApplicantAuditLog::create([
            'actor_user_id' => $adminId,
            'applicant_profile_id' => $profile->id,
            'event_type' => 'test_impersonation_started',
            'metadata' => [
                'target_user_id' => $target->id,
                'reason' => $data['reason'],
                'read_only' => true,
            ], 'created_at' => now(),
        ]);
        $request->session()->put('rms_impersonation_admin_id', $adminId);
        $request->session()->put('rms_impersonation_target_id', $target->id);
        $request->session()->put('rms_impersonation_profile_id', $profile->id);
        Auth::login($target);
        $request->session()->regenerate();
        return redirect()->route('applicant.dashboard');
    }

    public function stopImpersonation(Request $request)
    {
        $adminId = (int) $request->session()->get('rms_impersonation_admin_id');
        $targetId = (int) $request->session()->get('rms_impersonation_target_id');
        $profileId = (int) $request->session()->get('rms_impersonation_profile_id');
        abort_unless($adminId > 0 && $targetId > 0
            && (int) $request->user()?->id === $targetId, 403);

        $admin = User::with('role')->findOrFail($adminId);
        if (!$admin->role || (int) $admin->role->level !== 1 || (int) $admin->role->status !== 1) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('login')->withErrors([
                'access' => 'The administrator account is no longer authorized. Please sign in again.',
            ]);
        }

        ApplicantAuditLog::create([
            'actor_user_id' => $adminId,
            'applicant_profile_id' => $profileId,
            'event_type' => 'test_impersonation_ended',
            'metadata' => ['target_user_id' => $targetId],
            'created_at' => now(),
        ]);
        Auth::login($admin);
        $request->session()->forget([
            'rms_impersonation_admin_id','rms_impersonation_target_id','rms_impersonation_profile_id',
        ]);
        $request->session()->regenerate();
        return redirect()->route('admin.applicant_accounts.index')
            ->with('status','Returned to your administrator account. Test impersonation ended.');
    }
}
