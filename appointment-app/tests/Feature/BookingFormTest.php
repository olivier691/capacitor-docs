<?php

namespace Tests\Feature;

use App\Livewire\BookingForm;
use App\Mail\AppointmentReceived;
use App\Mail\NewAppointmentRequest;
use App\Models\Appointment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class BookingFormTest extends TestCase
{
    use RefreshDatabase;

    private function validForm(): array
    {
        return [
            'title' => 'Mme', 'first_name' => 'Awa', 'last_name' => 'Traoré', 'company' => 'BNI',
            'email' => 'Awa@BNI.ci', 'phone' => '+225 07 11 22 33 44', 'subject' => 'Présentation projet',
            'date' => today()->addDay()->toDateString(), 'time' => '15:00', 'duration' => 30,
            'attendees' => 1, 'consent' => true,
        ];
    }

    public function test_page_is_public_and_mobile_ready(): void
    {
        $this->withoutVite();
        $this->get('/')->assertOk()->assertSee('Envoyer la demande')->assertSee('width=device-width', false);
    }

    public function test_incomplete_form_is_rejected_with_french_messages(): void
    {
        Livewire::test(BookingForm::class)
            ->set('attendees', 3)
            ->set('date', '2000-01-01')
            ->call('submit')
            ->assertHasErrors(['first_name', 'last_name', 'email', 'phone', 'subject', 'time', 'consent', 'companions', 'date'])
            ->assertSee('Le prénom est obligatoire.')
            ->assertSee('Indiquez le nom des personnes qui vous accompagnent.')
            ->assertSee('La date doit être aujourd’hui ou ultérieure.');

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_valid_request_is_saved_and_sent_to_ceo_entourage_and_requester(): void
    {
        Mail::fake();
        config(['appointments.notify' => ['pdg@corp.test', 'assistante@corp.test']]);

        $component = Livewire::test(BookingForm::class)->fill($this->validForm())->call('submit')->assertHasNoErrors();

        $appointment = Appointment::sole();
        $component->assertSet('reference', $appointment->reference)->assertSee($appointment->reference);
        $this->assertSame('awa@bni.ci', $appointment->email);
        $this->assertSame('pending', $appointment->status->value);
        $this->assertNull($appointment->companions);

        Mail::assertSent(NewAppointmentRequest::class, fn ($m) => $m->hasTo('pdg@corp.test') && $m->hasTo('assistante@corp.test'));
        Mail::assertSent(AppointmentReceived::class, fn ($m) => $m->hasTo('awa@bni.ci'));
    }

    public function test_submissions_are_rate_limited(): void
    {
        Mail::fake();
        foreach (range(1, 5) as $_) {
            Livewire::test(BookingForm::class)->fill($this->validForm())->call('submit')->assertHasNoErrors();
        }

        Livewire::test(BookingForm::class)->fill($this->validForm())->call('submit')->assertHasErrors('form');
        $this->assertDatabaseCount('appointments', 5);
    }
}
