<?php

namespace App\Services;

use App\Models\Appointment;
use App\Services\MicrosoftGraph\GraphClient;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;

/**
 * Synchronise les rendez-vous validés avec l'agenda Outlook du PDG.
 * Sans configuration Microsoft Graph, fonctionne en mode simulation (journal Laravel).
 */
class OutlookCalendar
{
    public function __construct(
        private readonly GraphClient $graph,
        private readonly ?string $calendarUser,
    ) {}

    public function isConfigured(): bool
    {
        return $this->graph->isConfigured() && filled($this->calendarUser);
    }

    /** Crée l'événement et renvoie son identifiant Outlook. */
    public function createEvent(Appointment $appointment): string
    {
        $subject = 'RDV : '.$appointment->fullName()
            .($appointment->company ? " ({$appointment->company})" : '')
            .' — '.$appointment->subject;

        if (! $this->isConfigured()) {
            Log::info("[simulation agenda] {$appointment->startsAt()} ({$appointment->duration} min) — {$subject}");

            return 'simulation-'.$appointment->reference;
        }

        $timeZone = config('app.timezone');

        $event = $this->graph->request()->post(GraphClient::userPath($this->calendarUser).'/calendar/events', [
            'subject' => $subject,
            'body' => [
                'contentType' => 'HTML',
                'content' => view('mail.partials.appointment-details', ['appointment' => $appointment])->render(),
            ],
            'start' => ['dateTime' => $appointment->startsAt()->format('Y-m-d\TH:i:s'), 'timeZone' => $timeZone],
            'end' => ['dateTime' => $appointment->endsAt()->format('Y-m-d\TH:i:s'), 'timeZone' => $timeZone],
            'location' => ['displayName' => config('appointments.location')],
            'showAs' => 'busy',
            'isReminderOn' => true,
            'reminderMinutesBeforeStart' => 15,
            'categories' => ['Rendez-vous'],
            // Évite un doublon si la requête est rejouée.
            'transactionId' => $appointment->reference,
        ]);

        return $event->json('id');
    }

    public function deleteEvent(?string $eventId): void
    {
        if (blank($eventId)) {
            return;
        }

        if (! $this->isConfigured() || str_starts_with($eventId, 'simulation-')) {
            Log::info("[simulation agenda] suppression {$eventId}");

            return;
        }

        try {
            $this->graph->request()->delete(GraphClient::userPath($this->calendarUser).'/events/'.rawurlencode($eventId));
        } catch (RequestException $e) {
            // Déjà supprimé directement dans Outlook : rien à faire.
            if ($e->response->status() !== 404) {
                throw $e;
            }
        }
    }
}
