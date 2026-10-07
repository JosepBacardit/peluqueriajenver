<?php

use App\Booking\AppointmentLaneAssigner;
use App\Booking\DayTimeline;
use App\Booking\TimeProfile;
use App\Mail\AppointmentConfirmedMail;
use App\Mail\CustomerCancelledAppointmentMail;
use App\Mail\NewAppointmentMail;
use App\Models\Appointment;
use App\Models\OpeningHour;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * The agenda with waits inside services: each active stretch of an
 * appointment takes a lane (preferring the one its previous stretch was
 * in), the lane stays free during the wait, marked "Espera · {clienta}
 * hasta {hora}", and the stretches read "(1/2)", "(2/2)".
 *
 * The specification's example, capacity 2, Tuesday 2030-01-08:
 *  - Ana, Coloración 10:00-12:00, waiting 10:30-11:15;
 *  - Berta, Corte 10:00-10:45;
 *  - Carla, Peinado 10:30-11:30 (during Ana's wait).
 * Ana starts in plaza 1 and, with Carla still there at 11:15, finishes in
 * plaza 2.
 */
function waitAgendaAppointment(string $name, string $from, string $to, array $waits = [], string $label = 'Servicio'): Appointment
{
    return Appointment::factory()->create([
        'customer_name' => $name,
        'services_label' => $label,
        'starts_at' => "2030-01-08 {$from}",
        'ends_at' => "2030-01-08 {$to}",
        'waits' => $waits === [] ? null : $waits,
    ]);
}

function specExample(): array
{
    return [
        'ana' => waitAgendaAppointment('Ana', '10:00', '12:00', [['start' => 30, 'minutes' => 45]], 'Coloración'),
        'berta' => waitAgendaAppointment('Berta', '10:00', '10:45', [], 'Corte'),
        'carla' => waitAgendaAppointment('Carla', '10:30', '11:30', [], 'Peinado'),
    ];
}

function waitTimeline(array $appointments, int $capacity = 2): array
{
    $day = CarbonImmutable::parse('2030-01-08')->startOfDay();

    return DayTimeline::build($day, 9 * 60, 19 * 60, $capacity, collect([OpeningHour::make(['weekday' => 2, 'opens_at' => '09:00:00', 'closes_at' => '19:00:00'])]), collect($appointments), collect(), $day->subDay());
}

/**
 * @return list<array> every segment of the timeline's open pieces
 */
function waitSegments(array $timeline): array
{
    return collect($timeline['pieces'])->where('kind', 'open')->flatMap(fn (array $piece) => $piece['segments'])->values()->all();
}

test('each active stretch gets a lane, preferring the lane of the previous one', function () {
    ['ana' => $ana, 'berta' => $berta, 'carla' => $carla] = specExample();

    $result = AppointmentLaneAssigner::assign(collect([$ana, $berta, $carla]), capacity: 2);

    expect($result['lanes'])->toBe([
        "{$ana->id}:0" => 0,
        "{$berta->id}:0" => 1,
        "{$carla->id}:0" => 0, // Ana's lane, free while she waits
        "{$ana->id}:1" => 1, // her lane is still Carla's at 11:15
    ]);
    expect($result['maxLanes'])->toBe(2);
    expect(array_filter($result['overCapacity']))->toBe([]);
});

test('the second stretch stays in the same lane when it is free', function () {
    $ana = waitAgendaAppointment('Ana', '10:00', '12:00', [['start' => 30, 'minutes' => 45]]);
    $berta = waitAgendaAppointment('Berta', '10:00', '11:30');
    $dani = waitAgendaAppointment('Dani', '10:30', '11:00');

    $result = AppointmentLaneAssigner::assign(collect([$ana, $berta, $dani]), capacity: 2);

    expect($result['lanes']["{$ana->id}:0"])->toBe(0);
    expect($result['lanes']["{$dani->id}:0"])->toBe(0);
    expect($result['lanes']["{$ana->id}:1"])->toBe(0);
});

test('only the stretch that goes over capacity is marked so', function () {
    $ana = waitAgendaAppointment('Ana', '10:00', '12:00', [['start' => 30, 'minutes' => 45]]);
    // Both forced in ("Guardar igualmente") over Ana's second stretch.
    $berta = waitAgendaAppointment('Berta', '11:00', '12:00');
    $carla = waitAgendaAppointment('Carla', '11:00', '12:00');

    $result = AppointmentLaneAssigner::assign(collect([$ana, $berta, $carla]), capacity: 2);

    expect($result['overCapacity']["{$ana->id}:0"])->toBeFalse();
    expect($result['overCapacity']["{$ana->id}:1"])->toBeTrue();
    expect($result['maxLanes'])->toBe(3);
});

test('the timeline draws each stretch as its own block, numbered', function () {
    $blocks = collect(waitSegments(waitTimeline(specExample())))
        ->where('type', 'appointment')
        ->filter(fn (array $segment) => $segment['appointment']->customer_name === 'Ana')
        ->values();

    expect($blocks->map(fn (array $s) => [$s['lane'], $s['start'], $s['end'], $s['part'], $s['parts'], $s['waitUntil']])->all())->toBe([
        [0, 600, 630, 1, 2, 675],
        [1, 675, 720, 2, 2, null],
    ]);
});

test('the lane left free by a wait is marked with whose wait it is and until when', function () {
    $timeline = waitTimeline(specExample());
    $marked = collect(waitSegments($timeline))->filter(fn (array $s) => isset($s['wait']))->values();

    // Plaza 1 is Carla's during the wait; plaza 2 is free from 10:45 (Berta
    // done) until Ana comes back at 11:15.
    expect($marked->map(fn (array $s) => [$s['lane'], $s['start'], $s['end'], $s['wait']])->all())->toBe([
        [1, 645, 660, ['customer' => 'Ana', 'until' => '11:15']],
        [1, 660, 675, ['customer' => 'Ana', 'until' => '11:15']],
    ]);
});

test('a wait with its lane free the whole time is marked there and can still be tapped', function () {
    $ana = waitAgendaAppointment('Ana', '10:00', '12:00', [['start' => 30, 'minutes' => 60]]);
    $segments = collect(waitSegments(waitTimeline([$ana])));

    $waitSlot = $segments->first(fn (array $s) => $s['lane'] === 0 && $s['start'] === 630);
    expect($waitSlot['type'])->toBe('free');
    expect($waitSlot['tappable'])->toBeTrue();
    expect($waitSlot['wait'])->toBe(['customer' => 'Ana', 'until' => '11:30']);
    expect($segments->first(fn (array $s) => $s['lane'] === 1 && $s['start'] === 630))->not->toHaveKey('wait');
});

test('"Cabe" only counts the active stretches of the chosen service', function () {
    // Plaza 1 holds an appointment 10:30-11:15; a service with a wait right
    // over it (active 10:00-10:30 and 11:15-12:00) still fits in plaza 1.
    $other = waitAgendaAppointment('Berta', '10:30', '11:15');
    $timeline = waitTimeline([$other], capacity: 1);

    $marked = DayTimeline::markServiceFit($timeline, [600], new TimeProfile(120, [['start' => 30, 'minutes' => 45]]));
    $slot = collect(waitSegments($marked))->first(fn (array $s) => $s['lane'] === 0 && $s['start'] === 600);

    expect($slot['fits'])->toBeTrue();
});

test('the agenda shows the numbered stretches, the wait mark and the wait in the card', function () {
    $this->actingAs(User::factory()->create());
    $this->travelTo(CarbonImmutable::parse('2030-01-08 08:00'));
    specExample();

    $html = $this->get(route('admin.agenda', ['fecha' => '2030-01-08']))->assertOk()->getContent();

    expect($html)
        // Review L2: before the name, the part a narrow lane never cuts.
        ->toContain('(1/2) Ana')
        ->toContain('(2/2) Ana')
        ->toContain('aria-label="10:00 Coloración, Ana, tramo 1 de 2, de 10:00 a 10:30, espera hasta 11:15, plaza 1"')
        ->toContain('aria-label="11:15 Coloración, Ana, tramo 2 de 2, de 11:15 a 12:00, plaza 2"')
        ->toContain('title="10:00–12:00 (2 h, incl. 45 min de espera) Coloración, Ana · tramo 1 de 2: 10:00–10:30, espera hasta 11:15"')
        ->toContain('Espera · Ana hasta 11:15')
        ->toContain('(2 h, incl. 45 min de espera)')
        ->toContain('Espera: 10:30–11:15');
});

test('an appointment without waits looks exactly as before', function () {
    $this->actingAs(User::factory()->create());
    $this->travelTo(CarbonImmutable::parse('2030-01-08 08:00'));
    waitAgendaAppointment('Berta', '10:00', '10:45', [], 'Corte');

    $html = $this->get(route('admin.agenda', ['fecha' => '2030-01-08']))->assertOk()->getContent();

    expect($html)
        ->toContain('aria-label="10:00 Corte, duración 45 min, Berta, plaza 1"')
        ->not->toContain('(1/1)')
        ->not->toContain('tramo')
        ->not->toContain('Espera');
});

test('the two emails to the salon show the wait; the customer ones never do', function () {
    $coloracion = Service::factory()->create(['name' => 'Coloración', 'duration_minutes' => 120, 'waits' => [['start' => 30, 'minutes' => 45]]]);
    $appointment = Appointment::factory()->withServices($coloracion)->create(['starts_at' => '2030-01-08 10:00']);

    foreach ([NewAppointmentMail::class, CustomerCancelledAppointmentMail::class] as $mail) {
        expect((new $mail($appointment))->render())
            ->toContain('Coloración (2 h, incl. 45 min de espera)')
            ->toContain('Espera:</strong> 10:30–11:15');
    }

    expect((new AppointmentConfirmedMail($appointment))->render())
        ->not->toContain('de espera') // ("Te esperamos" is in every email)
        ->not->toContain('Espera:')
        ->not->toContain('11:15');
});

/*
 * Found in the browser (coordinator, 2026-10-07): a free run during a wait
 * is split into several segments (every half hour, and around a short
 * leading filler), and each one showed the visible mark. It must show
 * once per continuous run, on its first segment; every tappable segment
 * keeps saying it in its aria-label.
 */
test('the wait mark is labelled once per continuous free run, on its first segment', function () {
    $marked = collect(waitSegments(waitTimeline(specExample())))->filter(fn (array $s) => isset($s['wait']))->values();

    expect($marked->map(fn (array $s) => [$s['lane'], $s['start'], $s['waitLabel']])->all())->toBe([
        [1, 645, true],
        [1, 660, false],
    ]);
});

test('the agenda shows the visible wait mark once per run, and every tappable slot still says it', function () {
    $this->actingAs(User::factory()->create());
    $this->travelTo(CarbonImmutable::parse('2030-01-08 08:00'));
    specExample();
    // A wait with its lane free the whole hour: two tappable half hours.
    waitAgendaAppointment('Dora', '15:00', '17:00', [['start' => 30, 'minutes' => 60]], 'Mechas');

    $html = $this->get(route('admin.agenda', ['fecha' => '2030-01-08']))->assertOk()->getContent();

    expect(substr_count($html, '>Espera · Ana hasta 11:15</span>'))->toBe(1);
    expect(substr_count($html, '>Espera · Dora hasta 16:30</span>'))->toBe(1);
    expect($html)
        ->toContain('aria-label="Hueco libre a las 15:30, plaza 1, espera de Dora hasta 16:30"')
        ->toContain('aria-label="Hueco libre a las 16:00, plaza 1, espera de Dora hasta 16:30"');
});
