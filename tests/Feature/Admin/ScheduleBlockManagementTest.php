<?php

use App\Booking\AvailabilityCalculator;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\BookingSetting;
use App\Models\ScheduleBlock;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->travelTo(CarbonImmutable::parse('2030-01-07 10:00'));
});

test('a salon user can create a full closure and a partial capacity reduction', function () {
    $this->post(route('admin.blocks.store'), [
        'starts_at' => '2030-01-08T00:00',
        'ends_at' => '2030-01-09T00:00',
        'capacity_reduction' => '',
        'reason' => 'Festivo local',
    ])->assertRedirect(route('admin.blocks.index'));

    $this->post(route('admin.blocks.store'), [
        'starts_at' => '2030-01-10T09:00',
        'ends_at' => '2030-01-12T19:00',
        'capacity_reduction' => 1,
        'reason' => 'Vacaciones de una peluquera',
    ])->assertRedirect(route('admin.blocks.index'));

    $blocks = ScheduleBlock::query()->orderBy('starts_at')->get();
    expect($blocks)->toHaveCount(2);
    expect($blocks[0]->isFullClosure())->toBeTrue();
    expect($blocks[0]->reason)->toBe('Festivo local');
    expect($blocks[1]->capacity_reduction)->toBe(1);
});

test('invalid closures are rejected without saving', function (array $payload, string $field) {
    $this->from(route('admin.blocks.index'))
        ->post(route('admin.blocks.store'), array_merge([
            'starts_at' => '2030-01-08T09:00',
            'ends_at' => '2030-01-08T13:00',
            'capacity_reduction' => '',
            'reason' => '',
        ], $payload))
        ->assertSessionHasErrors($field);

    expect(ScheduleBlock::count())->toBe(0);
})->with([
    'end before start' => [['ends_at' => '2030-01-08T08:00'], 'ends_at'],
    'end equal to start' => [['ends_at' => '2030-01-08T09:00'], 'ends_at'],
    'missing start' => [['starts_at' => ''], 'starts_at'],
    'reduction of zero' => [['capacity_reduction' => 0], 'capacity_reduction'],
    'reduction over 10' => [['capacity_reduction' => 11], 'capacity_reduction'],
    'reason over 150 characters' => [['reason' => str_repeat('a', 151)], 'reason'],
]);

test('a closure over confirmed appointments warns and does not cancel them', function () {
    $appointments = collect([
        Appointment::factory()->create(['starts_at' => '2030-01-08 10:00', 'ends_at' => '2030-01-08 11:00']),
        Appointment::factory()->create(['starts_at' => '2030-01-08 12:00', 'ends_at' => '2030-01-08 13:00']),
        Appointment::factory()->cancelled()->create(['starts_at' => '2030-01-08 12:00', 'ends_at' => '2030-01-08 13:00']),
    ]);

    $this->post(route('admin.blocks.store'), [
        'starts_at' => '2030-01-08T00:00',
        'ends_at' => '2030-01-09T00:00',
        'capacity_reduction' => '',
    ])->assertSessionHas('warning', 'Hay 2 citas confirmadas en ese periodo. No se han cancelado: revísalas en la agenda.');

    expect($appointments[0]->fresh()->status)->toBe(AppointmentStatus::Confirmed);
    expect($appointments[1]->fresh()->status)->toBe(AppointmentStatus::Confirmed);
});

test('the warning uses the singular for a single affected appointment', function () {
    Appointment::factory()->create(['starts_at' => '2030-01-08 10:00', 'ends_at' => '2030-01-08 11:00']);

    $this->post(route('admin.blocks.store'), [
        'starts_at' => '2030-01-08T09:00',
        'ends_at' => '2030-01-08T12:00',
        'capacity_reduction' => 1,
    ])->assertSessionHas('warning', 'Hay 1 cita confirmada en ese periodo. No se ha cancelado: revísala en la agenda.');
});

test('the list shows only closures that have not ended, ordered by start', function () {
    ScheduleBlock::create(['starts_at' => '2030-01-01 00:00', 'ends_at' => '2030-01-02 00:00', 'reason' => 'Pasado']);
    ScheduleBlock::create(['starts_at' => '2030-02-01 00:00', 'ends_at' => '2030-02-02 00:00', 'reason' => 'Febrero']);
    ScheduleBlock::create(['starts_at' => '2030-01-20 00:00', 'ends_at' => '2030-01-21 00:00', 'reason' => 'Enero']);

    $this->get(route('admin.blocks.index'))
        ->assertOk()
        ->assertDontSee('Pasado')
        ->assertSeeInOrder(['Enero', 'Febrero']);
});

test('without future closures the list says so', function () {
    $this->get(route('admin.blocks.index'))->assertSee('No hay cierres previstos.');
});

test('deleting a closure frees its times again', function () {
    BookingSetting::current()->update(['min_notice_minutes' => 0]);
    $block = ScheduleBlock::create(['starts_at' => '2030-01-08 00:00', 'ends_at' => '2030-01-09 00:00']);
    $calculator = app(AvailabilityCalculator::class);
    $day = CarbonImmutable::parse('2030-01-08');

    expect($calculator->availableStartTimes(30, $day, now()->toImmutable()))->toBe([]);

    $this->delete(route('admin.blocks.destroy', $block))->assertRedirect(route('admin.blocks.index'));

    expect(ScheduleBlock::count())->toBe(0);
    expect($calculator->availableStartTimes(30, $day, now()->toImmutable()))->not->toBe([]);
});

/**
 * Review finding N3 (.ai/reviews/mobile-admin-ux.md, coordinator): the
 * datetime-local pickers (and every other field, sharing the same input
 * class) meet the 44px touch target.
 */
test('the closure form fields meet the 44px touch target', function () {
    $this->get(route('admin.blocks.index'))->assertSee('px-3 py-3 focus:border-gold', false);
});
