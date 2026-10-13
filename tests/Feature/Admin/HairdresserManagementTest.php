<?php

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Hairdresser;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('the migration creates Jennifer and Isabel, both active', function () {
    expect(Hairdresser::query()->ordered()->get(['name', 'is_active'])->toArray())->toBe([
        ['name' => 'Jennifer', 'is_active' => true],
        ['name' => 'Isabel', 'is_active' => true],
    ]);
});

test('existing appointments and blocks start with no hairdresser', function () {
    $appointment = Appointment::factory()->create();

    expect($appointment->fresh()->hairdresser_id)->toBeNull()
        ->and($appointment->fresh()->hairdresser_requested)->toBeFalse();
});

test('the hairdressers module requires an authenticated user', function () {
    auth()->logout();

    $this->get(route('admin.hairdressers.index'))->assertRedirect(route('login'));
    $this->post(route('admin.hairdressers.store'), ['name' => 'Laura', 'is_active' => '1'])->assertRedirect(route('login'));
    expect(Hairdresser::count())->toBe(2);
});

test('the panel navigation links to the hairdressers module', function () {
    $this->get(route('admin.hairdressers.index'))
        ->assertOk()
        ->assertSee('Peluqueras')
        ->assertSee('Jennifer')
        ->assertSee('Isabel');
});

test('a salon user can create a hairdresser', function () {
    $this->post(route('admin.hairdressers.store'), ['name' => '  Laura ', 'is_active' => '1'])
        ->assertRedirect(route('admin.hairdressers.index'))
        ->assertSessionHas('status');

    $laura = Hairdresser::query()->where('name', 'Laura')->sole();
    expect($laura->is_active)->toBeTrue();
});

test('a salon user can rename a hairdresser', function () {
    $jennifer = Hairdresser::query()->where('name', 'Jennifer')->sole();

    $this->put(route('admin.hairdressers.update', $jennifer), ['name' => 'Jenni', 'is_active' => '1'])
        ->assertRedirect(route('admin.hairdressers.index'));

    expect($jennifer->fresh()->name)->toBe('Jenni');
});

test('a hairdresser name is required, unique and at most 50 characters', function (array $payload) {
    $this->from(route('admin.hairdressers.create'))
        ->post(route('admin.hairdressers.store'), $payload + ['is_active' => '1'])
        ->assertRedirect(route('admin.hairdressers.create'))
        ->assertSessionHasErrors('name');

    expect(Hairdresser::count())->toBe(2);
})->with([
    'empty' => [['name' => '']],
    'taken' => [['name' => 'Isabel']],
    'too long' => [['name' => str_repeat('a', 51)]],
]);

test('renaming a hairdresser to her own name is allowed', function () {
    $isabel = Hairdresser::query()->where('name', 'Isabel')->sole();

    $this->put(route('admin.hairdressers.update', $isabel), ['name' => 'Isabel', 'is_active' => '1'])
        ->assertSessionHasNoErrors();
});

test('a hairdresser with no upcoming assigned appointments can be deactivated', function () {
    $isabel = Hairdresser::query()->where('name', 'Isabel')->sole();
    // A past one and a cancelled one never block it.
    Appointment::factory()->create(['hairdresser_id' => $isabel->id, 'starts_at' => now()->subDay(), 'ends_at' => now()->subDay()->addHour()]);
    Appointment::factory()->create(['hairdresser_id' => $isabel->id, 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour(), 'status' => AppointmentStatus::Cancelled]);

    $this->put(route('admin.hairdressers.update', $isabel), ['name' => 'Isabel', 'is_active' => '0'])
        ->assertSessionHasNoErrors();

    expect($isabel->fresh()->is_active)->toBeFalse();
});

test('a hairdresser with upcoming assigned appointments cannot be deactivated', function () {
    $isabel = Hairdresser::query()->where('name', 'Isabel')->sole();
    $first = CarbonImmutable::parse('2030-01-08 10:00');
    Appointment::factory()->create(['hairdresser_id' => $isabel->id, 'starts_at' => $first, 'ends_at' => $first->addHour()]);
    Appointment::factory()->create(['hairdresser_id' => $isabel->id, 'starts_at' => $first->addDay(), 'ends_at' => $first->addDay()->addHour()]);

    $this->from(route('admin.hairdressers.edit', $isabel))
        ->put(route('admin.hairdressers.update', $isabel), ['name' => 'Isabel', 'is_active' => '0'])
        ->assertRedirect(route('admin.hairdressers.edit', $isabel))
        ->assertSessionHasErrors(['is_active' => 'Isabel tiene 2 citas asignadas a partir de hoy (la primera, el 08/01/2030 a las 10:00). Reasígnalas o cancélalas antes de desactivarla.']);

    expect($isabel->fresh()->is_active)->toBeTrue();
});

test('the hairdressers list shows inactive ones dimmed and has no delete action', function () {
    Hairdresser::query()->where('name', 'Isabel')->update(['is_active' => false]);

    $this->get(route('admin.hairdressers.index'))
        ->assertOk()
        ->assertSee('Inactiva')
        ->assertDontSee('Eliminar');
});
