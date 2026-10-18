<?php
namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Models\ApplicantProfile;
use App\Models\OAuthIdentity;
use App\Models\User;
use App\Services\Applicant\HistoricalApplicationLinkingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('google')->redirectUrl(route('applicant.google.callback'))->redirect();
    }


    /**
     * A staff member proves possession of the existing authenticated staff session,
     * then reauthenticates with Google before binding an OAuth subject.
     * No staff identity is ever adopted based on a matching email alone.
     */
    public function linkStaff(Request $request)
    {
        abort_unless($request->user()?->role?->status == 1, 403);
        $request->session()->put('staff_google_link_user_id', $request->user()->id);
        return Socialite::driver('google')->redirectUrl(route('applicant.staff.google.callback'))->redirect();
    }

    public function completeStaffLink(Request $request, HistoricalApplicationLinkingService $linker)
    {
        $expectedId = (int) $request->session()->pull('staff_google_link_user_id');
        abort_unless($expectedId && $request->user()?->id === $expectedId
            && $request->user()?->role?->status == 1, 403);

        $google = Socialite::driver('google')->redirectUrl(route('applicant.staff.google.callback'))->user();
        $subject = (string) $google->getId();
        $email = mb_strtolower(trim((string) $google->getEmail()));
        $verified = filter_var(data_get($google->user, 'email_verified', data_get($google->user, 'verified_email', false)), FILTER_VALIDATE_BOOLEAN);
        abort_unless($subject && filter_var($email, FILTER_VALIDATE_EMAIL) && $verified, 403);

        $user = $request->user();
        // The Google identity used by staff must agree with the registered staff email.
        abort_unless(hash_equals(mb_strtolower(trim($user->email)), $email), 403,
            'Use the Google account corresponding to your registered RMS staff email.');

        DB::transaction(function () use ($user, $subject, $email) {
            $existing = OAuthIdentity::where('provider', 'google')
                ->where('provider_subject', $subject)->first();
            abort_if($existing && (int) $existing->user_id !== (int) $user->id, 403,
                'This Google account is linked to another RMS user.');

            $other = OAuthIdentity::where('provider', 'google')->where('user_id', $user->id)
                ->where('provider_subject', '!=', $subject)->exists();
            abort_if($other, 403, 'Staff account already has a different linked Google identity.');

            OAuthIdentity::firstOrCreate(
                ['provider' => 'google', 'provider_subject' => $subject],
                ['user_id' => $user->id, 'provider_email' => $email, 'email_verified' => true]
            );
            ApplicantProfile::firstOrCreate(['user_id' => $user->id]);
        });

        $request->session()->regenerate();
        $profile = ApplicantProfile::where('user_id', $user->id)->firstOrFail();
        try {
            $linker->linkMatchingEmail($profile, $email);
        } catch (\\Throwable $exception) {
            report($exception);
        }
        return redirect()->route('applicant.dashboard')
            ->with('status', 'Your staff Google account is linked. You can switch portals without signing in again.');
    }

    public function callback(Request $request, HistoricalApplicationLinkingService $linker)
    {
        $google = Socialite::driver('google')->redirectUrl(route('applicant.google.callback'))->user();
        $subject = (string) $google->getId();
        $email = mb_strtolower(trim((string) $google->getEmail()));
        $verified = filter_var(data_get($google->user, 'email_verified', data_get($google->user, 'verified_email', false)), FILTER_VALIDATE_BOOLEAN);

        abort_unless($subject !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) && $verified, 403, 'Google email verification is required.');

        $user = DB::transaction(function () use ($google, $subject, $email) {
            $identity = OAuthIdentity::where('provider', 'google')
                ->where('provider_subject', $subject)->first();
            if ($identity) {
                $user = User::findOrFail($identity->user_id);
            } else {
                $user = User::whereRaw('LOWER(email) = ?', [$email])->first();
                // Existing staff identities must be linked through a separately authorized staff flow.
                if ($user && $user->role) {
                    abort(403, 'Existing staff account requires authorized Google identity linking.');
                }
                if ($user && OAuthIdentity::where('user_id', $user->id)->where('provider', 'google')
                    ->where('provider_subject', '!=', $subject)->exists()) {
                    abort(403, 'Account identity conflict requires administrator review.');
                }
                if (!$user) {
                    $user = User::create([
                        'name' => $google->getName() ?: $email,
                        'email' => $email,
                        'password' => Hash::make(Str::random(64)),
                    ]);
                }
                OAuthIdentity::firstOrCreate(
                    ['provider' => 'google', 'provider_subject' => $subject],
                    ['user_id' => $user->id, 'provider_email' => $email, 'email_verified' => true]
                );
            }
            ApplicantProfile::firstOrCreate(['user_id' => $user->id]);
            return $user;
        });
        Auth::login($user);
        $request->session()->regenerate();
        $profile = ApplicantProfile::where('user_id', $user->id)->firstOrFail();
        // Never let a discovery failure invalidate a successful authenticated login.
        try {
            $linker->linkMatchingEmail($profile, $email);
        } catch (\Throwable $exception) {
            report($exception);
        }
        return redirect()->route('applicant.dashboard');
    }
}
