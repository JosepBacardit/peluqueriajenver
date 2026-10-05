<?php

/*
 * Fonts used to be loaded from fonts.googleapis.com/fonts.gstatic.com,
 * sending every visitor's IP to Google on every page view. They are now
 * self-hosted from public/fonts/ (see resources/css/app.css), so no request
 * should ever reach Google again.
 */
test('the served HTML never references Google Fonts', function (string $routeName) {
    $html = $this->get(route($routeName))->assertOk()->getContent();

    expect($html)
        ->not->toContain('fonts.googleapis.com')
        ->not->toContain('fonts.gstatic.com')
        ->toContain('/fonts/playfair-display-latin-400-normal.woff2')
        ->toContain('/fonts/inter-latin-400-normal.woff2');
})->with(['home', 'contacto', 'cookies', 'privacidad']);
