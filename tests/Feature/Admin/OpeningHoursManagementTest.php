<?php

use App\Models\OpeningHour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

/**
 * Form payload: every day closed unless given a `closed: false` override
 * or at least one range. A range is `['opens' => 'HH:MM', 'closes' =>
 * 'HH:MM']` (or '' to mean the select was left at "—").
 *
 * @param  array<int, array{closed?: bool, ranges?: list<array{opens: string, closes: string}>}>  $days
 * @return array{days: array<int, array<string, mixed>>}
 */
function openingHoursPayload(array $days = []): array
{
    $payload = [];

    foreach (range(1, 7) as $weekday) {
        $ranges = $days[$weekday]['ranges'] ?? [];
        $closed = $days[$weekday]['closed'] ?? ($ranges === []);

        $payload[$weekday] = ['closed' => $closed ? '1' : '0'];

        foreach ([0, 1] as $index) {
            $range = $ranges[$index] ?? ['opens' => '', 'closes' => ''];
            $payload[$weekday][$index] = [
                'opens_hour' => $range['opens'] === '' ? '' : substr($range['opens'], 0, 2),
                'opens_minute' => $range['opens'] === '' ? '00' : substr($range['opens'], 3, 2),
                'closes_hour' => $range['closes'] === '' ? '' : substr($range['closes'], 0, 2),
                'closes_minute' => $range['closes'] === '' ? '00' : substr($range['closes'], 3, 2),
            ];
        }
    }

    return ['days' => $payload];
}

/**
 * @return list<string>
 */
function savedRanges(): array
{
    return OpeningHour::query()->orderBy('weekday')->orderBy('opens_at')->get()
        ->map(fn (OpeningHour $hour) => $hour->weekday.' '.substr($hour->opens_at, 0, 5).'-'.substr($hour->closes_at, 0, 5))
        ->all();
}

test('a fresh install opens tuesday to saturday from 9 to 19 and closes sunday and monday', function () {
    expect(savedRanges())->toBe([
        '2 09:00-19:00',
        '3 09:00-19:00',
        '4 09:00-19:00',
        '5 09:00-19:00',
        '6 09:00-19:00',
    ]);

    $this->get(route('admin.opening-hours.edit'))
        ->assertOk()
        ->assertSeeInOrder(['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'])
        ->assertSee('value="09"', false);
});

test('monday, with no ranges, loads with "cerrado" already checked', function () {
    $html = $this->get(route('admin.opening-hours.edit'))->assertOk()->getContent();

    // Monday is weekday 1, the very first fieldset; its checkbox is the
    // first "closed" checkbox in the page and must carry `checked`.
    expect($html)->toContain('name="days[1][closed]"');
    $afterMonday = substr($html, (int) strpos($html, 'name="days[1][closed]"'));
    expect(substr($afterMonday, 0, 250))->toContain('checked');
});

test('a day can have two ranges saved through the hour/minute selects', function () {
    $this->put(route('admin.opening-hours.update'), openingHoursPayload([
        2 => ['ranges' => [['opens' => '09:00', 'closes' => '13:00'], ['opens' => '15:00', 'closes' => '19:00']]],
        6 => ['ranges' => [['opens' => '09:00', 'closes' => '14:00']]],
    ]))->assertRedirect(route('admin.opening-hours.edit'));

    expect(savedRanges())->toBe([
        '2 09:00-13:00',
        '2 15:00-19:00',
        '6 09:00-14:00',
    ]);
});

test('a range is removed by putting its hour back at the dash, whatever its minutes hold', function () {
    $this->put(route('admin.opening-hours.update'), [
        'days' => [
            1 => ['closed' => '0', 0 => ['opens_hour' => '', 'opens_minute' => '30', 'closes_hour' => '', 'closes_minute' => '45'], 1 => ['opens_hour' => '', 'opens_minute' => '00', 'closes_hour' => '', 'closes_minute' => '00']],
            2 => ['closed' => '0', 0 => ['opens_hour' => '09', 'opens_minute' => '00', 'closes_hour' => '13', 'closes_minute' => '00'], 1 => ['opens_hour' => '', 'opens_minute' => '00', 'closes_hour' => '', 'closes_minute' => '00']],
            3 => ['closed' => '1'],
            4 => ['closed' => '1'],
            5 => ['closed' => '1'],
            6 => ['closed' => '1'],
            7 => ['closed' => '1'],
        ],
    ])->assertRedirect(route('admin.opening-hours.edit'));

    expect(savedRanges())->toBe(['2 09:00-13:00']);
});

test('checking "cerrado" clears the day even if its ranges still carry values', function () {
    $this->put(route('admin.opening-hours.update'), [
        'days' => [
            1 => ['closed' => '1', 0 => ['opens_hour' => '09', 'opens_minute' => '00', 'closes_hour' => '19', 'closes_minute' => '00'], 1 => ['opens_hour' => '', 'opens_minute' => '00', 'closes_hour' => '', 'closes_minute' => '00']],
            2 => ['closed' => '0', 0 => ['opens_hour' => '09', 'opens_minute' => '00', 'closes_hour' => '19', 'closes_minute' => '00'], 1 => ['opens_hour' => '', 'opens_minute' => '00', 'closes_hour' => '', 'closes_minute' => '00']],
            3 => ['closed' => '1'],
            4 => ['closed' => '1'],
            5 => ['closed' => '1'],
            6 => ['closed' => '1'],
            7 => ['closed' => '1'],
        ],
    ])->assertRedirect(route('admin.opening-hours.edit'));

    // Monday's "closed" wins even though its selects still carried 09:00-19:00.
    expect(savedRanges())->toBe(['2 09:00-19:00']);
});

test('a range with only one of its two ends filled in is rejected with the exact message', function () {
    $before = savedRanges();

    $this->from(route('admin.opening-hours.edit'))
        ->put(route('admin.opening-hours.update'), openingHoursPayload([
            4 => ['closed' => false, 'ranges' => [['opens' => '09:00', 'closes' => '']]],
        ]))
        ->assertRedirect(route('admin.opening-hours.edit'))
        ->assertSessionHasErrors('days.4');

    $errors = session('errors');
    expect($errors->first('days.4'))->toContain('Elige inicio y fin, o deja los dos en —.');
    expect(savedRanges())->toBe($before);
});

test('impossible ranges are rejected and the week stays unchanged', function (array $days, string $errorKey) {
    $before = savedRanges();

    $this->from(route('admin.opening-hours.edit'))
        ->put(route('admin.opening-hours.update'), openingHoursPayload($days))
        ->assertRedirect(route('admin.opening-hours.edit'))
        ->assertSessionHasErrors($errorKey);

    expect(savedRanges())->toBe($before);
})->with([
    'ends before it starts' => [[3 => ['closed' => false, 'ranges' => [['opens' => '13:00', 'closes' => '09:00']]]], 'days.3'],
    'ends when it starts' => [[3 => ['closed' => false, 'ranges' => [['opens' => '09:00', 'closes' => '09:00']]]], 'days.3'],
    'overlapping ranges' => [[3 => ['closed' => false, 'ranges' => [['opens' => '09:00', 'closes' => '14:00'], ['opens' => '13:00', 'closes' => '19:00']]]], 'days.3'],
    'second range before the first' => [[3 => ['closed' => false, 'ranges' => [['opens' => '15:00', 'closes' => '19:00'], ['opens' => '09:00', 'closes' => '13:00']]]], 'days.3'],
]);

/**
 * @return array<int, array<string, mixed>>
 */
function allDaysClosedExcept(int $weekday, array $override): array
{
    $days = [];

    foreach (range(1, 7) as $day) {
        $days[$day] = $day === $weekday ? $override : ['closed' => '1'];
    }

    return $days;
}

test('an hour outside the whitelist is rejected, even if nothing else is wrong', function () {
    $before = savedRanges();

    $this->put(route('admin.opening-hours.update'), [
        'days' => allDaysClosedExcept(3, [
            'closed' => '0',
            0 => ['opens_hour' => '06', 'opens_minute' => '00', 'closes_hour' => '19', 'closes_minute' => '00'],
            1 => ['opens_hour' => '', 'opens_minute' => '00', 'closes_hour' => '', 'closes_minute' => '00'],
        ]),
    ])->assertSessionHasErrors('days.3.0.opens_hour');

    expect(savedRanges())->toBe($before);
});

test('a non-multiple-of-15 minute is rejected, even if nothing else is wrong', function () {
    $before = savedRanges();

    $this->put(route('admin.opening-hours.update'), [
        'days' => allDaysClosedExcept(3, [
            'closed' => '0',
            0 => ['opens_hour' => '09', 'opens_minute' => '07', 'closes_hour' => '19', 'closes_minute' => '00'],
            1 => ['opens_hour' => '', 'opens_minute' => '00', 'closes_hour' => '', 'closes_minute' => '00'],
        ]),
    ])->assertSessionHasErrors('days.3.0.opens_minute');

    expect(savedRanges())->toBe($before);
});

/**
 * PRF-096: at 360px the label and the four selects (hour/minutes of each
 * end) of a range row must not force horizontal scroll or get clipped.
 * The row wraps instead of overflowing.
 */
test('every opening-hours range row can wrap instead of overflowing on a narrow screen', function () {
    $html = $this->get(route('admin.opening-hours.edit'))->assertOk()->getContent();

    expect(substr_count($html, 'flex flex-wrap items-center gap-2 text-sm'))->toBe(14); // 7 days x 2 ranges
    expect(substr_count($html, '<select'))->toBe(14 * 4); // 2 ranges x 4 selects, x 7 days
});

/**
 * Review finding N3 (.ai/reviews/mobile-admin-ux.md, coordinator): every
 * touch target must be at least 44px; min-h-11 (44px) covers both the
 * selects and the "Cerrado" checkbox's own clickable label.
 */
test('every select and the "cerrado" checkbox label meet the 44px touch target', function () {
    $html = $this->get(route('admin.opening-hours.edit'))->assertOk()->getContent();

    expect(substr_count($html, 'min-h-11 w-14'))->toBe(14 * 2); // hour selects (opens + closes) x 14 rows
    expect(substr_count($html, 'min-h-11 w-12'))->toBe(14 * 2); // minute selects (opens + closes) x 14 rows
    expect(substr_count($html, 'flex items-center gap-2 text-sm text-gray-300 mb-4 min-h-11'))->toBe(7); // one "Cerrado" label per day
});

/**
 * Accessibility: every select has its own label, and a day's error is
 * associated with its selects through aria-describedby.
 */
test('every select has an aria-label and the day error is linked with aria-describedby', function () {
    $this->put(route('admin.opening-hours.update'), openingHoursPayload([
        4 => ['closed' => false, 'ranges' => [['opens' => '09:00', 'closes' => '']]],
    ]));

    $html = $this->get(route('admin.opening-hours.edit'))->assertOk()->getContent();

    expect(substr_count($html, 'aria-label="Jueves, tramo 1, hora de inicio"'))->toBe(1);
    expect($html)->toContain('id="dia-4-error"');
    expect(substr_count($html, 'aria-describedby="dia-4-error"'))->toBe(8); // Thursday's 2 ranges x 4 selects
});
