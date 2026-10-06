<?php

use App\Booking\OpeningHoursSummary;
use App\Models\OpeningHour;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Replaces the whole week with a split, non-default schedule, so every
 * assertion below can tell the configured value apart from a leftover
 * hardcoded one.
 */
function configureSplitSchedule(): string
{
    OpeningHour::query()->delete();
    OpeningHour::create(['weekday' => 3, 'opens_at' => '10:00:00', 'closes_at' => '13:30:00']);
    OpeningHour::create(['weekday' => 3, 'opens_at' => '17:00:00', 'closes_at' => '21:00:00']);
    OpeningHoursSummary::forgetCachedSchedule();

    return OpeningHoursSummary::text();
}

test('the home page footer, hero and contacto section all read the panel schedule, not a hardcoded one', function () {
    $expected = configureSplitSchedule();

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain($expected);
    expect(substr_count($html, $expected))->toBeGreaterThanOrEqual(3); // footer + "Reserva tu cita" + "Contacto" section
    expect($html)->not->toContain('9:00');
    expect($html)->not->toContain('Mar-Sáb');
});

test('the contacto page meta description and hours block read the panel schedule', function () {
    $expected = configureSplitSchedule();

    $html = $this->get(route('contacto'))->assertOk()->getContent();

    expect($html)->toContain('Horario: '.$expected)
        ->toContain($expected)
        ->not->toContain('Mar-Sáb 9:00-19:00');
});

test('the FAQ about online booking states the panel schedule in its JSON-LD', function () {
    $expected = configureSplitSchedule();

    $html = $this->get('/')->assertOk()->getContent();
    preg_match('#<script type="application/ld\+json">\s*(\{"@context":"https://schema.org","@type":"FAQPage".*?)\s*</script>#s', $html, $faqSchema);
    $questions = collect(json_decode($faqSchema[1], true)['mainEntity'])->pluck('acceptedAnswer.text', 'name');

    expect($questions['¿Puedo pedir cita online?'])->toContain($expected);
});

test('the local business JSON-LD opening hours reflect the panel, with closed days omitted', function () {
    OpeningHour::query()->delete();
    OpeningHour::create(['weekday' => 3, 'opens_at' => '10:00:00', 'closes_at' => '13:30:00']);
    OpeningHoursSummary::forgetCachedSchedule();

    $html = $this->get('/')->assertOk()->getContent();
    preg_match_all('#<script type="application/ld\+json">\s*(.+?)\s*</script>#s', $html, $matches);
    $salon = collect($matches[1])
        ->map(fn (string $json) => json_decode($json, true))
        ->first(fn (?array $data) => is_array($data) && in_array('HairSalon', (array) ($data['@type'] ?? [])));

    expect($salon['openingHoursSpecification'])->toBe([
        [
            '@type' => 'OpeningHoursSpecification',
            'dayOfWeek' => ['Wednesday'],
            'opens' => '10:00',
            'closes' => '13:30',
        ],
    ]);
});

test('a fully closed week never shows a 00:00-00:00 placeholder in the JSON-LD', function () {
    OpeningHour::query()->delete();
    OpeningHoursSummary::forgetCachedSchedule();

    $html = $this->get('/')->assertOk()->getContent();
    preg_match_all('#<script type="application/ld\+json">\s*(.+?)\s*</script>#s', $html, $matches);
    $salon = collect($matches[1])
        ->map(fn (string $json) => json_decode($json, true))
        ->first(fn (?array $data) => is_array($data) && in_array('HairSalon', (array) ($data['@type'] ?? [])));

    expect($salon['openingHoursSpecification'])->toBe([]);
});
