<?php

namespace App\Services;

use App\Models\ExamAttempt;
use App\Models\SkillTestAttempt;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * One browser session per applicant attempt, persisted across PHP workers
 * and devices. The row lock on the attempt serializes concurrent claims.
 */
class AssessmentDeviceSessionService
{
    private function hash(Request $request, bool $create = true): ?string
    {
        // Laravel legitimately regenerates session IDs on authentication and
        // logout. Use a server-held per-session secret which survives those
        // rotations, rather than locking to a transient session ID.
        $secret = $request->session()->get('rms_assessment_device_secret');
        if ((!is_string($secret) || strlen($secret) < 32) && $create) {
            $secret = bin2hex(random_bytes(32));
            $request->session()->put('rms_assessment_device_secret', $secret);
        }
        return is_string($secret) && strlen($secret) >= 32
            ? hash_hmac('sha256', $secret, (string) config('app.key'))
            : null;
    }

    public function claim(Request $request, string $kind, int $attemptId): void
    {
        abort_unless(in_array($kind, ['written', 'skills'], true), 422);
        $user = $request->user();
        abort_unless($user, 401);
        $hash = $this->hash($request);

        DB::transaction(function () use ($kind, $attemptId, $user, $hash) {
            // Serialize claims across BOTH assessment kinds for the same
            // applicant, not just within one attempt. Different users do not
            // block each other.
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $model = $kind === 'written' ? ExamAttempt::class : SkillTestAttempt::class;
            $attempt = $model::query()->whereKey($attemptId)->lockForUpdate()->firstOrFail();

            // Completed/voided attempts no longer need a live device lock.
            if ((int) $attempt->status !== 1 && (int) $attempt->status !== 0) return;

            $otherDevice = DB::table('assessment_device_sessions')
                ->where('user_id', $user->id)
                ->whereNull('released_at')
                ->where('session_hash', '!=', $hash)->exists();
            if ($otherDevice) {
                throw new ConflictHttpException(
                    'A Written or Skills assessment is active on another device. Sign out on the first device before using a different browser.'
                );
            }

            $lock = DB::table('assessment_device_sessions')
                ->where('kind', $kind)->where('attempt_id', $attemptId)
                ->first();

            if ($lock && $lock->released_at === null
                && !hash_equals((string) $lock->session_hash, $hash)) {
                throw new ConflictHttpException(
                    'This assessment is already open on another device or browser. Sign out on the first device before continuing here. If that device is unavailable, contact the assessment administrator.'
                );
            }

            $values = [
                'user_id' => $user->id,
                'session_hash' => $hash,
                'released_at' => null,
                'updated_at' => now(),
            ];
            if ($lock) {
                DB::table('assessment_device_sessions')->where('id', $lock->id)->update($values);
            } else {
                DB::table('assessment_device_sessions')->insert($values + [
                    'kind' => $kind, 'attempt_id' => $attemptId, 'created_at' => now(),
                ]);
            }
        }, 3);
    }

    /** Called before logout invalidates the original browser session. */
    public function releaseForSession(Request $request): int
    {
        if (!$request->hasSession() || !$request->user()) return 0;
        if (!Schema::hasTable('assessment_device_sessions')) return 0;

        $hash = $this->hash($request, false);
        if ($hash === null) return 0;
        $count = DB::table('assessment_device_sessions')
            ->where('user_id', $request->user()->id)
            ->where('session_hash', $hash)
            ->whereNull('released_at')
            ->update(['released_at' => now(), 'updated_at' => now()]);
        $request->session()->forget('rms_assessment_device_secret');
        return $count;
    }

    /** Admin recovery only; does not modify attempt answers, scores or expiry. */
    public function releaseByAdmin(string $kind, int $attemptId): void
    {
        DB::table('assessment_device_sessions')
            ->where('kind', $kind)->where('attempt_id', $attemptId)
            ->whereNull('released_at')
            ->update(['released_at' => now(), 'updated_at' => now()]);
    }
}
