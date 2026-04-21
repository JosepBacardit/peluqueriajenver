<?php

namespace App\Mail;

use App\Models\DiscountCode;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DiscountCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly DiscountCode $discountCode,
        public readonly int $percentage,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Tu código de descuento de Peluquería Jenver',
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlView: 'emails.discount-code',
            textView: 'emails.discount-code-text',
        );
    }
}
