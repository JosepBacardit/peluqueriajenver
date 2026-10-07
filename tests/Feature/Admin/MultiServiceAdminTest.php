<?php

use App\Models\Appointment;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

/*
 * PRF-129: creating, editing and moving a panel appointment with several
 * services. "Now" is Tuesday 2030-01-08 08:00; the salon opens 09:00-19:00
 * by the default schedule, capacity 2.
 */
beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->travelTo(CarbonImmutable::parse('2030-01-08 08:00'));
    $this->haircut = Service::factory()->create(['name' => 'Corte', 'duration_minutes' => 30]);
    $this->beard = Service::factory()->create(['name' => 'Barba', 'duration_minutes' => 15]);
    $this->color = Service::factory()->create(['name' => 'Color', 'duration_minutes' => 60]);
});

/**
 * @return array<string, mixed>
 */
function multiAdminPayload(array $overrides = []): array
{
    return array_merge([
        'service_ids' => [test()->haircut->id, test()->beard->id],
        'date' => '2030-01-08',
        'time' => '10:00',
        'customer_name' => 'Rosa Vidal',
        'customer_phone' => '600 123 456',
        'customer_email' => '',
        'notes' => null,
    ], $overrides);
}

test('creating a panel appointment with two services books both in the salon\'s order with the sum as its length', function () {
    $this->post(route('admin.appointments.store'), multiAdminPayload())
        ->assertRedirect(route('admin.agenda', ['fecha' => '2030-01-08']));

    $appointment = Appointment::sole();
    // Same "Orden": creation order (Corte was created first), not the name (review finding L2).
    expect($appointment->items->pluck('service_name')->all())->toBe(['Corte', 'Barba']);
    expect($appointment->durationMinutes())->toBe(45);
    expect($appointment->ends_at->format('H:i'))->toBe('10:45');
});

test('more than the maximum number of services is rejected when creating a panel appointment', function () {
    $extra = Service::factory()->count(4)->create();
    $ids = $extra->pluck('id')->push($this->haircut->id)->push($this->beard->id)->push($this->color->id)->all(); // 7 total

    $this->post(route('admin.appointments.store'), multiAdminPayload(['service_ids' => $ids]))
        ->assertSessionHasErrors('service_ids');

    expect(Appointment::count())->toBe(0);
});

test('a repeated service id is rejected when creating a panel appointment', function () {
    $this->post(route('admin.appointments.store'), multiAdminPayload(['service_ids' => [$this->haircut->id, $this->haircut->id]]))
        ->assertSessionHasErrors('service_ids.0');

    expect(Appointment::count())->toBe(0);
});

test('editing an appointment to drop one service and add another keeps the kept one\'s frozen data', function () {
    $appointment = Appointment::factory()->withServices($this->haircut, $this->beard)->create([
        'starts_at' => '2030-01-08 10:00', 'ends_at' => '2030-01-08 10:45',
    ]);
    // The salon renames/lengthens "Corte" after booking: the kept item must
    // not pick up the change (PRF-126).
    $this->haircut->update(['name' => 'Corte renombrado', 'duration_minutes' => 999]);

    $this->put(route('admin.appointments.update', $appointment), [
        'service_ids' => [$this->haircut->id, $this->color->id], // drop Barba, add Color
        'date' => '2030-01-08', 'time' => '10:00',
        'customer_name' => 'Rosa Vidal', 'customer_phone' => '600 123 456', 'customer_email' => '',
        'version' => $appointment->updated_at->getTimestamp(),
    ])->assertSessionHasNoErrors();

    $appointment->refresh();
    expect($appointment->items->pluck('service_name')->all())->toBe(['Corte', 'Color']); // Corte kept its frozen name
    expect($appointment->items->pluck('duration_minutes')->all())->toBe([30, 60]); // and its frozen duration
    expect($appointment->durationMinutes())->toBe(90);
});

test('a deactivated service the appointment already has is accepted when editing; another one is not', function () {
    $appointment = Appointment::factory()->withServices($this->haircut, $this->beard)->create([
        'starts_at' => '2030-01-08 10:00', 'ends_at' => '2030-01-08 10:45',
    ]);
    $this->haircut->update(['is_active' => false]);
    $retired = Service::factory()->inactive()->create();

    // Keeping its own deactivated service is fine.
    $this->put(route('admin.appointments.update', $appointment), [
        'service_ids' => [$this->haircut->id, $this->beard->id],
        'date' => '2030-01-08', 'time' => '10:00',
        'customer_name' => 'Rosa Vidal', 'customer_phone' => '600 123 456', 'customer_email' => '',
        'version' => $appointment->updated_at->getTimestamp(),
    ])->assertSessionHasNoErrors();

    // Adding a different, unrelated deactivated service is not.
    $this->put(route('admin.appointments.update', $appointment), [
        'service_ids' => [$this->haircut->id, $retired->id],
        'date' => '2030-01-08', 'time' => '10:00',
        'customer_name' => 'Rosa Vidal', 'customer_phone' => '600 123 456', 'customer_email' => '',
        'version' => $appointment->fresh()->updated_at->getTimestamp(),
    ])->assertSessionHasErrors('service_ids.1');
});

test('the edit form marks every service the appointment already has, not just the first', function () {
    $appointment = Appointment::factory()->withServices($this->haircut, $this->beard)->create([
        'starts_at' => '2030-01-08 10:00', 'ends_at' => '2030-01-08 10:45',
    ]);

    $html = $this->get(route('admin.appointments.edit', $appointment))->assertOk()->getContent();

    expect($html)->toMatch('/name="service_ids\[\]" value="'.$this->haircut->id.'"[^>]*checked/');
    expect($html)->toMatch('/name="service_ids\[\]" value="'.$this->beard->id.'"[^>]*checked/');
});

/**
 * UpdateAdminAppointmentRequest::slotKey() must identify the same set of
 * services regardless of the order "service_ids[]" arrives in, so the
 * "save anyway" button (built from the slotKey shown when the warning was
 * rendered) still matches a resubmission that lists the same two services
 * in a different order.
 */
test('"save anyway" still matches when the services are resubmitted in a different order', function () {
    $appointment = Appointment::factory()->withServices($this->haircut, $this->beard)->create([
        'starts_at' => '2030-01-08 10:00', 'ends_at' => '2030-01-08 10:45',
    ]);
    Appointment::factory()->count(2)->create(['starts_at' => '2030-01-08 12:00', 'ends_at' => '2030-01-08 12:45']);
    $editUrl = route('admin.appointments.edit', $appointment);

    $payload = [
        'service_ids' => [$this->beard->id, $this->haircut->id], // reversed order
        'date' => '2030-01-08', 'time' => '12:00', // full at this time
        'customer_name' => 'Rosa Vidal', 'customer_phone' => '600 123 456', 'customer_email' => '',
        'version' => $appointment->updated_at->getTimestamp(),
    ];
    $this->from($editUrl)->put(route('admin.appointments.update', $appointment), $payload);
    preg_match('/name="force" value="([^"]+)"/', $this->get($editUrl)->getContent(), $button);

    // Resubmitted with the services in the original order, plus the force
    // button's value: still recognised as the same confirmed slot.
    $this->put(route('admin.appointments.update', $appointment), [
        'service_ids' => [$this->haircut->id, $this->beard->id],
        'date' => '2030-01-08', 'time' => '12:00',
        'customer_name' => 'Rosa Vidal', 'customer_phone' => '600 123 456', 'customer_email' => '',
        'version' => $appointment->updated_at->getTimestamp(),
        'force' => html_entity_decode($button[1]),
    ])->assertSessionHasNoErrors();

    expect($appointment->fresh()->starts_at->format('H:i'))->toBe('12:00');
});

test('a refused list of services is announced on the checkboxes and points to one message that exists', function (Closure $serviceIds) {
    $this->from(route('admin.appointments.create'))
        ->post(route('admin.appointments.store'), multiAdminPayload(['service_ids' => $serviceIds()]));
    $html = $this->get(route('admin.appointments.create'))->getContent();

    expect($html)->toMatch('/<fieldset id="service_ids"\s+aria-describedby="service_ids-error"/');
    expect(substr_count($html, 'id="service_ids-error"'))->toBe(1);
    expect($html)->toMatch('/name="service_ids\[\]" value="'.$this->haircut->id.'"[^>]*aria-invalid="true"/');
})->with([
    'an item error (repeated)' => [fn () => [test()->haircut->id, test()->haircut->id]],
    'an item error (inactive)' => [fn () => [Service::factory()->inactive()->create()->id]],
    'a list error (none)' => [fn () => []],
]);

test('the create form shows the total length of the chosen services, live and without JavaScript', function () {
    $html = $this->get(route('admin.appointments.create', ['servicio' => [$this->haircut->id, $this->beard->id]]))->assertOk()->getContent();

    expect($html)->toContain('id="service-total"');
    expect($html)->toContain('Duración total: 45 min');
    expect($html)->toContain('document.getElementById("service-total")');
    expect($html)->toMatch('/class="service-checkbox[^"]*" data-minutes="30"/');
});

test('after a validation error the create form shows the total of what was checked', function () {
    $this->from(route('admin.appointments.create'))
        ->post(route('admin.appointments.store'), multiAdminPayload(['service_ids' => [$this->haircut->id, $this->color->id], 'customer_name' => '']));

    $this->get(route('admin.appointments.create'))->assertSee('Duración total: 1 h 30 min');
});

test('the edit form totals the services the way the move counts them, with each kept service\'s booked length', function () {
    $appointment = Appointment::factory()->withServices($this->haircut, $this->beard)->create(['starts_at' => '2030-01-08 10:00']);
    $this->haircut->update(['duration_minutes' => 90]);

    $html = $this->get(route('admin.appointments.edit', $appointment))->assertOk()->getContent();

    expect($html)->toContain('Duración total: 45 min');
    expect($html)->toMatch('/value="'.$this->haircut->id.'" class="service-checkbox[^"]*" data-minutes="30"/');
});

test('saving the same services after the catalogue was reordered sends no email and changes nothing', function () {
    Mail::fake();
    $appointment = Appointment::factory()->withServices($this->haircut, $this->beard)->create(['starts_at' => '2030-01-08 10:00', 'customer_email' => 'rosa@example.test', 'customer_notified_at' => now()]);
    $itemIds = $appointment->items()->pluck('id')->all();
    $this->beard->update(['sort_order' => -1]);

    $this->put(route('admin.appointments.update', $appointment), [
        'service_ids' => [$this->beard->id, $this->haircut->id],
        'date' => '2030-01-08', 'time' => '10:00',
        'customer_name' => 'Rosa Vidal', 'customer_phone' => '611 222 333', 'customer_email' => 'rosa@example.test',
        'version' => $appointment->updated_at->getTimestamp(),
    ])->assertSessionHas('status', 'Cita actualizada.');

    Mail::assertNothingSent();
    expect($appointment->items()->pluck('id')->all())->toBe($itemIds);
    expect($appointment->fresh()->customer_phone)->toBe('611 222 333');
});
