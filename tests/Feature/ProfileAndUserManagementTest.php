<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileAndUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_can_be_viewed_and_updated_without_changing_role(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $this->actingAs($customer)->get(route('profile.edit'))->assertOk();

        $this->put(route('profile.update'), [
            'name' => 'Updated Customer', 'email' => $customer->email,
        ])->assertRedirect(route('profile.edit'));

        $this->assertDatabaseHas('users', [
            'id' => $customer->id, 'name' => 'Updated Customer', 'role' => 'customer',
        ]);
    }

    public function test_password_change_requires_correct_current_password_and_confirmation(): void
    {
        $user = User::factory()->create(['password' => 'old-password']);
        $this->actingAs($user);

        $this->from(route('profile.edit'))->put(route('profile.update'), [
            'name' => $user->name, 'email' => $user->email,
            'current_password' => 'wrong-password', 'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertRedirect(route('profile.edit'))->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));

        $this->from(route('profile.edit'))->put(route('profile.update'), [
            'name' => $user->name, 'email' => $user->email,
            'current_password' => 'old-password', 'password' => 'new-password',
            'password_confirmation' => 'mismatch',
        ])->assertSessionHasErrors('password');
        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));

        $this->put(route('profile.update'), [
            'name' => $user->name, 'email' => $user->email,
            'current_password' => 'old-password', 'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertRedirect(route('profile.edit'));
        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }

    public function test_customer_cannot_access_user_management_and_admin_can_manage_roles(): void
    {
        $target = User::factory()->create(['role' => 'customer']);
        $this->actingAs(User::factory()->create(['role' => 'customer']))
            ->get(route('admin.users.index'))->assertRedirect('/');

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk()
            ->assertSee('Khu vực quản trị');
        $this->get(route('admin.users.edit', $target))->assertOk();

        $this->put(route('admin.users.update', $target), [
            'name' => $target->name, 'email' => $target->email, 'role' => 'admin',
        ])->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', ['id' => $target->id, 'role' => 'admin']);

        $this->put(route('admin.users.update', $target), [
            'name' => $target->name, 'email' => $target->email, 'role' => 'customer',
        ])->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', ['id' => $target->id, 'role' => 'customer']);
    }

    public function test_last_admin_cannot_be_demoted_or_deleted(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $this->from(route('admin.users.edit', $admin))->put(route('admin.users.update', $admin), [
            'name' => $admin->name, 'email' => $admin->email, 'role' => 'customer',
        ])->assertRedirect(route('admin.users.edit', $admin))->assertSessionHasErrors('role');
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'role' => 'admin']);

        $this->delete(route('admin.users.destroy', $admin))->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'role' => 'admin']);
    }

    public function test_database_seeder_does_not_create_or_promote_a_default_admin(): void
    {
        $existing = User::factory()->create([
            'name' => 'Existing account', 'email' => 'admin@gmail.com', 'role' => 'customer',
        ]);

        $this->seed();

        $this->assertDatabaseHas('users', [
            'id' => $existing->id, 'email' => 'admin@gmail.com', 'role' => 'customer',
        ]);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_admin_can_demote_another_admin_while_one_admin_remains(): void
    {
        $actingAdmin = User::factory()->create(['role' => 'admin']);
        $targetAdmin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($actingAdmin);

        $this->put(route('admin.users.update', $targetAdmin), [
            'name' => $targetAdmin->name, 'email' => $targetAdmin->email, 'role' => 'customer',
        ])->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', ['id' => $actingAdmin->id, 'role' => 'admin']);
        $this->assertDatabaseHas('users', ['id' => $targetAdmin->id, 'role' => 'customer']);
    }
}
