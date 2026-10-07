<?php

namespace App\Mail;

use App\Models\Appointment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Sent to the salon when a customer cancels online.
 */
class CustomerCancelledAppointmentMail extends Mailable
{
    public function __construct(public Appointment $appointment) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Cita cancelada por el cliente: '.$this->appointment->starts_at->format('d/m H:i').' · '.$this->appointment->customer_name,
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.customer-cancelled-appointment', with: [
            'agendaUrl' => route('admin.agenda', ['fecha' => $this->appointment->starts_at->toDateString()]),
        ]);
    }
}
