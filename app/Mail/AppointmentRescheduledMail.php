<?php

namespace App\Mail;

use App\Models\Appointment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Sent to the customer when the salon moves their appointment to another
 * time or service, with the same personal link they already had. Never
 * includes the service price.
 */
class AppointmentRescheduledMail extends Mailable
{
    public function __construct(public Appointment $appointment) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Tu cita en Peluquería Jenver ha cambiado: '.$this->appointment->dayLabel().' a las '.$this->appointment->starts_at->format('H:i'),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.appointment-rescheduled', with: [
            'appointmentUrl' => route('cita.show', $this->appointment->token),
        ]);
    }
}
