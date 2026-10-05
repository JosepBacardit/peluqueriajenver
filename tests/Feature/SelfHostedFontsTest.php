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
})->with([
    'home',
    'contacto',
    'cookies',
    'privacidad',
    'color-mechas',
    'corte-tratamientos',
    'peinados-eventos',
    'belleza-estetica',
    'avisos-legales',
]);

/*
 * Found in the browser, not by the independent reviewer (see
 * .ai/reviews/self-hosted-fonts.md): the critical <style> set the fallback
 * stack as the literal font-family for body/h1-h6, never
 * var(--font-sans)/var(--font-serif). Being plain, unlayered CSS, it always
 * won over Tailwind's @layer utilities, so Playfair Display and Inter were
 * downloaded and preloaded but never actually rendered anywhere on the
 * site, on every route, in production too. This guards against reverting
 * to a literal fallback instead of the variable.
 */
test('the critical CSS sets body and headings to the font variables, not a hardcoded fallback', function (string $routeName) {
    $html = $this->get(route($routeName))->assertOk()->getContent();

    expect($html)
        ->toContain('font-family: var(--font-sans);')
        ->toContain('font-family: var(--font-serif);');
})->with(['home', 'contacto']);
