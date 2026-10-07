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

/**
 * Below 768px (md:hidden) the panel shows a hamburger button that opens a
 * dropdown menu with the same five modules as the top nav (which becomes
 * hidden md:flex), so the menu can grow well past 5 apartados without
 * running out of room the way a bottom tab bar would (decision of
 * 2026-10-05, replaces the bottom bar). The active module is marked in
 * both navs (one of which is display:none at any given width).
 */
test('the panel shows a hamburger menu for phones with the five modules and the active one marked', function () {
    $this->actingAs(User::factory()->create());

    $html = $this->get(route('admin.services.index'))->assertOk()->getContent();

    // The desktop nav is now hidden below md, the hamburger only below md.
    expect($html)->toContain('hidden md:flex')->toContain('md:hidden');

    // The button: 44x44, aria-expanded/aria-controls/aria-label.
    expect($html)->toContain('id="admin-menu-btn"')
        ->toContain('w-11 h-11')
        ->toContain('aria-controls="admin-menu"')
        ->toMatch('/aria-expanded="(true|false)"/')
        ->toMatch('/aria-label="(Abrir|Cerrar) menú"/');

    // The dropdown menu itself: scrollable, so it never runs off the
    // screen once the panel grows to 10-12 apartados.
    expect($html)->toContain('id="admin-menu"')->toContain('overflow-y-auto');

    // Both navs render the 5 modules and mark "Servicios" as active, each
    // link at least 44px tall in the dropdown.
    expect(substr_count($html, '>Servicios<'))->toBeGreaterThanOrEqual(1);
    expect($html)->toContain('Servicios</a>');
    expect(substr_count($html, 'aria-current="page"'))->toBe(2);
    expect($html)->toContain('min-h-11');

    // "Mi cuenta" and "Cerrar sesión" are inside the dropdown, at the end,
    // visually separated from the modules (a border above "Mi cuenta").
    expect($html)->toMatch('/<a href="[^"]*cuenta"[^>]*class="[^"]*border-t[^"]*"[^>]*>Mi cuenta.*<form method="POST" action="[^"]*logout".*Cerrar sesión/s');

    // Every module has its own icon, hidden from assistive tech (the
    // visible label is the accessible name).
    expect(substr_count($html, 'aria-hidden="true"'))->toBeGreaterThanOrEqual(5);

    // The hamburger's own open/close icons are also decorative.
    expect($html)->toContain('id="admin-menu-icon-open"')->toContain('id="admin-menu-icon-close"');
});

/**
 * Progressive enhancement (justification for "sin JS, el menú tiene que
 * seguir siendo accesible"): the dropdown has no `hidden` class in the
 * markup and the button starts "expanded", so without JavaScript the menu
 * is simply always visible — every module and "Cerrar sesión" stay
 * reachable with no button dependency. The inline script (present on every
 * authenticated page) is what collapses it once JS does run.
 */
test('the dropdown menu is visible by default, so it still works without JavaScript', function () {
    $this->actingAs(User::factory()->create());

    $html = $this->get(route('admin.agenda'))->assertOk()->getContent();

    preg_match('/<nav id="admin-menu"[^>]*class="([^"]*)"/', $html, $match);

    // "md:hidden" (collapses only below the md breakpoint) is expected;
    // a standalone "hidden" token (display:none at every width) is not.
    expect(explode(' ', $match[1] ?? ''))->not->toContain('hidden');
    expect($html)->toContain('aria-expanded="true"');
    expect($html)->toContain('<script>');
});
