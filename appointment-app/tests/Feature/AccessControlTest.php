<?php

namespace Tests\Feature;

use App\Livewire\Auth\Login;
use App\Livewire\Direction\Appointments;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/direction')->assertRedirect('/connexion');
        $this->get('/securite')->assertRedirect('/connexion');
    }

    public function test_each_role_is_redirected_to_its_own_space(): void
    {
        $this->actingAs(User::factory()->security()->create())->get('/direction')->assertRedirect('/securite');
        $this->actingAs(User::factory()->direction()->create())->get('/securite')->assertRedirect('/direction');
    }

    public function test_login_redirects_by_role_and_rejects_bad_passwords(): void
    {
        User::factory()->security()->create(['email' => 'garde@corp.test', 'password' => 'mot-de-passe-long']);

        Livewire::test(Login::class)
            ->set('email', 'garde@corp.test')->set('password', 'faux')
            ->call('login')->assertHasErrors('email');
        $this->assertGuest();

        Livewire::test(Login::class)
            ->set('email', 'GARDE@corp.test')->set('password', 'mot-de-passe-long')
            ->call('login')->assertRedirect('/securite');
        $this->assertAuthenticated();
    }

    public function test_security_cannot_call_direction_actions(): void
    {
        $appointment = Appointment::factory()->create();

        $this->actingAs(User::factory()->security()->create());
        Livewire::test(Appointments::class)->assertForbidden();
        $this->assertSame('pending', $appointment->fresh()->status->value);
    }
}
