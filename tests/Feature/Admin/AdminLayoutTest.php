<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Every admin screen shares the same five-module navigation and a logout
 * button.
 */
test('every admin screen shows the module navigation and the logout button', function (string $routeName) {
    $this->actingAs(User::factory()->create());

    $response = $this->get(route($routeName))->assertOk();

    foreach (['admin.agenda' => 'Agenda', 'admin.services.index' => 'Servicios', 'admin.opening-hours.edit' => 'Horario', 'admin.blocks.index' => 'Cierres', 'admin.settings.edit' => 'Ajustes'] as $moduleRoute => $label) {
        $response->assertSee('href="'.route($moduleRoute).'"', false)->assertSee($label);
    }

    $response->assertSee('Cerrar sesión')->assertSee('noindex', false);
})->with([
    'agenda' => 'admin.agenda',
    'new appointment' => 'admin.appointments.create',
    'closures' => 'admin.blocks.index',
    'services' => 'admin.services.index',
    'new service' => 'admin.services.create',
    'opening hours' => 'admin.opening-hours.edit',
    'settings' => 'admin.settings.edit',
]);
