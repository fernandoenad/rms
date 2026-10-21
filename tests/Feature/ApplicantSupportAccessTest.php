<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Support\ApplicantImpersonationPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicantSupportAccessTest extends TestCase
{
    use RefreshDatabase;

    private function staff(int $level): User
    {
        $user = User::factory()->create();
        Role::create(['user_id' => $user->id, 'level' => $level, 'status' => 1]);
        return $user;
    }

    public function test_bootstrap_owner_can_grant_and_revoke_support_rights_with_audit(): void
    {
        $owner = $this->staff(1);
        $delegate = $this->staff(1);
        config()->set('app.support_impersonation_enabled', true);
        config()->set('app.support_impersonation_admin_ids', (string) $owner->id);
        $policy = app(ApplicantImpersonationPolicy::class);

        $this->actingAs($owner)->get(route('admin.support_access.index'))
            ->assertOk()->assertSee('Production Applicant Support Rights');
        $this->assertFalse($policy->productionSupportEnabled($delegate));
        $this->post(route('admin.support_access.grant', $delegate), [
            'reason' => 'HR board-approved support viewing for recruitment troubleshooting.',
        ])->assertRedirect();
        $this->assertTrue($policy->productionSupportEnabled($delegate));
        $this->assertDatabaseHas('applicant_audit_logs', [
            'event_type' => 'support_access_granted','actor_user_id' => $owner->id,
        ]);

        $this->post(route('admin.support_access.revoke', $delegate), [
            'reason' => 'Support assignment concluded after the approved test window.',
        ])->assertRedirect();
        $this->assertFalse($policy->productionSupportEnabled($delegate));
        $this->assertDatabaseHas('applicant_audit_logs', [
            'event_type' => 'support_access_revoked', 'actor_user_id' => $owner->id,
        ]);
    }

    public function test_delegate_cannot_grant_more_permissions_and_non_hr_cannot_receive_them(): void
    {
        $owner = $this->staff(1);
        $delegate = $this->staff(1);
        $ordinary = $this->staff(2);
        config()->set('app.support_impersonation_admin_ids', (string) $owner->id);
        $this->actingAs($owner)->post(route('admin.support_access.grant', $delegate), [
            'reason' => 'Support viewing required for the approved HR case review.',
        ])->assertRedirect();

        $this->actingAs($delegate)->get(route('admin.support_access.index'))->assertForbidden();
        $this->post(route('admin.support_access.grant', $ordinary), [
            'reason' => 'Delegation chain must not be allowed to expand itself.',
        ])->assertForbidden();

        $this->actingAs($owner)->post(route('admin.support_access.grant', $ordinary), [
            'reason' => 'Staff outside HR must not gain support viewing rights.',
        ])->assertStatus(422);

        $this->post(route('admin.support_access.revoke', $owner), [
            'reason' => 'Bootstrap owner must remain server configuration only.',
        ])->assertForbidden();
    }

    public function test_support_global_kill_switch_still_prevents_delegated_access(): void
    {
        $owner = $this->staff(1);
        $delegate = $this->staff(1);
        config()->set('app.support_impersonation_admin_ids', (string) $owner->id);
        $this->actingAs($owner)->post(route('admin.support_access.grant', $delegate), [
            'reason' => 'Grant short-lived authorized support access for testing.',
        ])->assertRedirect();

        config()->set('app.support_impersonation_enabled', false);
        $this->assertFalse(app(ApplicantImpersonationPolicy::class)->productionSupportEnabled($delegate));
    }
}
