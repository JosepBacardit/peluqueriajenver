<?php

use App\Mail\AppointmentCancelledMail;
use App\Mail\AppointmentConfirmedMail;
use App\Mail\AppointmentRescheduledMail;
use App\Mail\CustomerCancelledAppointmentMail;
use App\Mail\NewAppointmentMail;
use App\Models\Appointment;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Mirrors the production APP_NAME required by deploy:check (T015):
    // the <title> of every booking email reads it from config('app.name').
    config(['app.name' => 'Peluquería Jenver']);
});

/*
 * Every booking email must carry Peluquería Jenver's own branding (black
 * and gold, its logo, its own footer) instead of the framework's default
 * Markdown mail theme (Laravel's logo linked to laravel.com, a "© {year}
 * Laravel. All rights reserved." footer).
 */
test('every booking email shows the salon\'s brand, not the framework\'s', function (string $mailClass) {
    $appointment = Appointment::factory()->create();

    $mail = $mailClass === AppointmentCancelledMail::class ? new $mailClass($appointment, true) : new $mailClass($appointment);
    $html = $mail->render();

    expect($html)->not->toContain('laravel.com');
    expect($html)->not->toContain('Laravel');
    expect($html)->toContain('Peluquería Jenver');
    // The salon's black-and-gold palette, inlined by the Markdown renderer.
    expect(strtolower($html))->toContain('#c9a84c');
    // The footer shows the salon's own contact details, not a bare
    // copyright line.
    expect($html)->toContain('C/ Lleida, 21');
    expect($html)->toContain('633 912 050');
})->with([
    'confirmation to the customer' => AppointmentConfirmedMail::class,
    'notice to the salon' => NewAppointmentMail::class,
    'cancellation to the customer' => AppointmentCancelledMail::class,
    'cancellation notice to the salon' => CustomerCancelledAppointmentMail::class,
    'change of time to the customer' => AppointmentRescheduledMail::class,
]);

test('the logo in every booking email is an absolute, public URL with alt text', function (string $mailClass) {
    $appointment = Appointment::factory()->create();

    $mail = $mailClass === AppointmentCancelledMail::class ? new $mailClass($appointment, true) : new $mailClass($appointment);
    $html = $mail->render();

    preg_match('/<img[^>]*class="logo"[^>]*>/', $html, $matches);

    expect($matches)->not->toBeEmpty();
    expect($matches[0])->toContain('src="'.config('app.url'));
    expect($matches[0])->toContain('alt="Peluquería Jenver"');
})->with([
    'confirmation to the customer' => AppointmentConfirmedMail::class,
    'notice to the salon' => NewAppointmentMail::class,
    'cancellation to the customer' => AppointmentCancelledMail::class,
    'cancellation notice to the salon' => CustomerCancelledAppointmentMail::class,
    'change of time to the customer' => AppointmentRescheduledMail::class,
]);
