<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApplicantAuditLog;
use App\Models\ApplicantProfile;
use App\Models\Application;
use App\Models\OAuthIdentity;
use App\Models\User;
use App\Services\Applicant\HistoricalApplicationLinkingService;
use App\Services\Applicant\ApplicantImpersonationSession;
use App\Support\ApplicantImpersonationPolicy;
use Laravel\Socialite\Facades\Socialite;
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
        return auth()->user() && app(ApplicantImpersonationPolicy::class)
            ->mayStart(auth()->user());
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

    public function impersonate(
        Request $request,
        ApplicantProfile $profile,
        ApplicantImpersonationPolicy $policy,
        ApplicantImpersonationSession $session
    ) {
        $this->authorizeHr();
        abort_unless($policy->mayStart($request->user()), 403,
            'Only explicitly authorized support administrators may access read-only applicant accounts.');
        abort_if($request->session()->has('rms_impersonation_admin_id'), 403,
            'Nested support sessions are not available.');
        $data = $request->validate(['reason' => 'required|string|min:20|max:1000']);
        $target = User::with('role')->findOrFail($profile->user_id);
        abort_if($target->role !== null || $target->id === $request->user()->id, 403,
            'Staff accounts cannot be impersonated.');

        if ($policy->productionSupportEnabled($request->user())) {
            // A new Google challenge is mandatory; email alone is insufficient.
            // The existing pre-linked subject must be independently verified.
            $admin = $request->user();
            abort_unless(OAuthIdentity::where('user_id', $admin->id)
                ->where('provider', 'google')->where('email_verified', true)
                ->whereRaw('LOWER(TRIM(provider_email)) = ?', [
                    mb_strtolower(trim((string) $admin->email)),
                ])->exists(), 403,
                'Link your own verified staff Google account before beginning a support session.');

            $request->session()->put([
                'rms_support_pending_admin_id' => $admin->id,
                'rms_support_pending_profile_id' => $profile->id,
                'rms_support_pending_reason' => $data['reason'],
                'rms_support_pending_started_at' => now()->timestamp,
            ]);

            return Socialite::driver('google')
                ->redirectUrl(route('admin.applicant_accounts.impersonation.stepup'))
                ->with(['prompt' => 'login', 'max_age' => 0])
                ->redirect();
        }

        return $session->start($request, $request->user(), $profile, 'test', $data['reason']);
    }

    /**
     * Fresh Google authentication, matched against the already-bound staff
     * OAuth subject. This never creates or rebinds an OAuth identity.
     */
    public function completeSupportStepUp(
        Request $request,
        ApplicantImpersonationPolicy $policy,
        ApplicantImpersonationSession $session
    ) {
        $adminId = (int) $request->session()->pull('rms_support_pending_admin_id');
        $profileId = (int) $request->session()->pull('rms_support_pending_profile_id');
        $reason = (string) $request->session()->pull('rms_support_pending_reason');
        $startedAt = (int) $request->session()->pull('rms_support_pending_started_at');
        $admin = $request->user();

        abort_unless($admin && $adminId > 0 && (int) $admin->id === $adminId
            && $profileId > 0 && mb_strlen($reason) >= 20
            && $startedAt > now()->subMinutes(5)->timestamp
            && $startedAt <= now()->timestamp
            && !$request->session()->has('rms_impersonation_admin_id')
            && $policy->productionSupportEnabled($admin), 403,
            'The support identity challenge expired, was already used, or is no longer authorized.');

        $google = Socialite::driver('google')
            ->redirectUrl(route('admin.applicant_accounts.impersonation.stepup'))->user();
        $subject = (string) $google->getId();
        $email = mb_strtolower(trim((string) $google->getEmail()));
        $verified = filter_var(
            data_get($google->user, 'email_verified', data_get($google->user, 'verified_email', false)),
            FILTER_VALIDATE_BOOLEAN
        );
        abort_unless($subject !== '' && $verified && filter_var($email, FILTER_VALIDATE_EMAIL)
            && hash_equals(mb_strtolower(trim((string) $admin->email)), $email)
            && OAuthIdentity::where('user_id', $admin->id)
                ->where('provider', 'google')->where('provider_subject', $subject)
                ->where('email_verified', true)
                ->whereRaw('LOWER(TRIM(provider_email)) = ?', [$email])->exists(),
            403, 'Google reauthentication did not match your registered staff identity.');

        return $session->start(
            $request, $admin, ApplicantProfile::findOrFail($profileId), 'support', $reason
        );
    }

    public function stopImpersonation(Request $request, ApplicantImpersonationSession $session)
    {
        return $session->stop($request);
    }
}
