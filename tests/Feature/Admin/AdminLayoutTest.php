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

/**
 * The shared button components must give every "Guardar"/"Entrar"/etc.
 * action at least a 44px touch target (PRF-089), without the pages that
 * override the padding for their own CTAs (home, contacto, service pages)
 * having to change anything.
 */
test('the shared button styles meet the 44px minimum touch target', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    preg_match('/\.btn-gold\s*\{([^}]*)\}/', $css, $goldMatch);
    preg_match('/\.btn-outline\s*\{([^}]*)\}/', $css, $outlineMatch);

    expect($goldMatch)->not->toBeEmpty('`.btn-gold` is missing from app.css');
    expect($outlineMatch)->not->toBeEmpty('`.btn-outline` is missing from app.css');

    foreach (['btn-gold' => $goldMatch[1], 'btn-outline' => $outlineMatch[1]] as $name => $body) {
        expect($body)->toMatch('/\bmin-h-11\b/', "`.$name` must set min-h-11 (44px)");
        expect($body)->toMatch('/\binline-flex\b/', "`.$name` must be a flex container for min-h-11 to apply");
    }
});

/**
 * "Cancelar cita" and "Eliminar" are destructive and must look and feel
 * different from "Editar"/"Guardar", with their own 44px touch target
 * (PRF-089, PRF-094).
 */
test('the danger button style meets the 44px minimum touch target', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    preg_match('/\.btn-danger-outline\s*\{([^}]*)\}/', $css, $match);

    expect($match)->not->toBeEmpty('`.btn-danger-outline` is missing from app.css');
    expect($match[1])->toMatch('/\bmin-h-11\b/')->toMatch('/\binline-flex\b/')->toMatch('/\bred-/');
});
