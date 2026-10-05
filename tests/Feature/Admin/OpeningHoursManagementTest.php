<?php

use App\Models\OpeningHour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

/**
 * Form payload with every day closed, overridable per day and range.
 *
 * @param  array<int, list<array{opens: string, closes: string}>>  $days
 * @return array{days: array<int, list<array{opens: string, closes: string}>>}
 */
function openingHoursPayload(array $days = []): array
{
    $payload = [];

    foreach (range(1, 7) as $weekday) {
        $payload[$weekday] = [
            $days[$weekday][0] ?? ['opens' => '', 'closes' => ''],
            $days[$weekday][1] ?? ['opens' => '', 'closes' => ''],
        ];
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
        ->assertSee('value="09:00"', false);
});

test('a day can have two ranges and days without ranges are closed', function () {
    $this->put(route('admin.opening-hours.update'), openingHoursPayload([
        2 => [['opens' => '09:00', 'closes' => '13:00'], ['opens' => '15:00', 'closes' => '19:00']],
        6 => [['opens' => '09:00', 'closes' => '14:00']],
    ]))->assertRedirect(route('admin.opening-hours.edit'));

    expect(savedRanges())->toBe([
        '2 09:00-13:00',
        '2 15:00-19:00',
        '6 09:00-14:00',
    ]);
});

test('impossible ranges are rejected and the week stays unchanged', function (array $days, string $errorKey) {
    $before = savedRanges();

    $this->from(route('admin.opening-hours.edit'))
        ->put(route('admin.opening-hours.update'), openingHoursPayload($days))
        ->assertRedirect(route('admin.opening-hours.edit'))
        ->assertSessionHasErrors($errorKey);

    expect(savedRanges())->toBe($before);
})->with([
    'ends before it starts' => [[3 => [['opens' => '13:00', 'closes' => '09:00']]], 'days.3'],
    'ends when it starts' => [[3 => [['opens' => '09:00', 'closes' => '09:00']]], 'days.3'],
    'overlapping ranges' => [[3 => [['opens' => '09:00', 'closes' => '14:00'], ['opens' => '13:00', 'closes' => '19:00']]], 'days.3'],
    'second range before the first' => [[3 => [['opens' => '15:00', 'closes' => '19:00'], ['opens' => '09:00', 'closes' => '13:00']]], 'days.3'],
    'only the opening time' => [[4 => [['opens' => '09:00', 'closes' => '']]], 'days.4'],
    'not a multiple of 5 minutes' => [[5 => [['opens' => '09:03', 'closes' => '19:00']]], 'days.5.0.opens'],
    'not a time' => [[5 => [['opens' => 'nine', 'closes' => '19:00']]], 'days.5.0.opens'],
]);

/**
 * PRF-096: at 360px the label, the two time inputs and the dash of a
 * range row must not force horizontal scroll or get clipped. The row
 * wraps instead of overflowing.
 */
test('every opening-hours range row can wrap instead of overflowing on a narrow screen', function () {
    $html = $this->get(route('admin.opening-hours.edit'))->assertOk()->getContent();

    expect(substr_count($html, 'flex flex-wrap items-center gap-2 text-sm'))->toBe(14); // 7 days x 2 ranges
    expect(substr_count($html, 'type="time"'))->toBe(14 * 2);
});

/**
 * Review finding N3 (.ai/reviews/mobile-admin-ux.md, coordinator): the
 * time inputs measured 31px; py-3 brings every one of them to 44px.
 */
test('every time input meets the 44px touch target', function () {
    $html = $this->get(route('admin.opening-hours.edit'))->assertOk()->getContent();

    expect(substr_count($html, 'bg-black border border-[#2A2A2A] px-2 py-3 min-w-0'))->toBe(14 * 2);
});
