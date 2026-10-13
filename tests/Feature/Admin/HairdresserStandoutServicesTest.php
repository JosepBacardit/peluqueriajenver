<?php

use App\Models\Appointment;
use App\Models\Hairdresser;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * What each hairdresser "destaca en" (PRF-173): shown to the customer on
 * /reservas, suggested in the panel, never blocking anything. "Now" is
 * Monday 2030-01-07 10:00; Tuesday 2030-01-08 is open 09:00-19:00.
 */
beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2030-01-07 10:00'));
    $this->balayage = Service::factory()->create(['name' => 'Balayage', 'duration_minutes' => 60, 'sort_order' => 1]);
    $this->afro = Service::factory()->create(['name' => 'Afro y rizos', 'duration_minutes' => 60, 'sort_order' => 2]);
    $this->corte = Service::factory()->create(['name' => 'Corte', 'duration_minutes' => 60, 'sort_order' => 3]);
    $this->jennifer = Hairdresser::query()->where('name', 'Jennifer')->sole();
    $this->isabel = Hairdresser::query()->where('name', 'Isabel')->sole();
});

test('the salon marks the services a hairdresser stands out in', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('admin.hairdressers.edit', $this->isabel))
        ->assertOk()
        ->assertSee('Destaca en')
        ->assertSee('Balayage');

    $this->put(route('admin.hairdressers.update', $this->isabel), ['name' => 'Isabel', 'is_active' => '1', 'service_ids' => [$this->balayage->id, $this->afro->id]])
        ->assertSessionHasNoErrors();

    expect($this->isabel->standoutServices()->pluck('services.id')->sort()->values()->all())->toBe([$this->balayage->id, $this->afro->id]);

    $this->put(route('admin.hairdressers.update', $this->isabel), ['name' => 'Isabel', 'is_active' => '1'])
        ->assertSessionHasNoErrors();

    expect($this->isabel->standoutServices()->count())->toBe(0);
});

test('only active services can be marked', function () {
    $this->actingAs(User::factory()->create());
    $retired = Service::factory()->inactive()->create(['name' => 'Retirado']);

    $this->from(route('admin.hairdressers.edit', $this->isabel))
        ->put(route('admin.hairdressers.update', $this->isabel), ['name' => 'Isabel', 'is_active' => '1', 'service_ids' => [$retired->id]])
        ->assertSessionHasErrors('service_ids.0');
});

test('the booking page says what each hairdresser stands out in, only for services bookable online', function () {
    $phoneOnly = Service::factory()->notBookableOnline()->create(['name' => 'Recogido de novia']);
    $this->isabel->standoutServices()->attach([$this->balayage->id, $this->afro->id, $phoneOnly->id]);

    $this->get(route('reservas'))
        ->assertOk()
        ->assertSeeInOrder(['Isabel', 'Destaca en: Balayage, Afro y rizos'])
        ->assertDontSee('Recogido de novia');
});

test('standing out never changes the times offered', function () {
    $this->isabel->standoutServices()->attach($this->balayage->id);

    $jennifer = $this->get(route('reservas', ['servicio' => $this->balayage->id, 'peluquera' => $this->jennifer->id, 'fecha' => '2030-01-08']))->getContent();
    $isabel = $this->get(route('reservas', ['servicio' => $this->balayage->id, 'peluquera' => $this->isabel->id, 'fecha' => '2030-01-08']))->getContent();

    preg_match_all('/name="time" value="([^"]+)"/', $jennifer, $jenniferTimes);
    preg_match_all('/name="time" value="([^"]+)"/', $isabel, $isabelTimes);
    expect($jenniferTimes[1])->not->toBe([])->toBe($isabelTimes[1]);
});

test('the panel marks the hairdressers who stand out in the appointment\'s services', function () {
    $this->actingAs(User::factory()->create());
    $this->isabel->standoutServices()->attach($this->balayage->id);
    $appointment = Appointment::factory()->withServices($this->balayage)->create(['starts_at' => '2030-01-08 10:00']);

    $this->get(route('admin.appointments.edit', $appointment))
        ->assertOk()
        ->assertSee('Isabel (destaca)')
        ->assertDontSee('Jennifer (destaca)');

    $this->get(route('admin.appointments.create', ['servicio' => $this->balayage->id]))
        ->assertSee('Isabel (destaca)');

    $this->get(route('admin.appointments.create', ['servicio' => $this->corte->id]))
        ->assertDontSee('(destaca)');
});

test('the agenda draws an unassigned appointment in the column of a free hairdresser who stands out in it', function () {
    $this->actingAs(User::factory()->create());
    $this->isabel->standoutServices()->attach($this->balayage->id);
    Appointment::factory()->withServices($this->balayage)->create(['starts_at' => '2030-01-08 10:00', 'customer_name' => 'Rosa Vidal']);

    $this->get(route('admin.agenda', ['fecha' => '2030-01-08']))
        ->assertSee('Rosa Vidal, cualquier peluquera, columna de Isabel', false);
});
