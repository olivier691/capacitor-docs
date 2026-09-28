<?php

namespace App\Mail;

use App\Models\Appointment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Accusé de réception envoyé au demandeur. */
class AppointmentReceived extends Mailable
{
    public function __construct(public Appointment $appointment) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Demande de rendez-vous reçue — {$this->appointment->reference}");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.received');
    }
}
