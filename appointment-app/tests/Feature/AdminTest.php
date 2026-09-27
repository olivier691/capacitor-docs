<?php

namespace Tests\Feature;

use App\Livewire\Admin\Roles;
use App\Livewire\Admin\Users;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create(['name' => 'Admin']);
        $this->actingAs($this->admin);
    }

    public function test_admin_creates_an_account_with_roles(): void
    {
        Livewire::test(Users::class)
            ->call('create')
            ->set('name', 'Assistante du PDG')
            ->set('email', 'Assistante@Corp.test')
            ->set('password', 'un-mot-de-passe-solide')
            ->set('roles', ['Direction', 'Sécurité'])
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Compte créé.');

        $user = User::firstWhere('email', 'assistante@corp.test');
        $this->assertTrue(Hash::check('un-mot-de-passe-solide', $user->password));
        $this->assertEqualsCanonicalizing(['Direction', 'Sécurité'], $user->getRoleNames()->all());
        $this->assertTrue($user->can('appointments.decide'));
        $this->assertTrue($user->can('visits.check-in'));
    }

    public function test_account_validation(): void
    {
        User::factory()->create(['email' => 'pris@corp.test']);

        Livewire::test(Users::class)
            ->call('create')
            ->set('email', 'pris@corp.test')
            ->set('password', 'court')
            ->set('roles', ['Inconnu'])
            ->call('save')
            ->assertHasErrors(['name', 'email', 'password', 'roles.0']);
    }

    public function test_editing_keeps_the_password_when_left_blank(): void
    {
        $user = User::factory()->security()->create(['password' => 'ancien-mot-de-passe']);

        Livewire::test(Users::class)
            ->call('edit', $user->id)
            ->assertSet('roles', ['Sécurité'])
            ->set('roles', ['Direction'])
            ->call('save')
            ->assertHasNoErrors();

        $user->refresh();
        $this->assertTrue(Hash::check('ancien-mot-de-passe', $user->password));
        $this->assertSame(['Direction'], $user->getRoleNames()->all());
    }

    public function test_admin_can_deactivate_and_reactivate_an_account(): void
    {
        $user = User::factory()->security()->create();

        Livewire::test(Users::class)->call('toggleActive', $user->id);
        $this->assertFalse($user->fresh()->is_active);

        Livewire::test(Users::class)->call('toggleActive', $user->id);
        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_the_last_administrator_cannot_lock_everyone_out(): void
    {
        Livewire::test(Users::class)->call('toggleActive', $this->admin->id)
            ->assertSee('Vous ne pouvez pas désactiver votre propre compte.');

        Livewire::test(Users::class)
            ->call('edit', $this->admin->id)
            ->set('roles', ['Direction'])
            ->call('save')
            ->assertHasErrors('roles');
        $this->assertTrue($this->admin->fresh()->hasRole('Administrateur'));

        // Avec un second administrateur, le retrait devient possible.
        User::factory()->admin()->create();
        Livewire::test(Users::class)
            ->call('edit', $this->admin->id)
            ->set('roles', ['Direction'])
            ->call('save')
            ->assertHasNoErrors();
        $this->assertFalse($this->admin->fresh()->hasRole('Administrateur'));
    }

    public function test_admin_creates_a_custom_role(): void
    {
        Livewire::test(Roles::class)
            ->call('create')
            ->set('name', 'Chef de cabinet')
            ->set('permissions', ['appointments.view', 'visits.view'])
            ->call('save')
            ->assertHasNoErrors();

        $role = Role::findByName('Chef de cabinet');
        $this->assertEqualsCanonicalizing(['appointments.view', 'visits.view'], $role->permissions->pluck('name')->all());

        Livewire::test(Roles::class)
            ->call('create')->set('name', 'Pirate')->set('permissions', ['tout.faire'])
            ->call('save')->assertHasErrors('permissions.0');
    }

    public function test_changing_a_role_changes_access_for_its_users(): void
    {
        $guard = User::factory()->security()->create();
        $this->assertTrue($guard->can('visits.check-in'));

        Livewire::test(Roles::class)
            ->call('edit', Role::findByName('Sécurité')->id)
            ->set('permissions', ['visits.view'])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertFalse($guard->fresh()->can('visits.check-in'));
        $this->assertTrue($guard->fresh()->can('visits.view'));
    }

    public function test_administrator_role_is_protected(): void
    {
        $adminRole = Role::findByName('Administrateur');

        Livewire::test(Roles::class)
            ->call('edit', $adminRole->id)
            ->set('permissions', [])
            ->call('save')
            ->assertHasNoErrors();
        $this->assertEqualsCanonicalizing(['users.manage', 'roles.manage'], $adminRole->fresh()->permissions->pluck('name')->all());

        Livewire::test(Roles::class)->call('edit', $adminRole->id)->set('name', 'Chef')->call('save')->assertHasErrors('name');

        Livewire::test(Roles::class)->call('delete', $adminRole->id)->assertSee('ne peut pas être supprimé');
        $this->assertNotNull(Role::findByName('Administrateur'));
    }

    public function test_a_role_can_be_deleted(): void
    {
        $role = Role::create(['name' => 'Temporaire']);

        Livewire::test(Roles::class)->call('delete', $role->id);
        $this->assertNull(Role::firstWhere('name', 'Temporaire'));
    }

    public function test_non_admins_cannot_manage_accounts(): void
    {
        $this->actingAs(User::factory()->direction()->create());

        Livewire::test(Users::class)->assertForbidden();
        Livewire::test(Roles::class)->assertForbidden();
    }

    public function test_user_command_assigns_roles(): void
    {
        $this->artisan('app:user', ['email' => 'pdg@corp.test', '--name' => 'PDG', '--role' => ['Direction', 'Administrateur']])
            ->expectsQuestion('Mot de passe (12 caractères minimum)', 'mot-de-passe-du-pdg')
            ->assertSuccessful();

        $this->assertEqualsCanonicalizing(['Direction', 'Administrateur'], User::firstWhere('email', 'pdg@corp.test')->getRoleNames()->all());

        $this->artisan('app:user', ['email' => 'x@corp.test', '--name' => 'X', '--role' => ['Inconnu']])
            ->expectsQuestion('Mot de passe (12 caractères minimum)', 'mot-de-passe-long')
            ->assertFailed();
    }
}
