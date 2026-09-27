<?php

namespace App\Livewire\Direction;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Services\AppointmentService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use RuntimeException;

#[Layout('layouts.app', ['heading' => 'Rendez-vous du PDG'])]
#[Title('Direction')]
class Appointments extends Component
{
    #[Url]
    public string $status = 'pending';

    /** Action en cours dans la fenêtre de confirmation : approve, reject ou cancel. */
    #[Locked]
    public ?string $action = null;

    #[Locked]
    public ?int $appointmentId = null;

    public string $slotDate = '';

    public string $slotTime = '';

    public int $slotDuration = 30;

    public string $note = '';

    public bool $notify = true;

    /** Rendez-vous validés qui chevauchent le créneau à valider (confirmation requise). */
    public array $overlaps = [];

    public function mount(): void
    {
        Gate::authorize('manage-appointments');
    }

    #[Computed]
    public function appointments()
    {
        return Appointment::query()
            ->with(['decidedBy', 'arrivalRecordedBy'])
            ->when(AppointmentStatus::tryFrom($this->status), fn ($q, $s) => $q->where('status', $s))
            ->when($this->status === 'pending',
                fn ($q) => $q->orderBy('date')->orderBy('time'),
                fn ($q) => $q->orderByDesc('date')->orderByDesc('time'))
            ->limit(200)
            ->get();
    }

    #[Computed]
    public function counts()
    {
        return Appointment::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
    }

    #[Computed]
    public function current(): ?Appointment
    {
        return $this->appointmentId ? Appointment::find($this->appointmentId) : null;
    }

    public function open(string $action, int $id): void
    {
        Gate::authorize('manage-appointments');
        abort_unless(in_array($action, ['approve', 'reject', 'cancel'], true), 400);

        $appointment = Appointment::findOrFail($id);
        $this->resetValidation();
        $this->action = $action;
        $this->appointmentId = $appointment->id;
        $this->slotDate = $appointment->date->format('Y-m-d');
        $this->slotTime = $appointment->time;
        $this->slotDuration = $appointment->duration;
        $this->note = '';
        $this->notify = true;
        $this->overlaps = [];
        unset($this->current);
    }

    /** Un créneau modifié annule la confirmation de chevauchement déjà affichée. */
    public function updated(string $property): void
    {
        if (str_starts_with($property, 'slot')) {
            $this->overlaps = [];
        }
    }

    public function close(): void
    {
        $this->reset('action', 'appointmentId', 'note', 'overlaps');
    }

    public function confirm(AppointmentService $service, bool $force = false): void
    {
        Gate::authorize('manage-appointments');
        $appointment = Appointment::findOrFail($this->appointmentId);
        $user = Auth::user();

        try {
            match ($this->action) {
                'approve' => $this->approve($service, $appointment, $force),
                'reject' => $service->reject($appointment, $user, $this->note, $this->notify),
                'cancel' => $service->cancel($appointment, $user, $this->note, $this->notify),
            };
        } catch (RuntimeException $e) {
            $this->close();
            session()->flash('error', $e->getMessage());

            return;
        } catch (RequestException|ConnectionException $e) {
            report($e);
            $this->addError('slotDate', "L'agenda Outlook n'a pas pu être mis à jour. Rien n'a été modifié ; réessayez dans un instant.");

            return;
        }

        if ($this->overlaps) {
            return; // Attend la confirmation explicite du chevauchement.
        }

        session()->flash('success', match ($this->action) {
            'approve' => "Rendez-vous validé et ajouté à l'agenda Outlook du PDG.",
            'reject' => 'Demande refusée.',
            'cancel' => "Rendez-vous annulé et retiré de l'agenda Outlook.",
        });
        $this->close();
    }

    private function approve(AppointmentService $service, Appointment $appointment, bool $force): void
    {
        $slot = $this->validate([
            'slotDate' => ['required', 'date_format:Y-m-d'],
            'slotTime' => ['required', 'date_format:H:i'],
            'slotDuration' => ['required', 'integer', Rule::in(config('appointments.durations'))],
        ]);

        // Le forçage n'est accepté que si l'avertissement affiché porte sur ce créneau précis.
        $force = $force && $this->overlaps !== [];
        $overlaps = $appointment->overlappingApproved($slot['slotDate'], $slot['slotTime'], $slot['slotDuration']);
        if ($overlaps->isNotEmpty() && ! $force) {
            $this->overlaps = $overlaps->map(fn ($o) => "{$o->time} — {$o->fullName()}")->all();

            return;
        }
        $this->overlaps = [];

        $service->approve($appointment, Auth::user(), [
            'date' => $slot['slotDate'],
            'time' => $slot['slotTime'],
            'duration' => $slot['slotDuration'],
        ], $this->note);
    }

    public function render()
    {
        return view('livewire.direction.appointments', [
            'tabs' => ['pending' => 'À traiter', 'approved' => 'Validés', 'rejected' => 'Refusés', 'cancelled' => 'Annulés', 'all' => 'Tous'],
            'durations' => config('appointments.durations'),
        ]);
    }
}
