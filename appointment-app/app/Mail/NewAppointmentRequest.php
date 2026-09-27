<?php

namespace App\Mail;

use App\Models\Appointment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Envoyé au PDG et à son entourage à chaque nouvelle demande. */
class NewAppointmentRequest extends Mailable
{
    public function __construct(public Appointment $appointment) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Nouvelle demande de rendez-vous — {$this->appointment->fullName()} — "
                .$this->appointment->startsAt()->translatedFormat('d/m/Y H:i'),
            replyTo: [$this->appointment->email],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.new-request');
    }
}
