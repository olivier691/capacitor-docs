<?php

namespace App\Livewire;

use App\Services\AppointmentService;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Demande de rendez-vous')]
class BookingForm extends Component
{
    public const TITLES = ['M.', 'Mme', 'Dr', 'Pr', 'Me'];

    public string $title = '';

    public string $first_name = '';

    public string $last_name = '';

    public string $company = '';

    public string $job_title = '';

    public string $email = '';

    public string $phone = '';

    public string $subject = '';

    public string $date = '';

    public string $time = '';

    public int $duration = 30;

    public int $attendees = 1;

    public string $companions = '';

    public string $message = '';

    public bool $consent = false;

    /** Référence de la demande envoyée (affiche l'écran de confirmation). */
    public ?string $reference = null;

    protected function rules(): array
    {
        return [
            'title' => ['nullable', Rule::in(self::TITLES)],
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'company' => ['nullable', 'string', 'max:120'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:160'],
            'phone' => ['required', 'regex:/^\+?[0-9 ().-]{8,20}$/'],
            'subject' => ['required', 'string', 'max:200'],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'time' => ['required', 'date_format:H:i'],
            'duration' => ['required', 'integer', Rule::in(config('appointments.durations'))],
            'attendees' => ['required', 'integer', 'min:1', 'max:'.config('appointments.max_attendees')],
            'companions' => [Rule::requiredIf($this->attendees > 1), 'nullable', 'string', 'max:500'],
            'message' => ['nullable', 'string', 'max:2000'],
            'consent' => ['accepted'],
        ];
    }

    public function submit(AppointmentService $appointments): void
    {
        $data = $this->validate();

        // Anti-abus : 5 demandes par tranche de 15 minutes et par adresse IP.
        $key = 'booking:'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('form', 'Trop de demandes envoyées. Veuillez réessayer dans quelques minutes.');

            return;
        }
        RateLimiter::hit($key, 15 * 60);

        unset($data['consent']);
        if ($this->attendees <= 1) {
            $data['companions'] = null;
        }
        $data = array_map(fn ($v) => is_string($v) ? (trim($v) === '' ? null : trim($v)) : $v, $data);
        $data['email'] = strtolower($data['email']);

        $this->reference = $appointments->submit($data)->reference;
    }

    public function startOver(): void
    {
        $this->reset();
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.booking-form', [
            'titles' => self::TITLES,
            'durations' => config('appointments.durations'),
            'today' => today()->toDateString(),
        ]);
    }
}
