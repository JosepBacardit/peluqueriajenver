<?php

use App\Mail\AppointmentCancelledMail;
use App\Mail\AppointmentConfirmedMail;
use App\Mail\AppointmentRescheduledMail;
use App\Mail\CustomerCancelledAppointmentMail;
use App\Mail\NewAppointmentMail;
use App\Models\Appointment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

/*
 * The "From" the customer sees in her inbox is the configured sender
 * (MAIL_FROM_ADDRESS / MAIL_FROM_NAME, which deploy:check guards): no
 * booking email may set a sender of its own.
 */
test('every booking email is really sent from the configured salon sender', function (string $mailClass) {
    config([
        'mail.default' => 'array',
        'mail.from.address' => 'reservas@peluqueriajenver.test',
        'mail.from.name' => 'Peluquería Jenver',
    ]);
    $appointment = Appointment::factory()->create();
    $mail = $mailClass === AppointmentCancelledMail::class ? new $mailClass($appointment, true) : new $mailClass($appointment);

    Mail::to('clienta@example.test')->send($mail);

    $sent = app('mail.manager')->mailer('array')->getSymfonyTransport()->messages()->sole();
    $from = $sent->getOriginalMessage()->getFrom();

    expect($from)->toHaveCount(1);
    expect($from[0]->getAddress())->toBe('reservas@peluqueriajenver.test');
    expect($from[0]->getName())->toBe('Peluquería Jenver');
})->with([
    'confirmation to the customer' => AppointmentConfirmedMail::class,
    'notice to the salon' => NewAppointmentMail::class,
    'cancellation to the customer' => AppointmentCancelledMail::class,
    'cancellation notice to the salon' => CustomerCancelledAppointmentMail::class,
    'change of time to the customer' => AppointmentRescheduledMail::class,
]);

test('the example environment sends as the salon by default', function () {
    $env = file_get_contents(base_path('.env.example'));

    expect($env)->toContain('APP_NAME="Peluquería Jenver"');
    expect($env)->toContain('MAIL_FROM_NAME="${APP_NAME}"');
});
