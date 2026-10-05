<?php

use App\Enums\AppointmentSource;
use App\Models\Appointment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->travelTo(CarbonImmutable::parse('2030-01-08 08:00'));
});

test('the admin home opens today\'s agenda', function () {
    $this->get(route('admin.home'))->assertRedirect(route('admin.agenda'));
});

test('the agenda lists the day\'s appointments by time with every detail', function () {
    Appointment::factory()->create([
        'starts_at' => '2030-01-08 12:00', 'ends_at' => '2030-01-08 13:30', 'service_name' => 'Balayage',
        'customer_name' => 'Laura Pérez', 'customer_phone' => '600 111 222', 'customer_email' => 'laura@example.test',
        'notes' => 'Primera visita', 'source' => AppointmentSource::Web,
    ]);
    Appointment::factory()->create([
        'starts_at' => '2030-01-08 09:30', 'ends_at' => '2030-01-08 10:00', 'service_name' => 'Corte',
        'customer_name' => 'Marc Soler', 'customer_email' => null, 'source' => AppointmentSource::Admin,
    ]);
    Appointment::factory()->create(['starts_at' => '2030-01-09 10:00', 'ends_at' => '2030-01-09 11:00', 'customer_name' => 'Otro Día']);

    $this->get(route('admin.agenda'))
        ->assertOk()
        ->assertSeeInOrder(['09:30', 'Marc Soler', 'Panel', '12:00', '13:30', 'Balayage', 'Laura Pérez', '600 111 222', 'laura@example.test', 'Primera visita', 'Web', 'Confirmada'])
        ->assertDontSee('Otro Día');
});

test('the agenda navigates to other days', function () {
    Appointment::factory()->create(['starts_at' => '2030-01-09 10:00', 'ends_at' => '2030-01-09 11:00', 'customer_name' => 'Mañana Cliente']);

    $this->get(route('admin.agenda', ['fecha' => '2030-01-09']))
        ->assertOk()
        ->assertSee('Mañana Cliente')
        ->assertSee('href="'.route('admin.agenda', ['fecha' => '2030-01-08']).'"', false)
        ->assertSee('href="'.route('admin.agenda', ['fecha' => '2030-01-10']).'"', false);
});

/**
 * Review finding N2 (.ai/reviews/mobile-admin-ux.md, coordinator): prev/next
 * are icon-only buttons with an aria-label, so the day switcher fits one row
 * at 375px instead of wrapping to two lines.
 */
test('the day switcher uses icon-only prev/next buttons with an accessible label', function () {
    $html = $this->get(route('admin.agenda'))->assertOk()->getContent();

    expect($html)->toContain('aria-label="Día anterior">←</a>');
    expect($html)->toContain('aria-label="Día siguiente">→</a>');
    expect($html)->not->toContain('← Día anterior')->not->toContain('Día siguiente →');
});

/**
 * Review finding N3 (.ai/reviews/mobile-admin-ux.md, coordinator): the
 * "ir a la fecha" date picker measured 34px; py-3 brings it to 44px.
 */
test('the "ir a la fecha" date picker meets the 44px touch target', function () {
    $this->get(route('admin.agenda'))->assertSee('id="fecha" type="date" name="fecha" value="2030-01-08" class="bg-black border border-[#2A2A2A] px-2 py-3"', false);
});

test('an invalid date falls back to today', function () {
    $this->get(route('admin.agenda', ['fecha' => 'not-a-date']))->assertOk()->assertSee('08/01/2030');
});

test('a day without appointments says so', function () {
    $this->get(route('admin.agenda'))->assertSee('No hay citas este día.');
});

test('cancelled appointments stay visible as cancelled', function () {
    Appointment::factory()->cancelled()->create(['starts_at' => '2030-01-08 10:00', 'ends_at' => '2030-01-08 11:00', 'customer_name' => 'Cancelada Cliente']);

    $this->get(route('admin.agenda'))->assertSee('Cancelada Cliente')->assertSee('Cancelada');
});

/**
 * Review finding N1 (.ai/reviews/mobile-admin-ux.md, coordinator): calling
 * and WhatsApp are the most-used actions, so they are large buttons next to
 * Editar/Cancelar, not 21px text links in the detail line. The phone number
 * must still be readable as plain text. A cancelled appointment keeps
 * Llamar/WhatsApp (the salon may still need to reach the customer) but not
 * Editar/Cancelar.
 */
test('an appointment card offers Llamar and WhatsApp as buttons, with the phone still visible as text', function () {
    Appointment::factory()->create([
        'starts_at' => '2030-01-08 10:00', 'ends_at' => '2030-01-08 10:30',
        'customer_name' => 'Marta Ruiz', 'customer_phone' => '633 912 050',
    ]);

    $html = $this->get(route('admin.agenda'))->assertOk()->getContent();

    expect($html)->toContain('Marta Ruiz · 633 912 050');
    expect($html)->toContain('href="tel:633912050" class="btn-outline text-sm" aria-label="Llamar a Marta Ruiz"');
    expect($html)->toContain('href="https://wa.me/34633912050?text=Hola%20Marta%20Ruiz%2C%20te%20escribimos%20de%20Peluquer%C3%ADa%20Jenver%20sobre%20tu%20cita." target="_blank" rel="noopener noreferrer" class="btn-outline text-sm"');
});

test('a cancelled appointment still offers Llamar and WhatsApp, but not Editar/Cancelar', function () {
    $appointment = Appointment::factory()->cancelled()->create([
        'starts_at' => '2030-01-08 10:00', 'ends_at' => '2030-01-08 11:00', 'customer_name' => 'Cancelada Cliente',
    ]);

    $html = $this->get(route('admin.agenda'))->assertOk()->getContent();

    expect(substr_count($html, 'aria-label="Llamar a Cancelada Cliente"'))->toBe(1);
    expect(substr_count($html, 'aria-label="Abrir WhatsApp con Cancelada Cliente"'))->toBe(1);
    expect($html)->not->toContain('href="'.route('admin.appointments.edit', $appointment).'"');
    expect($html)->not->toContain('Cancelar cita');
});

test('the agenda requires an authenticated user', function () {
    auth()->logout();

    $this->get(route('admin.agenda'))->assertRedirect(route('login'));
});

test('the agenda flags confirmed appointments whose confirmation email could not be sent', function () {
    Appointment::factory()->create(['starts_at' => '2030-01-08 10:00', 'ends_at' => '2030-01-08 11:00', 'customer_name' => 'Sin Correo', 'customer_notified_at' => null]);
    Appointment::factory()->create(['starts_at' => '2030-01-08 12:00', 'ends_at' => '2030-01-08 13:00', 'customer_name' => 'Con Correo', 'customer_notified_at' => now()]);
    Appointment::factory()->create(['starts_at' => '2030-01-08 14:00', 'ends_at' => '2030-01-08 15:00', 'customer_name' => 'Sin Email', 'customer_email' => null]);

    $html = $this->get(route('admin.agenda'))->getContent();

    expect(substr_count($html, 'Correo de confirmación no enviado'))->toBe(1);
    expect(strpos($html, 'Correo de confirmación no enviado'))->toBeLessThan(strpos($html, 'Con Correo'));
});

test('only confirmed appointments that have not started yet offer to be edited', function () {
    $upcoming = Appointment::factory()->create(['starts_at' => '2030-01-08 10:00', 'ends_at' => '2030-01-08 11:00']);
    $started = Appointment::factory()->create(['starts_at' => '2030-01-08 07:30', 'ends_at' => '2030-01-08 08:30']);
    $cancelled = Appointment::factory()->cancelled()->create(['starts_at' => '2030-01-08 12:00', 'ends_at' => '2030-01-08 13:00']);

    $this->get(route('admin.agenda'))
        ->assertSee('href="'.route('admin.appointments.edit', $upcoming).'"', false)
        ->assertDontSee('href="'.route('admin.appointments.edit', $started).'"', false)
        ->assertDontSee('href="'.route('admin.appointments.edit', $cancelled).'"', false);
});

/**
 * PRF-092: a direct shortcut to tomorrow, next to "Hoy", so the salon can
 * jump straight to it to note a phone/WhatsApp booking for the next day.
 */
test('the agenda offers a shortcut to tomorrow', function () {
    $this->get(route('admin.agenda'))
        ->assertSee('Mañana')
        ->assertSee('href="'.route('admin.agenda', ['fecha' => '2030-01-09']).'"', false);
});

/**
 * Review finding L2 (.ai/reviews/mobile-admin-ux.md): "Mañana" must stay
 * tomorrow relative to today (2030-01-08, frozen in beforeEach), not to
 * whatever day is currently being viewed — unlike "Día siguiente →", which
 * does depend on the viewed day.
 */
test('"Mañana" still points to the day after today when viewing a different day', function () {
    $html = $this->get(route('admin.agenda', ['fecha' => '2030-01-20']))->assertOk()->getContent();

    // "Día siguiente →" does depend on the viewed day (2030-01-21 here);
    // "Mañana" must not: it stays the day after today (2030-01-09),
    // frozen by beforeEach() to 2030-01-08.
    preg_match('/href="([^"]+)"[^>]*>Mañana</', $html, $match);

    expect($match[1] ?? null)->toBe(route('admin.agenda', ['fecha' => '2030-01-09']));
});

/**
 * PRF-093: a floating "new appointment" button for phones, always
 * reachable with the thumb; the top button stays for desktop only.
 */
test('the agenda has a floating "new appointment" button for phones', function () {
    $html = $this->get(route('admin.agenda'))->assertOk()->getContent();

    expect(substr_count($html, 'aria-label="Nueva cita"'))->toBe(1);
    expect($html)->toContain('md:hidden fixed right-4');
    expect($html)->toContain('hidden md:inline-flex btn-gold');
});

/**
 * PRF-095: WhatsApp from the appointment card, phone normalized to
 * international format.
 */
test('an appointment card offers to open WhatsApp with the customer, phone normalized to +34 when missing', function () {
    Appointment::factory()->create([
        'starts_at' => '2030-01-08 10:00', 'ends_at' => '2030-01-08 10:30',
        'customer_name' => 'Marta Ruiz', 'customer_phone' => '633 912 050',
    ]);

    $this->get(route('admin.agenda'))
        ->assertSee('href="https://wa.me/34633912050?text=Hola%20Marta%20Ruiz%2C%20te%20escribimos%20de%20Peluquer%C3%ADa%20Jenver%20sobre%20tu%20cita."', false);
});

test('customerWhatsappUrl keeps an already-international phone as is and never breaks on odd input', function () {
    $withPlus = Appointment::factory()->make(['customer_name' => 'X', 'customer_phone' => '+34 633 912 050']);
    expect($withPlus->customerWhatsappUrl())->toStartWith('https://wa.me/34633912050?text=');

    // A number that already carries a country code without "+" is left as is.
    $withoutPlus = Appointment::factory()->make(['customer_name' => 'X', 'customer_phone' => '34633912050']);
    expect($withoutPlus->customerWhatsappUrl())->toStartWith('https://wa.me/34633912050?text=');

    // Odd but valid-per-PhoneNumber input never throws.
    $foreign = Appointment::factory()->make(['customer_name' => 'X', 'customer_phone' => '+1 (555) 123-4567']);
    expect(fn () => $foreign->customerWhatsappUrl())->not->toThrow(Throwable::class);
});

/**
 * Review finding M1 (.ai/reviews/mobile-admin-ux.md): the "00" international
 * dialing prefix (same as "+" in meaning) was not stripped, so a phone
 * written that way produced a broken wa.me link ("wa.me/0034...").
 */
test('customerWhatsappUrl strips the "00" international dialing prefix, with or without spaces', function (string $phone) {
    $appointment = Appointment::factory()->make(['customer_name' => 'X', 'customer_phone' => $phone]);

    expect($appointment->customerWhatsappUrl())->toStartWith('https://wa.me/34633912050?text=');
})->with([
    '0034633912050',
    '00 34 633 912 050',
    '00-34-633-912-050',
]);
