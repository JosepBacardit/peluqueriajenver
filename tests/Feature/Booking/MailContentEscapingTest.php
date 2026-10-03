<?php

use App\Mail\AppointmentCancelledMail;
use App\Mail\AppointmentConfirmedMail;
use App\Mail\CustomerCancelledAppointmentMail;
use App\Mail\NewAppointmentMail;
use App\Models\Appointment;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * Customer-typed text goes into Markdown emails sent from the salon's own
 * domain. Markdown syntax in it must stay plain text, never become a link
 * (otherwise the booking form is a phishing relay).
 */
test('markdown typed by the customer never becomes a link in any booking email', function (string $mailClass) {
    $appointment = Appointment::factory()->create([
        'customer_name' => '[Pulsa aquí para confirmar](https://evil.example/x)',
        'notes' => '[Descarga tu factura](https://evil.example/y) <https://evil.example/z>',
    ]);

    $mail = $mailClass === AppointmentCancelledMail::class ? new $mailClass($appointment, true) : new $mailClass($appointment);
    $html = $mail->render();

    expect($html)->not->toContain('href="https://evil.example');
})->with([
    'confirmation to the customer' => AppointmentConfirmedMail::class,
    'notice to the salon' => NewAppointmentMail::class,
    'cancellation to the customer' => AppointmentCancelledMail::class,
    'cancellation notice to the salon' => CustomerCancelledAppointmentMail::class,
]);
