<?php

use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

/**
 * @return array<string, mixed>
 */
function validServicePayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Corte y peinado',
        // The duration is the sum of the steps; with no wait, just the first.
        'work_1' => 45,
        'price' => '35.50',
        'is_bookable_online' => '1',
        'is_active' => '1',
        'sort_order' => 3,
    ], $overrides);
}

test('the services module requires an authenticated user', function () {
    auth()->logout();

    $this->get(route('admin.services.index'))->assertRedirect(route('login'));
    $this->post(route('admin.services.store'), validServicePayload())->assertRedirect(route('login'));
    expect(Service::count())->toBe(0);
});

test('an empty services list explains how to start', function () {
    $this->get(route('admin.services.index'))
        ->assertOk()
        ->assertSee('Todavía no hay servicios. Crea el primero para poder recibir reservas.');
});

test('a salon user can create a service with every field', function () {
    $this->post(route('admin.services.store'), validServicePayload())
        ->assertRedirect(route('admin.services.index'));

    $service = Service::sole();
    expect($service->name)->toBe('Corte y peinado');
    expect($service->duration_minutes)->toBe(45);
    expect($service->price_cents)->toBe(3550);
    expect($service->is_bookable_online)->toBeTrue();
    expect($service->is_active)->toBeTrue();
    expect($service->sort_order)->toBe(3);
});

test('the internal price and sort order are optional', function () {
    $this->post(route('admin.services.store'), validServicePayload(['price' => '', 'sort_order' => '']))
        ->assertRedirect(route('admin.services.index'));

    $service = Service::sole();
    expect($service->price_cents)->toBeNull();
    expect($service->sort_order)->toBe(0);
});

test('unchecked flags are saved as no', function () {
    $payload = validServicePayload();
    unset($payload['is_bookable_online'], $payload['is_active']);

    $this->post(route('admin.services.store'), $payload);

    $service = Service::sole();
    expect($service->is_bookable_online)->toBeFalse();
    expect($service->is_active)->toBeFalse();
});

test('invalid service values are rejected without saving anything', function (array $overrides, string $field) {
    $this->from(route('admin.services.create'))
        ->post(route('admin.services.store'), validServicePayload($overrides))
        ->assertRedirect(route('admin.services.create'))
        ->assertSessionHasErrors($field);

    expect(Service::count())->toBe(0);
})->with([
    'missing name' => [['name' => ''], 'name'],
    'one-letter name' => [['name' => 'C'], 'name'],
    'name over 100 characters' => [['name' => str_repeat('a', 101)], 'name'],
    'duration not a multiple of 5' => [['work_1' => 47], 'work_1'],
    'zero duration' => [['work_1' => 0], 'work_1'],
    'duration over 600' => [['work_1' => 605], 'work_1'],
    'negative price' => [['price' => '-1'], 'price'],
    'price with three decimals' => [['price' => '10.555'], 'price'],
    'price over 9999.99' => [['price' => '10000'], 'price'],
    'sort order over 999' => [['sort_order' => 1000], 'sort_order'],
]);

test('accented names are kept as typed', function () {
    $this->post(route('admin.services.store'), validServicePayload(['name' => 'Alisado de keratina · Cabello afro']));

    expect(Service::sole()->name)->toBe('Alisado de keratina · Cabello afro');
});

test('a salon user can edit a service with the same rules', function () {
    $service = Service::factory()->create(['name' => 'Corte', 'duration_minutes' => 30]);

    $this->put(route('admin.services.update', $service), validServicePayload(['name' => 'Corte caballero', 'work_1' => 20]))
        ->assertRedirect(route('admin.services.index'));

    expect($service->fresh()->name)->toBe('Corte caballero');
    expect($service->fresh()->duration_minutes)->toBe(20);

    $this->from(route('admin.services.edit', $service))
        ->put(route('admin.services.update', $service), validServicePayload(['work_1' => 0]))
        ->assertSessionHasErrors('work_1');

    expect($service->fresh()->duration_minutes)->toBe(20);
});

test('the list is ordered by sort order then name and shows every column', function () {
    Service::factory()->create(['name' => 'Zeta', 'sort_order' => 1, 'duration_minutes' => 90, 'price_cents' => 4500]);
    Service::factory()->create(['name' => 'Alfa', 'sort_order' => 2]);
    Service::factory()->create(['name' => 'Beta', 'sort_order' => 1, 'is_active' => false, 'is_bookable_online' => false]);

    $this->get(route('admin.services.index'))
        ->assertOk()
        ->assertSeeInOrder(['Beta', 'Zeta', 'Alfa'])
        ->assertSee('1 h 30 min')
        ->assertSee('45,00 €');
});

/**
 * PRF-091: cards at every width instead of a table with horizontal scroll
 * on a phone.
 */
test('the list is rendered as cards, never a table', function () {
    Service::factory()->create(['name' => 'Corte', 'sort_order' => 1, 'duration_minutes' => 30]);

    $html = $this->get(route('admin.services.index'))->assertOk()->getContent();

    expect($html)->not->toContain('<table')->not->toContain('overflow-x-auto');
    expect($html)->toContain('href="'.route('admin.services.edit', 1).'"');
});

test('services cannot be deleted from the panel', function () {
    $service = Service::factory()->create();

    expect(collect(Route::getRoutes())->contains(
        fn ($route) => in_array('DELETE', $route->methods()) && str_contains($route->uri(), 'servicios')
    ))->toBeFalse();

    $this->delete('/admin/servicios/'.$service->id)->assertStatus(405);
    expect(Service::count())->toBe(1);
});
