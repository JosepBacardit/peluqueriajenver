<?php

use App\Actions\CreateAppointment;
use App\Enums\AppointmentSource;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * Up to 2 waits per service, set from the panel's service form: "from
 * minute X, for Y minutes", always between two active stretches. Shown in
 * the panel only, as "incl. N min de espera".
 */
beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

/**
 * @return array<string, mixed>
 */
function waitServicePayload(array $waits = [], array $overrides = []): array
{
    return array_merge([
        'name' => 'Coloración',
        'duration_minutes' => 120,
        'price' => '',
        'is_bookable_online' => '1',
        'is_active' => '1',
        'sort_order' => 1,
        'waits' => $waits + [0 => ['start' => '', 'minutes' => ''], 1 => ['start' => '', 'minutes' => '']],
    ], $overrides);
}

test('the service form offers two optional waits', function () {
    $this->get(route('admin.services.create'))
        ->assertOk()
        ->assertSee('name="waits[0][start]"', false)
        ->assertSee('name="waits[0][minutes]"', false)
        ->assertSee('name="waits[1][start]"', false)
        ->assertSee('name="waits[1][minutes]"', false)
        ->assertSee('Espera 1')
        ->assertSee('Espera 2');
});

test('a service is saved with one, two or no waits', function (array $waits, ?array $expected) {
    $this->post(route('admin.services.store'), waitServicePayload($waits))
        ->assertRedirect(route('admin.services.index'));

    expect(Service::sole()->waits)->toBe($expected);
})->with([
    'none' => [[], null],
    'one' => [[0 => ['start' => '30', 'minutes' => '45']], [['start' => 30, 'minutes' => 45]]],
    'two' => [[0 => ['start' => '20', 'minutes' => '30'], 1 => ['start' => '80', 'minutes' => '20']], [['start' => 20, 'minutes' => 30], ['start' => 80, 'minutes' => 20]]],
    'only the second row filled' => [[1 => ['start' => '30', 'minutes' => '45']], [['start' => 30, 'minutes' => 45]]],
]);

test('the edit form shows the waits and they can be removed', function () {
    $service = Service::factory()->create(['duration_minutes' => 120, 'waits' => [['start' => 30, 'minutes' => 45]]]);

    $this->get(route('admin.services.edit', $service))
        ->assertOk()
        ->assertSee('name="waits[0][start]" type="number" min="5" max="595" step="5" value="30"', false)
        ->assertSee('name="waits[0][minutes]" type="number" min="5" max="590" step="5" value="45"', false);

    $this->put(route('admin.services.update', $service), waitServicePayload())
        ->assertRedirect(route('admin.services.index'));

    expect($service->fresh()->waits)->toBeNull();
});

test('a wait out of place is rejected next to its field without saving anything', function (array $waits, string $field, array $overrides = []) {
    $this->from(route('admin.services.create'))
        ->post(route('admin.services.store'), waitServicePayload($waits, $overrides))
        ->assertRedirect(route('admin.services.create'))
        ->assertSessionHasErrors($field);

    expect(Service::count())->toBe(0);
})->with([
    'starting at minute 0' => [[0 => ['start' => '0', 'minutes' => '30']], 'waits.0.start'],
    'start not a multiple of 5' => [[0 => ['start' => '32', 'minutes' => '30']], 'waits.0.start'],
    'length not a multiple of 5' => [[0 => ['start' => '30', 'minutes' => '31']], 'waits.0.minutes'],
    'start without a length' => [[0 => ['start' => '30', 'minutes' => '']], 'waits.0.minutes'],
    'length without a start' => [[0 => ['start' => '', 'minutes' => '30']], 'waits.0.start'],
    'running to the end of the service' => [[0 => ['start' => '90', 'minutes' => '30']], 'waits.0.minutes'],
    'running past the end of the service' => [[0 => ['start' => '100', 'minutes' => '45']], 'waits.0.minutes'],
    'a shorter duration leaves the wait at the end' => [[0 => ['start' => '30', 'minutes' => '45']], 'waits.0.minutes', ['duration_minutes' => 75]],
    'the second one overlapping the first' => [[0 => ['start' => '20', 'minutes' => '30'], 1 => ['start' => '40', 'minutes' => '10']], 'waits.1.start'],
    'the second one right after the first' => [[0 => ['start' => '20', 'minutes' => '30'], 1 => ['start' => '50', 'minutes' => '10']], 'waits.1.start'],
    'the second one before the first' => [[0 => ['start' => '60', 'minutes' => '20'], 1 => ['start' => '20', 'minutes' => '10']], 'waits.1.start'],
]);

test('the form shows a wait error next to its field', function () {
    $this->from(route('admin.services.create'))
        ->followingRedirects()
        ->post(route('admin.services.store'), waitServicePayload([0 => ['start' => '90', 'minutes' => '30']]))
        ->assertSee('La espera 1 tiene que terminar antes del final del servicio.')
        ->assertSee('id="waits-0-minutes-error"', false)
        ->assertSee('aria-describedby="waits-0-minutes-error"', false);
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

        expect($html)->not->toContain('espera')->not->toContain('data-wait-minutes')->not->toContain('45 min');
    }
});
