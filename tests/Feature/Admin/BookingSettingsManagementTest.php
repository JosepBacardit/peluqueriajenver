<?php

use App\Models\BookingSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

/**
 * @return array<string, int>
 */
function bookingSettingsPayload(array $overrides = []): array
{
    return array_merge([
        'capacity' => 3,
        'slot_interval_minutes' => 30,
        'min_notice_minutes' => 60,
        'max_advance_days' => 90,
        'cancellation_limit_hours' => 12,
    ], $overrides);
}

test('a fresh install has the agreed default booking settings', function () {
    $settings = BookingSetting::current();

    expect($settings->capacity)->toBe(2);
    expect($settings->slot_interval_minutes)->toBe(15);
    expect($settings->min_notice_minutes)->toBe(120);
    expect($settings->max_advance_days)->toBe(60);
    expect($settings->cancellation_limit_hours)->toBe(24);

    $this->get(route('admin.settings.edit'))->assertOk()->assertSee('Capacidad');
});

test('a salon user can change the booking settings', function () {
    $this->put(route('admin.settings.update'), bookingSettingsPayload())
        ->assertRedirect(route('admin.settings.edit'));

    $settings = BookingSetting::current();
    expect($settings->capacity)->toBe(3);
    expect($settings->slot_interval_minutes)->toBe(30);
    expect($settings->min_notice_minutes)->toBe(60);
    expect($settings->max_advance_days)->toBe(90);
    expect($settings->cancellation_limit_hours)->toBe(12);
    expect(BookingSetting::count())->toBe(1);
});

test('settings outside their limits are rejected without changes', function (array $overrides, string $field) {
    $this->from(route('admin.settings.edit'))
        ->put(route('admin.settings.update'), bookingSettingsPayload($overrides))
        ->assertSessionHasErrors($field);

    expect(BookingSetting::current()->capacity)->toBe(2);
})->with([
    'zero capacity' => [['capacity' => 0], 'capacity'],
    'capacity over 10' => [['capacity' => 11], 'capacity'],
    'interval of 25' => [['slot_interval_minutes' => 25], 'slot_interval_minutes'],
    'negative minimum notice' => [['min_notice_minutes' => -1], 'min_notice_minutes'],
    'minimum notice over 7 days' => [['min_notice_minutes' => 10081], 'min_notice_minutes'],
    'zero maximum advance' => [['max_advance_days' => 0], 'max_advance_days'],
    'maximum advance over a year' => [['max_advance_days' => 366], 'max_advance_days'],
    'cancellation limit over a week' => [['cancellation_limit_hours' => 169], 'cancellation_limit_hours'],
]);

test('the settings module requires an authenticated user', function () {
    auth()->logout();

    $this->put(route('admin.settings.update'), bookingSettingsPayload())->assertRedirect(route('login'));
    expect(BookingSetting::current()->capacity)->toBe(2);
});
