<?php

namespace Tests\Feature;

use App\Livewire\Auth\Login;
use App\Livewire\Direction\Appointments;
use App\Livewire\Security\Visits;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_roles_and_permissions_are_installed(): void
    {
        $this->assertEqualsCanonicalizing(
            ['users.manage', 'roles.manage'],
            Role::findByName('Administrateur')->permissions->pluck('name')->all(),
        );
        $this->assertEqualsCanonicalizing(
            ['appointments.view', 'appointments.decide', 'appointments.cancel'],
            Role::findByName('Direction')->permissions->pluck('name')->all(),
        );
        $this->assertEqualsCanonicalizing(
            ['visits.view', 'visits.check-in'],
            Role::findByName('Sécurité')->permissions->pluck('name')->all(),
        );
    }

    public function test_guests_are_sent_to_login(): void
    {
        foreach (['/direction', '/securite', '/admin/utilisateurs', '/admin/roles'] as $url) {
            $this->get($url)->assertRedirect('/connexion');
        }
    }

    public function test_pages_require_the_matching_permission(): void
    {
        $this->withoutVite();

        $this->actingAs(User::factory()->security()->create());
        $this->get('/securite')->assertOk();
        $this->get('/direction')->assertForbidden();
        $this->get('/admin/utilisateurs')->assertForbidden();

        $this->actingAs(User::factory()->direction()->create());
        $this->get('/direction')->assertOk();
        $this->get('/securite')->assertForbidden();

        $this->actingAs(User::factory()->admin()->create());
        $this->get('/admin/utilisateurs')->assertOk();
        $this->get('/admin/roles')->assertOk();
        $this->get('/direction')->assertForbidden();
    }

    public function test_a_user_with_several_roles_gets_every_space(): void
    {
        $this->withoutVite();
        $this->actingAs(User::factory()->withRole('Direction', 'Administrateur')->create());

        $this->get('/accueil')->assertRedirect('/direction');
        $this->get('/direction')->assertOk()->assertSee('Utilisateurs')->assertSee('Rôles et permissions');
        $this->get('/admin/utilisateurs')->assertOk();
    }

    public function test_login_redirects_to_the_user_space_and_rejects_bad_passwords(): void
    {
        User::factory()->security()->create(['email' => 'garde@corp.test', 'password' => 'mot-de-passe-long']);

        Livewire::test(Login::class)
            ->set('email', 'garde@corp.test')->set('password', 'faux')
            ->call('login')->assertHasErrors('email');
        $this->assertGuest();

        Livewire::test(Login::class)
            ->set('email', 'GARDE@corp.test')->set('password', 'mot-de-passe-long')
            ->call('login')->assertRedirect('/accueil');
        $this->assertAuthenticated();
        $this->get('/accueil')->assertRedirect('/securite');
    }

    public function test_deactivated_accounts_cannot_log_in_and_are_logged_out(): void
    {
        $user = User::factory()->security()->inactive()->create(['email' => 'ancien@corp.test', 'password' => 'mot-de-passe-long']);

        Livewire::test(Login::class)
            ->set('email', 'ancien@corp.test')->set('password', 'mot-de-passe-long')
            ->call('login')->assertHasErrors('email');
        $this->assertGuest();

        // Compte désactivé pendant qu'il était connecté.
        $this->actingAs($user)->get('/securite')->assertRedirect('/connexion');
        $this->assertGuest();
    }

    public function test_an_account_without_any_role_has_no_access(): void
    {
        $this->actingAs(User::factory()->create())->get('/accueil')->assertRedirect('/connexion');
        $this->assertGuest();
    }

    public function test_livewire_actions_check_permissions_too(): void
    {
        $appointment = Appointment::factory()->create();

        $this->actingAs(User::factory()->security()->create());
        Livewire::test(Appointments::class)->assertForbidden();

        // Rôle personnalisé : consultation seule, sans droit de décision.
        Role::create(['name' => 'Lecture agenda'])->givePermissionTo('appointments.view');
        $this->actingAs(User::factory()->withRole('Lecture agenda')->create());
        Livewire::test(Appointments::class)
            ->assertSee($appointment->fullName())
            ->assertDontSee('Refuser')
            ->call('open', 'reject', $appointment->id)
            ->assertForbidden();
        $this->assertSame('pending', $appointment->fresh()->status->value);

        // Rôle personnalisé : consultation des visites sans pointage.
        Role::create(['name' => 'Accueil'])->givePermissionTo('visits.view');
        $this->actingAs(User::factory()->withRole('Accueil')->create());
        Livewire::test(Visits::class)->call('toggleArrival', $appointment->id)->assertForbidden();
    }
}
