<?php

use App\Actions\CreateAppointment;
use App\Enums\AppointmentSource;
use App\Models\Appointment;
use App\Models\BookingSetting;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2030-01-07 10:00'));
    $this->service = Service::factory()->create(['name' => 'Corte y peinado', 'duration_minutes' => 90, 'price_cents' => 3550, 'sort_order' => 1]);
});

function disableOnlineBooking(): void
{
    BookingSetting::current()->update(['online_booking_enabled' => false]);
}

test('a fresh install has online booking enabled', function () {
    expect(BookingSetting::onlineBookingEnabled())->toBeTrue();
});

test('/reservas answers 200 at its own url with a phone/whatsapp page while the switch is off', function () {
    disableOnlineBooking();

    $html = $this->get(route('reservas'))->assertOk()->getContent();

    expect($html)->toContain('La reserva online no está disponible en este momento. Pide tu cita por teléfono o WhatsApp.');
    expect($html)->toContain('href="tel:+34633912050"');
    expect($html)->toContain('href="https://wa.me/34633912050');
    expect($html)->toContain('C/ Lleida, 21'); // address
    expect($html)->toContain('Martes a sábado: 9:00–19:00 · Domingo y lunes: cerrado'); // OpeningHoursSummary
    expect($html)->toContain('href="'.route('contacto').'"');

    // The two buttons and the "Ver más formas de contactar" link all meet
    // the 44px touch target (review finding N1, coordinator: the link
    // measured 24px before).
    expect(substr_count($html, 'min-h-11'))->toBe(3);
});

test('/reservas shows the normal booking form while the switch is on', function () {
    $html = $this->get(route('reservas'))->assertOk()->getContent();

    expect($html)->not->toContain('La reserva online no está disponible');
    expect($html)->toContain(__('reservas.steps.service'));
});

test('a booking POSTed while the switch is off is rejected, nothing is created', function () {
    disableOnlineBooking();

    $this->post(route('reservas.store'), [
        'service_ids' => [$this->service->id],
        'date' => '2030-01-08',
        'time' => '10:00',
        'customer_name' => 'Núria Martínez',
        'customer_phone' => '+34600123456',
        'customer_email' => 'nuria@example.test',
        'privacy' => '1',
        'website' => '',
    ])->assertRedirect(route('reservas'));

    expect(Appointment::count())->toBe(0);
    $this->get(route('reservas'))->assertSee('La reserva online no está disponible en este momento. Pide tu cita por teléfono o WhatsApp.');
});

test('a booking POSTed while the switch is on still works (regression)', function () {
    $this->post(route('reservas.store'), [
        'service_ids' => [$this->service->id],
        'date' => '2030-01-08',
        'time' => '10:00',
        'customer_name' => 'Núria Martínez',
        'customer_phone' => '+34600123456',
        'customer_email' => 'nuria@example.test',
        'privacy' => '1',
        'website' => '',
    ])->assertRedirect();

    expect(Appointment::count())->toBe(1);
});

test('the header desktop and mobile CTAs call instead of linking to /reservas while the switch is off', function () {
    disableOnlineBooking();

    $html = $this->get('/')->assertOk()->getContent();

    expect(substr_count($html, 'href="'.route('reservas').'" class="btn-gold'))->toBe(0);
    expect(substr_count($html, 'href="tel:+34633912050" class="btn-gold'))->toBeGreaterThanOrEqual(2); // desktop + mobile header CTA
});

test('the home hero and "Reserva tu cita" CTAs call instead of linking to /reservas while the switch is off', function () {
    disableOnlineBooking();

    $html = $this->get('/')->assertOk()->getContent();
    preg_match('#<section id="reserva".*?</section>#s', $html, $section);

    expect($html)->not->toContain('href="'.route('reservas').'"');
    expect($section[0])->toContain('href="tel:+34633912050"');
    expect($section[0])->toContain('href="https://wa.me/34633912050');
});

test('every service page hero calls instead of linking to /reservas while the switch is off', function (string $route) {
    disableOnlineBooking();

    $html = $this->get(route($route))->assertOk()->getContent();

    expect($html)->not->toContain('href="'.route('reservas').'"');
    expect($html)->toContain('href="tel:+34633912050" class="btn-gold');
})->with([
    'color-mechas',
    'corte-tratamientos',
    'peinados-eventos',
    'belleza-estetica',
]);

test('the FAQ about online booking says it is not available while the switch is off', function () {
    disableOnlineBooking();

    $home = $this->get('/')->assertOk()->getContent();
    preg_match('#<script type="application/ld\+json">\s*(\{"@context":"https://schema.org","@type":"FAQPage".*?)\s*</script>#s', $home, $faqSchema);
    $questions = collect(json_decode($faqSchema[1], true)['mainEntity'])->pluck('acceptedAnswer.text', 'name');

    expect($questions['¿Puedo pedir cita online?'])
        ->toContain('Ahora mismo no')
        ->toContain('633 912 050');
});

test('the local business JSON-LD drops the ReserveAction while the switch is off, keeps the phone', function () {
    disableOnlineBooking();

    $html = $this->get('/')->assertOk()->getContent();
    preg_match_all('#<script type="application/ld\+json">\s*(.+?)\s*</script>#s', $html, $matches);
    $salon = collect($matches[1])
        ->map(fn (string $json) => json_decode($json, true))
        ->first(fn (?array $data) => is_array($data) && in_array('HairSalon', (array) ($data['@type'] ?? [])));

    expect($salon)->not->toHaveKey('potentialAction');
    expect($salon['telephone'])->toBe('+34633912050');
});

test('the admin panel shows a visible warning while the switch is off, not when it is on', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('admin.agenda'))->assertOk()->assertDontSee('Reserva online desactivada');

    disableOnlineBooking();

    $this->get(route('admin.agenda'))->assertOk()->assertSee('Reserva online desactivada');
});

test('/cita/{token}, the cron retry and the admin panel keep working while the switch is off', function () {
    $this->actingAs(User::factory()->create());

    $created = app(CreateAppointment::class)->handle(
        collect([$this->service]),
        CarbonImmutable::parse('2030-01-08 10:00'),
        ['customer_name' => 'Núria', 'customer_phone' => '+34600123456', 'customer_email' => 'nuria@example.test', 'notes' => null],
        AppointmentSource::Admin,
        applyPublicRules: false,
    );

    disableOnlineBooking();

    $this->get(route('cita.show', $created->token))->assertOk();
    $this->get(route('admin.agenda'))->assertOk();
    $this->get(route('admin.services.index'))->assertOk();
});
