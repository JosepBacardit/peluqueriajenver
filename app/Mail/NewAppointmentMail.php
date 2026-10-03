<?php

namespace App\Mail;

use App\Models\Appointment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Sent to the salon when a customer books online.
 */
class NewAppointmentMail extends Mailable
{
    public function __construct(public Appointment $appointment) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: $this->appointment->customer_email ? [new Address($this->appointment->customer_email, $this->appointment->customer_name)] : [],
            subject: 'Nueva cita online: '.$this->appointment->starts_at->format('d/m H:i').' · '.$this->appointment->customer_name,
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.new-appointment', with: [
            'agendaUrl' => route('admin.agenda', ['fecha' => $this->appointment->starts_at->toDateString()]),
        ]);
    }
}
