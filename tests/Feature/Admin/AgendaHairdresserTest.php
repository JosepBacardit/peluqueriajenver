<?php

use App\Booking\AppointmentLaneAssigner;
use App\Models\Appointment;
use App\Models\Hairdresser;
use App\Models\ScheduleBlock;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * The agenda by hairdresser (PRF-171) and its hairdresser filter
 * (PRF-172). "Now" is Monday 2030-01-07 10:00; Tuesday 2030-01-08 is open
 * 09:00-19:00; Jennifer (column 1) and Isabel (column 2) are active.
 */
beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->travelTo(CarbonImmutable::parse('2030-01-07 10:00'));
    $this->jennifer = Hairdresser::query()->where('name', 'Jennifer')->sole();
    $this->isabel = Hairdresser::query()->where('name', 'Isabel')->sole();
});

function agendaAppointment(string $from, string $to, ?Hairdresser $hairdresser = null, array $attributes = []): Appointment
{
    return Appointment::factory()->create(array_merge([
        'starts_at' => "2030-01-08 {$from}",
        'ends_at' => "2030-01-08 {$to}",
        'hairdresser_id' => $hairdresser?->id,
    ], $attributes));
}

test('an appointment with a hairdresser goes in her lane, and one without in a free lane', function () {
    $isabels = agendaAppointment('10:00', '11:00', $this->isabel);
    $anyone = agendaAppointment('10:00', '11:00');
    $map = [$this->jennifer->id => 0, $this->isabel->id => 1];

    $result = AppointmentLaneAssigner::assign(collect([$anyone, $isabels]), 2, $map);

    expect($result['lanes'][AppointmentLaneAssigner::key($isabels, 0)])->toBe(1)
        ->and($result['lanes'][AppointmentLaneAssigner::key($anyone, 0)])->toBe(0)
        ->and($result['maxLanes'])->toBe(2);
});

test('an unassigned appointment stays out of a lane that has one of hers later on, or whose hairdresser is away', function () {
    $anyone = agendaAppointment('10:00', '11:00');
    $jennifers = agendaAppointment('10:30', '11:30', $this->jennifer);
    $map = [$this->jennifer->id => 0, $this->isabel->id => 1];

    $result = AppointmentLaneAssigner::assign(collect([$anyone, $jennifers]), 2, $map);
    expect($result['lanes'][AppointmentLaneAssigner::key($anyone, 0)])->toBe(1);

    $away = [1 => [[CarbonImmutable::parse('2030-01-08 09:00'), CarbonImmutable::parse('2030-01-08 19:00')]]];
    $result = AppointmentLaneAssigner::assign(collect([$anyone]), 2, $map, $away);
    expect($result['lanes'][AppointmentLaneAssigner::key($anyone, 0)])->toBe(0);
});

test('a second appointment of the same hairdresser at the same time goes to an extra lane, over capacity', function () {
    $first = agendaAppointment('10:00', '11:00', $this->jennifer);
    $second = agendaAppointment('10:00', '11:00', $this->jennifer);
    $third = agendaAppointment('10:00', '11:00', $this->isabel);
    $map = [$this->jennifer->id => 0, $this->isabel->id => 1];

    $result = AppointmentLaneAssigner::assign(collect([$first, $second, $third]), 2, $map);

    expect($result['lanes'][AppointmentLaneAssigner::key($first, 0)])->toBe(0)
        ->and($result['lanes'][AppointmentLaneAssigner::key($third, 0)])->toBe(1)
        ->and($result['lanes'][AppointmentLaneAssigner::key($second, 0)])->toBe(2)
        ->and($result['overCapacity'][AppointmentLaneAssigner::key($second, 0)])->toBeTrue();
});

test('the day view has one column per active hairdresser, named, and marks unassigned appointments', function () {
    Hairdresser::factory()->inactive()->create(['name' => 'Laura']);
    agendaAppointment('10:00', '11:00', $this->isabel, ['customer_name' => 'Rosa Vidal', 'services_label' => 'Corte']);
    agendaAppointment('12:00', '13:00', null, ['customer_name' => 'Marta Ruiz', 'services_label' => 'Peinado']);

    $html = $this->get(route('admin.agenda', ['fecha' => '2030-01-08']))->assertOk()->getContent();

    expect($html)->toContain('>Jennifer<')->toContain('>Isabel<')->not->toContain('>Laura<')
        ->toContain('aria-label="10:00 Corte, duración 1 h, Rosa Vidal, columna de Isabel"')
        ->toContain('aria-label="12:00 Peinado, duración 1 h, Marta Ruiz, cualquier peluquera, columna de Jennifer"')
        ->toContain('Peluquera: Isabel')
        ->toContain('Peluquera: cualquiera');
});

test('a hairdresser\'s closure shades only her column and names her', function () {
    ScheduleBlock::create(['starts_at' => '2030-01-08 09:00', 'ends_at' => '2030-01-08 19:00', 'capacity_reduction' => 1, 'hairdresser_id' => $this->jennifer->id]);

    $html = $this->get(route('admin.agenda', ['fecha' => '2030-01-08']))->getContent();

    expect($html)->toContain('aria-label="Cierre parcial, columna de Jennifer"')
        ->not->toContain('aria-label="Cierre parcial, columna de Isabel"')
        ->toContain('aria-label="Hueco libre a las 09:00, columna de Isabel"')
        ->toContain('No está Jennifer');
});

test('the hairdresser filter shows only her column, her cards and the unassigned ones drawn in it', function () {
    agendaAppointment('10:00', '11:00', $this->isabel, ['customer_name' => 'Rosa Vidal']);
    agendaAppointment('10:00', '11:00', null, ['customer_name' => 'Marta Ruiz']);
    agendaAppointment('15:00', '16:00', $this->jennifer, ['customer_name' => 'Núria Puig']);

    $html = $this->get(route('admin.agenda', ['fecha' => '2030-01-08', 'peluquera' => $this->jennifer->id]))->assertOk()->getContent();

    expect($html)->toContain('columna de Jennifer')->not->toContain('columna de Isabel')
        ->toContain('Núria Puig')->toContain('Marta Ruiz')->not->toContain('Rosa Vidal')
        ->toMatch('/<option value="'.$this->jennifer->id.'" selected/');
});

test('the hairdresser filter is kept by the links and by "volver"', function () {
    $appointment = agendaAppointment('10:00', '11:00', $this->jennifer);

    $html = $this->get(route('admin.agenda', ['fecha' => '2030-01-08', 'peluquera' => $this->jennifer->id]))->getContent();

    expect($html)->toContain('vista=semana&amp;fecha=2030-01-08&amp;peluquera='.$this->jennifer->id)
        ->toContain('volver=dia%3A2030-01-08%3A'.$this->jennifer->id);

    $this->post(route('admin.appointments.cancel', $appointment), ['volver' => 'dia:2030-01-08:'.$this->jennifer->id])
        ->assertRedirect(route('admin.agenda', ['vista' => 'dia', 'fecha' => '2030-01-08', 'peluquera' => $this->jennifer->id]));
});

test('an unknown or inactive hairdresser in the filter shows every column', function () {
    $laura = Hairdresser::factory()->inactive()->create(['name' => 'Laura']);

    foreach ([(string) $laura->id, '999', 'abc'] as $value) {
        $this->get(route('admin.agenda', ['fecha' => '2030-01-08', 'peluquera' => $value]))
            ->assertOk()
            ->assertSee('columna de Jennifer', false)
            ->assertSee('columna de Isabel', false);
    }
});

test('with the filter, "Cabe" says where the service fits for her', function () {
    $service = Service::factory()->create(['name' => 'Corte', 'duration_minutes' => 60]);
    agendaAppointment('10:00', '11:00', $this->jennifer);

    $html = $this->get(route('admin.agenda', ['fecha' => '2030-01-08', 'peluquera' => $this->jennifer->id, 'servicio' => $service->id]))->getContent();

    expect($html)->toContain('aria-label="Hueco libre a las 09:00, columna de Jennifer, cabe Corte"')
        ->not->toContain('aria-label="Hueco libre a las 09:30, columna de Jennifer, cabe Corte"')
        ->toContain('aria-label="Hueco libre a las 11:00, columna de Jennifer, cabe Corte"');
});

test('the week view with the filter shows only her column on each day', function () {
    agendaAppointment('10:00', '11:00', $this->isabel, ['customer_name' => 'Rosa Vidal']);

    $html = $this->get(route('admin.agenda', ['vista' => 'semana', 'fecha' => '2030-01-08', 'peluquera' => $this->jennifer->id]))->assertOk()->getContent();

    expect($html)->toContain('columna de Jennifer')->not->toContain('columna de Isabel')->not->toContain('Rosa Vidal');
});

test('the month view with the filter counts only her appointments', function () {
    agendaAppointment('10:00', '11:00', $this->isabel);
    agendaAppointment('12:00', '13:00', $this->isabel);
    agendaAppointment('15:00', '16:00', $this->jennifer);

    $all = $this->get(route('admin.agenda', ['vista' => 'mes', 'fecha' => '2030-01-08']))->getContent();
    $isabel = $this->get(route('admin.agenda', ['vista' => 'mes', 'fecha' => '2030-01-08', 'peluquera' => $this->isabel->id]))->getContent();

    expect($all)->toContain('3 citas');
    expect($isabel)->toContain('2 citas')->not->toContain('3 citas');
});
