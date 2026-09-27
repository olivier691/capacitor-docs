<?php

namespace App\Mail;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Informe le demandeur de la validation, du refus ou de l'annulation. */
class AppointmentDecision extends Mailable
{
    public function __construct(public Appointment $appointment) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: match ($this->appointment->status) {
            AppointmentStatus::Approved => 'Rendez-vous confirmé — '
                .$this->appointment->startsAt()->translatedFormat('l j F Y \à H:i'),
            AppointmentStatus::Cancelled => "Rendez-vous annulé — {$this->appointment->reference}",
            default => "Votre demande de rendez-vous {$this->appointment->reference}",
        });
    }

    public function content(): Content
    {
        return new Content(view: 'mail.decision');
    }
}
