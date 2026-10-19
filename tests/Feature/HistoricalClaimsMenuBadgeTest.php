<?php

namespace Tests\Feature;

use App\Menu\Filters\HistoricalClaimsCountFilter;
use App\Models\ApplicationClaimRequest;
use App\Models\ApplicantProfile;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HistoricalClaimsMenuBadgeTest extends TestCase
{
    use RefreshDatabase;

    private function staff(int $level = 1): User
    {
        $user = User::factory()->create(['email' => 'claims-level-'.$level.'@deped.gov.ph']);
        Role::create(['user_id' => $user->id, 'level' => $level, 'status' => 1]);
        return $user;
    }

    private function claim(string $status, int $number): ApplicationClaimRequest
    {
        $profile = ApplicantProfile::create([
            'user_id' => User::factory()->create()->id,
            'first_name' => 'Synthetic',
            'last_name' => 'Claimant',
        ]);

        return ApplicationClaimRequest::create([
            'applicant_profile_id' => $profile->id,
            'claimed_application_code' => 'SYNTH-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT),
            'claimed_email' => 'old-application@example.test',
            'status' => $status,
        ]);
    }

    private function item(): array
    {
        return collect(config('adminlte.menu'))->first(fn ($item) =>
            is_array($item) && ($item['key'] ?? null) === 'historical-claims'
        );
    }

    public function test_badge_counts_only_open_claims_and_updates_after_review(): void
    {
        $admin = $this->staff();
        $this->actingAs($admin);
        $pending = $this->claim('pending', 1);
        $clarification = $this->claim('clarification', 2);
        $this->claim('approved', 3);
        $this->claim('rejected', 4);
        $this->claim('cancelled', 5);

        $filter = new HistoricalClaimsCountFilter();
        $menu = $filter->transform($this->item());
        $this->assertSame(2, $menu['label']);
        $this->assertSame('warning', $menu['label_color']);

        $pending->update(['status' => 'approved']);
        $this->assertSame(1, $filter->transform($this->item())['label']);

        $clarification->update(['status' => 'rejected']);
        $menu = $filter->transform($this->item());
        $this->assertArrayNotHasKey('label', $menu);
        $this->assertArrayNotHasKey('label_color', $menu);
    }

    public function test_only_authorized_hr_can_retrieve_claim_count(): void
    {
        $this->claim('pending', 1);
        $staff = $this->staff(2);
        $this->actingAs($staff);
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            if (str_contains(strtolower($query->sql), 'application_claim_requests')) {
                $queries[] = $query->sql;
            }
        });

        $filter = new HistoricalClaimsCountFilter();
        $menu = $filter->transform($this->item());
        $this->assertArrayNotHasKey('label', $menu);
        $this->assertSame([], $queries);

        $admin = $this->staff();
        $this->actingAs($admin);
        $restricted = $this->item();
        $restricted['restricted'] = true;
        $this->assertSame($restricted, $filter->transform($restricted));
        $this->assertSame([], $queries);
    }

    public function test_admin_sidebar_renders_actionable_claims_badge(): void
    {
        $admin = $this->staff();
        $this->claim('pending', 1);
        $this->claim('clarification', 2);
        $this->claim('approved', 3);

        $this->actingAs($admin)->get(route('admin.applicant_claims.index'))
            ->assertOk()
            ->assertSee('Historical Claims')
            ->assertSee('badge-warning')
            ->assertSee('>2</span>', false);
    }

    public function test_other_menu_items_are_not_modified(): void
    {
        $this->actingAs($this->staff());
        $filter = new HistoricalClaimsCountFilter();
        $other = ['text' => 'Applications', 'url' => 'admin/applications'];
        $this->assertSame($other, $filter->transform($other));
    }
}
