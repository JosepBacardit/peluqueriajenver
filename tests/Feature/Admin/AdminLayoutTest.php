<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

/**
 * Every admin screen shares the same module navigation and a logout
 * button. Modules whose screens are not built yet are simply absent.
 */
test('every admin screen shows the module navigation and the logout button', function (string $routeName) {
    $this->actingAs(User::factory()->create());

    $response = $this->get(route($routeName))->assertOk();

    foreach (['admin.agenda' => 'Agenda', 'admin.services.index' => 'Servicios', 'admin.opening-hours.edit' => 'Horario', 'admin.blocks.index' => 'Cierres', 'admin.settings.edit' => 'Ajustes'] as $moduleRoute => $label) {
        if (Route::has($moduleRoute)) {
            $response->assertSee('href="'.route($moduleRoute).'"', false)->assertSee($label);
        }
    }

    $response->assertSee('Cerrar sesión')->assertSee('noindex', false);
})->with([
    'home' => 'admin.home',
    'services' => 'admin.services.index',
    'new service' => 'admin.services.create',
    'opening hours' => 'admin.opening-hours.edit',
    'settings' => 'admin.settings.edit',
]);
