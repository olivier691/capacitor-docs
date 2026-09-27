<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Mail\AppointmentDecision;
use App\Mail\AppointmentReceived;
use App\Mail\NewAppointmentRequest;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class AppointmentService
{
    public function __construct(private readonly OutlookCalendar $calendar) {}

    /** Enregistre une demande et prévient le PDG, son entourage et le demandeur. */
    public function submit(array $data): Appointment
    {
        $appointment = Appointment::create($data);

        $this->sendQuietly(config('appointments.notify'), new NewAppointmentRequest($appointment));
        $this->sendQuietly($appointment->email, new AppointmentReceived($appointment));

        return $appointment;
    }

    /**
     * Valide la demande et l'inscrit dans l'agenda Outlook du PDG.
     * Si Outlook refuse, rien n'est modifié : l'application et l'agenda restent cohérents.
     */
    public function approve(Appointment $appointment, User $by, array $slot = [], ?string $note = null): Appointment
    {
        $this->ensureStatus($appointment, AppointmentStatus::Pending);

        $appointment->fill(array_filter($slot, fn ($v) => filled($v)));
        $appointment->outlook_event_id = $this->calendar->createEvent($appointment);

        $this->recordDecision($appointment, AppointmentStatus::Approved, $by, $note);
        $this->sendQuietly($appointment->email, new AppointmentDecision($appointment));

        return $appointment;
    }

    public function reject(Appointment $appointment, User $by, ?string $note = null, bool $notify = true): Appointment
    {
        $this->ensureStatus($appointment, AppointmentStatus::Pending);
        $this->recordDecision($appointment, AppointmentStatus::Rejected, $by, $note);

        if ($notify) {
            $this->sendQuietly($appointment->email, new AppointmentDecision($appointment));
        }

        return $appointment;
    }

    public function cancel(Appointment $appointment, User $by, ?string $note = null, bool $notify = true): Appointment
    {
        $this->ensureStatus($appointment, AppointmentStatus::Approved);

        $this->calendar->deleteEvent($appointment->outlook_event_id);
        $appointment->outlook_event_id = null;
        $this->recordDecision($appointment, AppointmentStatus::Cancelled, $by, $note);

        if ($notify) {
            $this->sendQuietly($appointment->email, new AppointmentDecision($appointment));
        }

        return $appointment;
    }

    /** Pointage par la sécurité : le visiteur s'est-il présenté ? */
    public function recordArrival(Appointment $appointment, User $by, bool $arrived): Appointment
    {
        $this->ensureStatus($appointment, AppointmentStatus::Approved);

        if (! $appointment->isToday()) {
            throw new RuntimeException('La présence ne peut être pointée que le jour du rendez-vous.');
        }

        $appointment->forceFill([
            'arrived_at' => $arrived ? now() : null,
            'arrival_recorded_by' => $arrived ? $by->id : null,
        ])->save();

        return $appointment;
    }

    private function recordDecision(Appointment $appointment, AppointmentStatus $status, User $by, ?string $note): void
    {
        DB::transaction(fn () => $appointment->forceFill([
            'status' => $status,
            'decided_by' => $by->id,
            'decided_at' => now(),
            'decision_note' => filled($note) ? trim($note) : null,
        ])->save());
    }

    private function ensureStatus(Appointment $appointment, AppointmentStatus $expected): void
    {
        // Relit l'état en base : un autre membre de la direction a pu traiter la demande entre-temps.
        $appointment->refresh();

        if ($appointment->status !== $expected) {
            throw new RuntimeException(match ($expected) {
                AppointmentStatus::Pending => 'Ce rendez-vous a déjà été traité.',
                default => "Ce rendez-vous n'est pas validé.",
            });
        }
    }

    /** Un e-mail qui échoue ne doit pas bloquer l'enregistrement : on le journalise. */
    private function sendQuietly(string|array $to, Mailable $mail): void
    {
        if (empty($to)) {
            return;
        }

        try {
            Mail::to($to)->send($mail);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
