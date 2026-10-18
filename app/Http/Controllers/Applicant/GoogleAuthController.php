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
        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request, HistoricalApplicationLinkingService $linker)
    {
        $google = Socialite::driver('google')->user();
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
                $user = User::where('email', $email)->first();
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
        $linker->linkMatchingEmail($profile, $email);
        return redirect()->route('applicant.dashboard');
    }
}
