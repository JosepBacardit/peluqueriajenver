<?php

namespace App\Mail;

use App\Models\Appointment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Sent to the customer when an appointment is confirmed. Never includes
 * the service price.
 */
class AppointmentConfirmedMail extends Mailable
{
    public function __construct(public Appointment $appointment) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Tu cita en Peluquería Jenver: '.$this->appointment->dayLabel().' a las '.$this->appointment->starts_at->format('H:i'),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.appointment-confirmed', with: [
            'appointmentUrl' => route('cita.show', $this->appointment->token),
        ]);
    }
}
