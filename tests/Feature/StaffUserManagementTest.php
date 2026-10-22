<?php

namespace Tests\Feature;

use App\Models\ApplicantProfile;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffUserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function staff(int $level = 1, string $email = 'hr@example.test'): User
    {
        $user = User::factory()->create(['email' => $email]);
        Role::create(['user_id' => $user->id, 'level' => $level, 'status' => 1]);
        return $user;
    }

    public function test_user_management_lists_staff_but_not_applicant_only_google_accounts(): void
    {
        $hr = $this->staff();
        $employee = $this->staff(2, 'employee@example.test');
        $applicant = User::factory()->create(['email' => 'applicant@example.test']);
        ApplicantProfile::create(['user_id' => $applicant->id]);

        $this->actingAs($hr)->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Staff Accounts')
            ->assertSee('employee@example.test')
            ->assertSee('hr@example.test')
            ->assertDontSee('applicant@example.test')
            ->assertSee('Applicant Management')
            ->assertSee(route('admin.applicant_accounts.index'));

        $this->assertDatabaseHas('users', ['id' => $applicant->id]);
        $this->assertDatabaseMissing('roles', ['user_id' => $applicant->id]);
        $this->assertDatabaseHas('roles', ['user_id' => $employee->id, 'level' => 2]);
    }

    public function test_staff_directory_paginates_and_searches_without_loading_every_account(): void
    {
        $admin = $this->staff();
        $admin->update(['name' => 'Admin Person']);
        for ($i = 1; $i <= 30; $i++) {
            $member = $this->staff(2, sprintf('staff%02d@example.test', $i));
            $member->update(['name' => sprintf('Staff Person %02d', $i)]);
        }
        $applicant = User::factory()->create([
            'name' => 'Staff Person 99',
            'email' => 'applicant-only@example.test',
        ]);
        ApplicantProfile::create(['user_id' => $applicant->id]);

        $this->actingAs($admin)->get(route('admin.users.index'))
            ->assertOk()
            ->assertViewHas('users', fn ($rows) =>
                $rows->count() === 25 && $rows->hasMorePages())
            ->assertSee('Staff Person 01')
            ->assertDontSee('Staff Person 30')
            ->assertDontSee('Staff Person 99')
            ->assertSee('page=2');

        $this->get(route('admin.users.index', ['page' => 2]))
            ->assertOk()
            ->assertViewHas('users', fn ($rows) =>
                $rows->count() === 6 && !$rows->hasMorePages())
            ->assertSee('Staff Person 30')
            ->assertDontSee('Staff Person 01');

        $this->get(route('admin.users.index', ['q' => 'staff30@example.test']))
            ->assertOk()
            ->assertViewHas('users', fn ($rows) => $rows->count() === 1)
            ->assertSee('staff30@example.test')
            ->assertDontSee('staff29@example.test');

        $this->get(route('admin.users.index', ['q' => 'applicant-only@example.test']))
            ->assertOk()
            ->assertViewHas('users', fn ($rows) => $rows->count() === 0)
            ->assertSee('No matching staff users found');
    }

    public function test_applicant_only_account_cannot_be_edited_reset_or_deleted_via_staff_routes(): void
    {
        $hr = $this->staff();
        $applicant = User::factory()->create(['email' => 'applicant@example.test']);
        ApplicantProfile::create(['user_id' => $applicant->id]);

        $this->actingAs($hr)
            ->get(route('admin.users.edit', $applicant))->assertNotFound();
        $this->get(route('admin.users.reset', $applicant))->assertNotFound();
        $this->get(route('admin.users.delete', $applicant))->assertNotFound();
        $this->put(route('admin.users.update', $applicant), [
            'name' => 'Changed', 'email' => 'changed@example.test', 'level' => 1, 'status' => 1,
        ])->assertNotFound();
        $this->put(route('admin.users.resetOk', $applicant), [])->assertNotFound();
        $this->delete(route('admin.users.destroy', $applicant))->assertNotFound();

        $this->assertDatabaseHas('users', [
            'id' => $applicant->id, 'email' => 'applicant@example.test',
        ]);
        $this->assertDatabaseMissing('roles', ['user_id' => $applicant->id]);
    }

    public function test_non_hr_staff_cannot_access_password_reset_or_role_change_routes(): void
    {
        $employee = $this->staff(2, 'employee@example.test');
        $other = $this->staff(2, 'other@example.test');

        $this->actingAs($employee)->get(route('admin.users.index'))->assertStatus(401);
        $this->get(route('admin.users.edit', $other))->assertStatus(401);
        $this->put(route('admin.users.update', $other), [
            'name' => 'Promoted', 'email' => $other->email, 'level' => 1, 'status' => 1,
        ])->assertStatus(401);
        $this->put(route('admin.users.resetOk', $other), [])->assertStatus(401);
        $this->delete(route('admin.users.destroy', $other))->assertStatus(401);

        $this->assertDatabaseHas('roles', ['user_id' => $other->id, 'level' => 2]);
        $this->assertDatabaseHas('users', ['id' => $other->id]);
    }

    public function test_existing_staff_user_edit_page_remains_available_for_hr(): void
    {
        $hr = $this->staff();
        $staff = $this->staff(2, 'employee@example.test');

        $this->actingAs($hr)->get(route('admin.users.edit', $staff))
            ->assertOk()->assertSee('employee@example.test');
    }
}
