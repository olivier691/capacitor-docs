<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Livewire\Security\Visits;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SecurityVisitsTest extends TestCase
{
    use RefreshDatabase;

    private function approved(array $attributes = []): Appointment
    {
        $appointment = Appointment::factory()->create($attributes);
        $appointment->forceFill(['status' => AppointmentStatus::Approved])->save();

        return $appointment;
    }

    public function test_security_sees_only_approved_visits_without_confidential_details(): void
    {
        $visit = $this->approved(['date' => today(), 'subject' => 'Dossier confidentiel', 'phone' => '+225 01 02 03 04 05']);
        $pending = Appointment::factory()->create(['date' => today()]);

        $this->actingAs(User::factory()->security()->create());
        Livewire::test(Visits::class)
            ->assertSee($visit->fullName())
            ->assertDontSee($pending->fullName())
            ->assertDontSee('Dossier confidentiel')
            ->assertDontSee($visit->email)
            ->assertDontSee('+225 01 02 03 04 05');
    }

    public function test_security_checks_in_a_visitor_on_the_day(): void
    {
        $visit = $this->approved(['date' => today()]);
        $guard = User::factory()->security()->create(['name' => 'Poste de garde']);
        $this->actingAs($guard);

        Livewire::test(Visits::class)->call('toggleArrival', $visit->id)->assertSee('pointé par Poste de garde');
        $this->assertNotNull($visit->fresh()->arrived_at);
        $this->assertSame($guard->id, $visit->fresh()->arrival_recorded_by);

        Livewire::test(Visits::class)->call('toggleArrival', $visit->id);
        $this->assertNull($visit->fresh()->arrived_at);
    }

    public function test_arrival_cannot_be_recorded_on_another_day(): void
    {
        $visit = $this->approved(['date' => today()->addDay()]);
        $this->actingAs(User::factory()->security()->create());

        Livewire::test(Visits::class, ['date' => today()->addDay()->toDateString()])
            ->call('toggleArrival', $visit->id)
            ->assertSee('La présence ne peut être pointée que le jour du rendez-vous.');
        $this->assertNull($visit->fresh()->arrived_at);
    }

    public function test_direction_cannot_record_arrivals(): void
    {
        $visit = $this->approved(['date' => today()]);
        $this->actingAs(User::factory()->direction()->create());

        Livewire::test(Visits::class)->assertForbidden();
        $this->assertNull($visit->fresh()->arrived_at);
    }
}
