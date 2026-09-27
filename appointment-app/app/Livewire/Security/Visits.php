<?php

namespace App\Livewire\Security;

use App\Models\Appointment;
use App\Services\AppointmentService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use RuntimeException;

/**
 * Espace du service de sécurité : consultation des rendez-vous validés (lecture seule)
 * et pointage des visiteurs effectivement venus.
 */
#[Layout('layouts.app', ['heading' => "Contrôle d'accès — Rendez-vous"])]
#[Title('Sécurité')]
class Visits extends Component
{
    #[Url]
    public string $date = '';

    public function mount(): void
    {
        Gate::authorize('record-arrival');
        $this->normalizeDate();
    }

    public function updatedDate(): void
    {
        $this->normalizeDate();
    }

    public function shiftDay(int $days): void
    {
        $this->date = Carbon::parse($this->date)->addDays($days)->toDateString();
    }

    public function goToday(): void
    {
        $this->date = today()->toDateString();
    }

    #[Computed]
    public function visits()
    {
        return Appointment::approved()
            ->with('arrivalRecordedBy')
            ->whereDate('date', $this->date)
            ->orderBy('time')
            ->get();
    }

    public function toggleArrival(int $id, AppointmentService $service): void
    {
        Gate::authorize('record-arrival');
        $appointment = Appointment::findOrFail($id);

        try {
            $service->recordArrival($appointment, Auth::user(), $appointment->arrived_at === null);
        } catch (RuntimeException $e) {
            session()->flash('error', $e->getMessage());
        }

        unset($this->visits);
    }

    private function normalizeDate(): void
    {
        $valid = preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->date) && Carbon::hasFormat($this->date, 'Y-m-d');
        $this->date = $valid ? $this->date : today()->toDateString();
    }

    public function render()
    {
        return view('livewire.security.visits', [
            'isToday' => $this->date === today()->toDateString(),
        ]);
    }
}
