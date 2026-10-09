<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\BankUnit;
use App\Models\Role;
use App\Models\User;
use App\Support\Rbac;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BankManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_bank_and_unit(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.banks.store'), ['name' => 'BTN'])
            ->assertRedirect();

        $bank = Bank::query()->where('name', 'BTN')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.banks.units.store', $bank), ['name' => 'Magelang'])
            ->assertRedirect();

        $this->assertDatabaseHas('bank_units', ['bank_id' => $bank->id, 'name' => 'Magelang', 'is_active' => true]);
    }

    public function test_duplicate_bank_and_unit_are_rejected(): void
    {
        $admin = $this->admin();
        $bank = Bank::factory()->create(['name' => 'BTN']);
        BankUnit::factory()->for($bank)->create(['name' => 'Magelang']);

        $this->actingAs($admin)
            ->post(route('admin.banks.store'), ['name' => 'BTN'])
            ->assertSessionHasErrors('name');

        $this->actingAs($admin)
            ->post(route('admin.banks.units.store', $bank), ['name' => 'Magelang'])
            ->assertSessionHasErrors('name');
    }

    public function test_inactive_bank_cannot_receive_new_unit(): void
    {
        $admin = $this->admin();
        $bank = Bank::factory()->create(['is_active' => false]);

        $this->actingAs($admin)
            ->post(route('admin.banks.units.store', $bank), ['name' => 'Magelang'])
            ->assertSessionHasErrors('bank');
    }

    public function test_bank_with_units_cannot_be_deleted(): void
    {
        $admin = $this->admin();
        $bank = Bank::factory()->create();
        BankUnit::factory()->for($bank)->create();

        $this->actingAs($admin)
            ->delete(route('admin.banks.destroy', $bank))
            ->assertSessionHasErrors('bank');

        $this->assertDatabaseHas('banks', ['id' => $bank->id]);
    }

    public function test_user_without_bank_management_permission_is_forbidden(): void
    {
        Rbac::provision();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('admin.banks.store'), ['name' => 'BTN'])
            ->assertForbidden();
    }

    private function admin(): User
    {
        Rbac::provision();
        $admin = User::factory()->create();
        $role = Role::query()->whereNull('team_id')->where('name', 'super_admin')->firstOrFail();
        $admin->globalRoles()->attach($role);

        return $admin;
    }
}
