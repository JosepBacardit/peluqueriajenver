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
