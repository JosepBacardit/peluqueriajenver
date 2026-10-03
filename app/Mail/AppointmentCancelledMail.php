<?php

namespace App\Mail;

use App\Models\Appointment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Sent to the customer when their appointment is cancelled, either by
 * themselves (a confirmation) or by the salon (with an invitation to book
 * another time).
 */
class AppointmentCancelledMail extends Mailable
{
    public function __construct(public Appointment $appointment, public bool $cancelledByCustomer) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->cancelledByCustomer
                ? 'Has cancelado tu cita en Peluquería Jenver'
                : 'Peluquería Jenver ha cancelado tu cita',
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.appointment-cancelled', with: [
            'bookingUrl' => route('reservas'),
        ]);
    }
}
