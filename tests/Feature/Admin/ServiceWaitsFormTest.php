<?php

use App\Actions\CreateAppointment;
use App\Enums\AppointmentSource;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * The service's times are written as steps, in the order they happen:
 * Trabajo 1, Espera 1, Trabajo 2, Espera 2, Trabajo 3 (only Trabajo 1 is
 * required). The total duration is their sum, never typed. Stored as
 * before: duration_minutes and waits ({start, minutes}). Plain words for
 * hairdressers who do not use these tools much (user's request,
 * 2026-10-07). Shown in the panel only, as "incl. N min de espera".
 */
beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

/**
 * @param  array<string, int|string>  $steps  work_1, wait_1, work_2, wait_2, work_3
 * @return array<string, mixed>
 */
function stepsServicePayload(array $steps = ['work_1' => '120'], array $overrides = []): array
{
    return array_merge([
        'name' => 'Coloración',
        'price' => '',
        'is_bookable_online' => '1',
        'is_active' => '1',
        'sort_order' => 1,
    ], ['work_1' => '', 'wait_1' => '', 'work_2' => '', 'wait_2' => '', 'work_3' => ''], $steps, $overrides);
}

test('the form asks for the steps in order, with a short example and no separate duration', function () {
    $this->get(route('admin.services.create'))
        ->assertOk()
        ->assertSeeInOrder(['Trabajo 1', 'Espera 1', 'Trabajo 2', 'Espera 2', 'Trabajo 3', 'Duración total'])
        ->assertSee('Si el servicio no tiene esperas, rellena solo «Trabajo 1».')
        ->assertSee('Ejemplo, un tinte: Trabajo 30 · Espera 45 · Trabajo 45.')
        ->assertSee('La peluquera queda libre')
        ->assertSee('name="work_1"', false)
        ->assertSee('name="wait_1"', false)
        ->assertSee('name="work_2"', false)
        ->assertSee('name="wait_2"', false)
        ->assertSee('name="work_3"', false)
        ->assertDontSee('name="duration_minutes"', false)
        ->assertDontSee('minuto 30')
        ->assertDontSee('tramo');
});

test('the steps are saved as the total duration and the waits', function (array $steps, int $duration, ?array $waits) {
    $this->post(route('admin.services.store'), stepsServicePayload($steps))
        ->assertRedirect(route('admin.services.index'));

    $service = Service::sole();
    expect($service->duration_minutes)->toBe($duration);
    expect($service->waits)->toBe($waits);
})->with([
    'no wait' => [['work_1' => '45'], 45, null],
    'one wait' => [['work_1' => '30', 'wait_1' => '45', 'work_2' => '45'], 120, [['start' => 30, 'minutes' => 45]]],
    'two waits' => [['work_1' => '20', 'wait_1' => '30', 'work_2' => '30', 'wait_2' => '20', 'work_3' => '50'], 150, [['start' => 20, 'minutes' => 30], ['start' => 80, 'minutes' => 20]]],
]);

test('editing a service shows its steps worked out from what is stored', function () {
    $service = Service::factory()->create(['duration_minutes' => 150, 'waits' => [['start' => 20, 'minutes' => 30], ['start' => 80, 'minutes' => 20]]]);

    $this->get(route('admin.services.edit', $service))
        ->assertOk()
        ->assertSee('name="work_1" type="number" inputmode="numeric" min="5" max="600" step="5" value="20"', false)
        ->assertSee('name="wait_1" type="number" inputmode="numeric" min="5" max="600" step="5" value="30"', false)
        ->assertSee('name="work_2" type="number" inputmode="numeric" min="5" max="600" step="5" value="30"', false)
        ->assertSee('name="wait_2" type="number" inputmode="numeric" min="5" max="600" step="5" value="20"', false)
        ->assertSee('name="work_3" type="number" inputmode="numeric" min="5" max="600" step="5" value="50"', false)
        ->assertSee('Duración total: <span id="steps-total">2 h 30 min</span>', false);
});

test('a service without waits shows only Trabajo 1 filled, and its waits can be removed', function () {
    $service = Service::factory()->create(['duration_minutes' => 120, 'waits' => [['start' => 30, 'minutes' => 45]]]);

    $this->put(route('admin.services.update', $service), stepsServicePayload(['work_1' => '90']))
        ->assertRedirect(route('admin.services.index'));

    expect($service->fresh()->duration_minutes)->toBe(90);
    expect($service->fresh()->waits)->toBeNull();

    $this->get(route('admin.services.edit', $service))
        ->assertSee('name="work_1" type="number" inputmode="numeric" min="5" max="600" step="5" value="90"', false)
        ->assertSee('name="wait_1" type="number" inputmode="numeric" min="5" max="600" step="5" value=""', false);
});

test('steps out of place are rejected next to the right field, in plain words, without saving anything', function (array $steps, string $field, string $message) {
    $this->from(route('admin.services.create'))
        ->post(route('admin.services.store'), stepsServicePayload($steps))
        ->assertRedirect(route('admin.services.create'))
        ->assertSessionHasErrors([$field => $message]);

    expect(Service::count())->toBe(0);
})->with([
    'no work at all' => [[], 'work_1', 'Escribe cuántos minutos dura el trabajo 1.'],
    'not a multiple of 5' => [['work_1' => '32'], 'work_1', 'Usa múltiplos de 5 minutos (5, 10, 15…).'],
    'zero minutes' => [['work_1' => '0'], 'work_1', 'Como mínimo, 5 minutos.'],
    'not a number' => [['work_1' => 'media hora'], 'work_1', 'Escribe solo el número de minutos.'],
    'a wait with nothing after it' => [['work_1' => '30', 'wait_1' => '45'], 'work_2', 'Después de una espera tiene que haber un tiempo de trabajo.'],
    'the second wait with nothing after it' => [['work_1' => '30', 'wait_1' => '45', 'work_2' => '30', 'wait_2' => '20'], 'work_3', 'Después de una espera tiene que haber un tiempo de trabajo.'],
    'a second work without a wait before it' => [['work_1' => '30', 'work_2' => '45'], 'wait_1', 'Rellena primero la espera 1.'],
    'the second wait without the first' => [['work_1' => '30', 'wait_2' => '20', 'work_3' => '30'], 'wait_1', 'Rellena primero la espera 1.'],
    'the second wait without a second work' => [['work_1' => '30', 'wait_1' => '45', 'wait_2' => '20', 'work_3' => '30'], 'work_2', 'Rellena primero el trabajo 2.'],
    'a third work without the second wait' => [['work_1' => '30', 'wait_1' => '45', 'work_2' => '30', 'work_3' => '30'], 'wait_2', 'Rellena primero la espera 2.'],
    'a total over 10 hours' => [['work_1' => '300', 'wait_1' => '200', 'work_2' => '200'], 'duration_minutes', 'El servicio entero no puede pasar de 10 horas (600 minutos).'],
]);

test('the form shows the error next to its step', function () {
    $this->from(route('admin.services.create'))
        ->followingRedirects()
        ->post(route('admin.services.store'), stepsServicePayload(['work_1' => '30', 'wait_1' => '45']))
        ->assertSee('Después de una espera tiene que haber un tiempo de trabajo.')
        ->assertSee('id="work_2-error"', false)
        ->assertSee('aria-describedby="work_2-error"', false)
        // What was typed is kept.
        ->assertSee('name="wait_1" type="number" inputmode="numeric" min="5" max="600" step="5" value="45"', false);
});

test('the service list shows the wait included in the duration', function () {
    Service::factory()->create(['name' => 'Coloración', 'duration_minutes' => 120, 'waits' => [['start' => 30, 'minutes' => 45]]]);
    Service::factory()->create(['name' => 'Corte', 'duration_minutes' => 45]);

    $this->get(route('admin.services.index'))
        ->assertOk()
        ->assertSee('2 h, incl. 45 min de espera')
        ->assertSee('45 min · Orden', false)
        ->assertDontSee('45 min, incl.');
});

test('the new appointment form shows each wait and counts it in the total', function () {
    $coloracion = Service::factory()->create(['name' => 'Coloración', 'duration_minutes' => 120, 'waits' => [['start' => 30, 'minutes' => 45]], 'sort_order' => 1]);
    $corte = Service::factory()->create(['name' => 'Corte', 'duration_minutes' => 45, 'sort_order' => 2]);

    $this->get(route('admin.appointments.create', ['fecha' => '2030-01-08', 'servicio' => [$coloracion->id, $corte->id]]))
        ->assertOk()
        ->assertSee('Coloración (2 h, incl. 45 min de espera)')
        ->assertSee('data-wait-minutes="45"', false)
        ->assertSee('Duración total: 2 h 45 min, incl. 45 min de espera');
});

test('the edit form counts the waits the appointment was booked with', function () {
    $this->travelTo(CarbonImmutable::parse('2030-01-07 20:00'));
    $coloracion = Service::factory()->create(['name' => 'Coloración', 'duration_minutes' => 120, 'waits' => [['start' => 30, 'minutes' => 45]]]);
    $appointment = app(CreateAppointment::class)->handle(
        collect([$coloracion]),
        CarbonImmutable::parse('2030-01-08 10:00'),
        ['customer_name' => 'Ana', 'customer_phone' => '600 111 222', 'customer_email' => null, 'notes' => null],
        AppointmentSource::Admin,
        applyPublicRules: false,
    );
    // The service changes afterwards; the appointment keeps its own wait.
    $coloracion->update(['waits' => [['start' => 30, 'minutes' => 20]]]);

    $this->get(route('admin.appointments.edit', $appointment))
        ->assertOk()
        ->assertSee('Coloración (2 h, incl. 20 min de espera)')
        ->assertSee('data-wait-minutes="45"', false)
        ->assertSee('Duración total: 2 h, incl. 45 min de espera');
});

test('the public booking page never shows the waits', function () {
    $this->travelTo(CarbonImmutable::parse('2030-01-07 10:00'));
    $coloracion = Service::factory()->create(['name' => 'Coloración', 'duration_minutes' => 120, 'waits' => [['start' => 30, 'minutes' => 45]]]);

    foreach ([route('reservas'), route('reservas', ['servicio' => $coloracion->id, 'fecha' => '2030-01-08'])] as $url) {
        $html = $this->get($url)->assertOk()->getContent();

        expect($html)->not->toMatch('/\bespera\b/iu')->not->toContain('data-wait-minutes')->not->toContain('45 min');
    }
});
