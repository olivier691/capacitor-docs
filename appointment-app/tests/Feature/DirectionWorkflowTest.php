<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Livewire\Direction\Appointments;
use App\Mail\AppointmentDecision;
use App\Models\Appointment;
use App\Models\User;
use App\Services\MicrosoftGraph\GraphClient;
use App\Services\OutlookCalendar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class DirectionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        config([
            'app.timezone' => 'Africa/Abidjan',
            'services.microsoft_graph' => [
                'tenant_id' => 'tenant', 'client_id' => 'client', 'client_secret' => 'secret',
                'calendar_user' => 'pdg@corp.test',
            ],
        ]);
        $this->app->forgetInstance(GraphClient::class);
        $this->app->forgetInstance(OutlookCalendar::class);
        $this->actingAs(User::factory()->direction()->create(['name' => 'Assistante']));
    }

    private function fakeGraph(int $eventStatus = 201): void
    {
        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'token', 'expires_in' => 3600]),
            'graph.microsoft.com/v1.0/users/pdg%40corp.test/calendar/events' => Http::response(['id' => 'outlook-evt-1'], $eventStatus),
            'graph.microsoft.com/v1.0/users/pdg%40corp.test/events/*' => Http::response(null, 204),
        ]);
    }

    public function test_approval_creates_the_event_in_the_ceo_outlook_calendar(): void
    {
        $this->fakeGraph();
        $appointment = Appointment::factory()->create(['date' => '2026-10-05', 'time' => '10:00']);

        Livewire::test(Appointments::class)
            ->assertSee($appointment->fullName())
            ->call('open', 'approve', $appointment->id)
            ->assertDontSee('Valider quand même')
            ->set('slotTime', '11:30')
            ->set('slotDuration', 45)
            ->set('note', 'Merci de venir 10 minutes en avance.')
            ->call('confirm')
            ->assertHasNoErrors()
            ->assertSet('action', null);

        $appointment->refresh();
        $this->assertSame(AppointmentStatus::Approved, $appointment->status);
        $this->assertSame('11:30', $appointment->time);
        $this->assertSame('outlook-evt-1', $appointment->outlook_event_id);
        $this->assertSame('Assistante', $appointment->decidedBy->name);

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/calendar/events')
            && $r->hasHeader('Authorization', 'Bearer token')
            && $r['start'] === ['dateTime' => '2026-10-05T11:30:00', 'timeZone' => 'Africa/Abidjan']
            && $r['end'] === ['dateTime' => '2026-10-05T12:15:00', 'timeZone' => 'Africa/Abidjan']
            && $r['transactionId'] === $appointment->reference
            && str_contains($r['subject'], $appointment->last_name));

        Mail::assertSent(AppointmentDecision::class, fn ($m) => $m->hasTo($appointment->email)
            && str_contains($m->envelope()->subject, 'confirmé'));
    }

    public function test_outlook_failure_leaves_the_request_pending(): void
    {
        $this->fakeGraph(eventStatus: 503);
        $appointment = Appointment::factory()->create();

        Livewire::test(Appointments::class)
            ->call('open', 'approve', $appointment->id)
            ->call('confirm')
            ->assertHasErrors('slotDate');

        $this->assertSame(AppointmentStatus::Pending, $appointment->fresh()->status);
        Mail::assertNothingSent();
    }

    public function test_overlapping_slot_requires_explicit_confirmation(): void
    {
        $this->fakeGraph();
        $date = today()->addDays(2)->toDateString();
        Appointment::factory()->create(['date' => $date, 'time' => '10:00', 'duration' => 60])
            ->forceFill(['status' => AppointmentStatus::Approved])->save();
        $pending = Appointment::factory()->create(['date' => $date, 'time' => '10:30']);

        $component = Livewire::test(Appointments::class)
            ->assertSee('Chevauche un rendez-vous déjà validé')
            ->call('open', 'approve', $pending->id)
            ->call('confirm')
            ->assertCount('overlaps', 1);
        $this->assertSame(AppointmentStatus::Pending, $pending->fresh()->status);

        // Changer le créneau annule l'avertissement : on ne peut pas forcer un autre créneau à l'aveugle.
        $component->set('slotTime', '10:45')->call('confirm', true)->assertCount('overlaps', 1);
        $this->assertSame(AppointmentStatus::Pending, $pending->fresh()->status);

        $component->call('confirm', true)->assertSet('action', null);
        $this->assertSame(AppointmentStatus::Approved, $pending->fresh()->status);
    }

    public function test_rejection_with_reason_and_optional_notification(): void
    {
        $appointment = Appointment::factory()->create();

        Livewire::test(Appointments::class)
            ->call('open', 'reject', $appointment->id)
            ->set('note', 'Agenda complet')
            ->set('notify', false)
            ->call('confirm');

        $appointment->refresh();
        $this->assertSame(AppointmentStatus::Rejected, $appointment->status);
        $this->assertSame('Agenda complet', $appointment->decision_note);
        Mail::assertNothingSent();
    }

    public function test_cancellation_removes_the_outlook_event(): void
    {
        $this->fakeGraph();
        $appointment = Appointment::factory()->create();
        $appointment->forceFill(['status' => AppointmentStatus::Approved, 'outlook_event_id' => 'evt-42'])->save();

        Livewire::test(Appointments::class, ['status' => 'approved'])
            ->call('open', 'cancel', $appointment->id)
            ->call('confirm');

        $this->assertSame(AppointmentStatus::Cancelled, $appointment->fresh()->status);
        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && str_ends_with($r->url(), '/events/evt-42'));
        Mail::assertSent(AppointmentDecision::class);
    }

    public function test_an_already_processed_request_cannot_be_decided_twice(): void
    {
        $appointment = Appointment::factory()->create();
        $component = Livewire::test(Appointments::class)->call('open', 'reject', $appointment->id);

        $appointment->forceFill(['status' => AppointmentStatus::Rejected])->save(); // traité par un collègue entre-temps

        $component->call('confirm')->assertSee('Ce rendez-vous a déjà été traité.');
    }
}
