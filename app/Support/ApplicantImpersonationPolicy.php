<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ApplicantImpersonationPolicy
{
    public function isHr(User $user): bool
    {
        return (int) ($user->role?->level ?? 0) === 1
            && (int) ($user->role?->status ?? 0) === 1;
    }

    public function localTestingEnabled(): bool
    {
        return app()->environment(['local', 'testing', 'staging'])
            && (bool) config('app.applicant_impersonation_enabled', false);
    }

    public function isProvisioningAdmin(User $admin): bool
    {
        if (!$this->isHr($admin)) return false;

        $ids = array_filter(array_map('trim', explode(',',
            (string) config('app.support_impersonation_admin_ids', '')
        )), fn ($id) => ctype_digit($id) && (int) $id > 0);

        return in_array((string) $admin->id, $ids, true);
    }

    public function productionSupportEnabled(User $admin): bool
    {
        // Production is always opt-in, limited to specific HR user IDs. Do not
        // allow APP_DEBUG=true production sessions to access real applicant PII.
        if (!app()->environment(['production', 'staging', 'testing'])
            || !(bool) config('app.support_impersonation_enabled', false)
            || (app()->environment('production') && (bool) config('app.debug', false))
            || !$this->isHr($admin)) {
            return false;
        }

        if ($this->isProvisioningAdmin($admin)) return true;

        // Grants may be delegated only by a bootstrap owner using the audited
        // management page. Deployed code remains safe before migration runs.
        return Schema::hasTable('applicant_support_access_grants')
            && DB::table('applicant_support_access_grants')
                ->where('user_id', $admin->id)->whereNull('revoked_at')->exists();
    }

    public function mayStart(User $admin): bool
    {
        return $this->isHr($admin) && (
            $this->localTestingEnabled() || $this->productionSupportEnabled($admin)
        );
    }

    public function isActiveModeAllowed(string $mode, User $admin): bool
    {
        return $mode === 'support'
            ? $this->productionSupportEnabled($admin)
            : ($mode === 'test' && $this->localTestingEnabled() && $this->isHr($admin));
    }

    public function durationSeconds(): int
    {
        // Fixed short lifetime, independent of mutable client-side timers.
        return 10 * 60;
    }
}
