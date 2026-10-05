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

    // Shown at 124x56 in every client (Outlook ignores max-height and
    // width:auto, so the size lives in the attributes), from a file made
    // at twice that size instead of the 400px website logo.
    expect($matches[0])->toContain('src="'.asset('images/logo-jenver-email.png').'"');
    expect($matches[0])->toContain('width="124"');
    expect($matches[0])->toContain('height="56"');
    expect(getimagesize(public_path('images/logo-jenver-email.png')))->toMatchArray([0 => 248, 1 => 112]);
    expect(filesize(public_path('images/logo-jenver-email.png')))->toBeLessThan(15 * 1024);
})->with([
    'confirmation to the customer' => AppointmentConfirmedMail::class,
    'notice to the salon' => NewAppointmentMail::class,
    'cancellation to the customer' => AppointmentCancelledMail::class,
    'cancellation notice to the salon' => CustomerCancelledAppointmentMail::class,
    'change of time to the customer' => AppointmentRescheduledMail::class,
]);

/*
 * The personal link is also written out for when the button does not
 * work. As plain text a 48-character token cannot wrap and stretches the
 * email past a phone's width; inside a link the theme lets it break.
 */
test('the personal link written out in the customer emails can wrap on a phone', function (string $mailClass) {
    $appointment = Appointment::factory()->create();
    $url = route('cita.show', $appointment->token);

    $html = (new $mailClass($appointment))->render();

    expect(preg_match('/<a href="'.preg_quote($url, '/').'"[^>]*word-break: break-all;[^>]*>'.preg_quote($url, '/').'<\/a>/', $html))->toBe(1);
    // The URL never appears as unbreakable text outside a link.
    expect(substr_count($html, '>'.$url))->toBe(substr_count($html, '>'.$url.'</a>'));
})->with([
    'confirmation to the customer' => AppointmentConfirmedMail::class,
    'change of time to the customer' => AppointmentRescheduledMail::class,
]);

/*
 * Outlook for Windows ignores the padding and borders of a link, so the
 * button's colour and padding also live on its table cell.
 */
test('the button keeps its shape in Outlook for Windows', function () {
    $html = (new AppointmentConfirmedMail(Appointment::factory()->create()))->render();

    preg_match('/<td[^>]*bgcolor="#c9a84c"[^>]*>\s*<a[^>]*class="button button-primary"/', $html, $matches);

    expect($matches)->not->toBeEmpty();
    expect($matches[0])->toContain('mso-padding-alt: 8px 18px');
});
