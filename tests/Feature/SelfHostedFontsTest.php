<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Merged in from main (2026-10-06, PR #8): every page using
// layouts/app.blade.php now reads the schedule (footer.blade.php,
// OpeningHoursSummary) and the online-booking switch from the database,
// so even this file's unrelated tests need a migrated connection.

/*
 * Fonts used to be loaded from fonts.googleapis.com/fonts.gstatic.com,
 * sending every visitor's IP to Google on every page view. They are now
 * self-hosted from resources/fonts/, built by Vite (see
 * resources/css/app.css), so no request should ever reach Google again.
 *
 * The preloaded filename is matched loosely (optional "-<hash>" before
 * .woff2) because Vite fingerprints it only in `npm run build`; under
 * `npm run dev` (as this suite runs against in this project's Docker setup,
 * since the node service keeps the Vite dev server up) the asset keeps its
 * plain source filename instead. Either way it must never be the
 * unhashed, un-Vite-processed /fonts/... path from a previous version of
 * this change, which 404ed under the dev server (see
 * .ai/reviews/fonts-applied.md): that server serves app.css from its own
 * origin, so a path starting with "/" never reached this app.
 */
test('the served HTML never references Google Fonts', function (string $routeName) {
    $html = $this->get(route($routeName))->assertOk()->getContent();

    expect($html)
        ->not->toContain('fonts.googleapis.com')
        ->not->toContain('fonts.gstatic.com')
        ->not->toContain('"/fonts/');

    foreach (['playfair-display-latin-700-normal', 'inter-latin-400-normal'] as $font) {
        expect(preg_match('/'.preg_quote($font, '/').'(-\w+)?\.woff2/', $html))
            ->toBe(1, "Expected to find a preload for {$font}.woff2 (optionally hashed) in the HTML.");
    }
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
 * Only Playfair Display 700 and Inter 400 are used above the fold: the <h1>
 * is the only Playfair element in every hero section (home and the 4
 * service pages), always font-serif + font-bold (Playfair 400/regular is
 * not used anywhere on the site), and the hero's body text/nav/buttons use
 * Inter at its default (400) weight. Preloading any other weight would
 * just delay these two.
 */
test('exactly Playfair Display 700 and Inter 400 are preloaded, not Playfair 400', function () {
    $html = $this->get(route('home'))->assertOk()->getContent();

    preg_match_all('/<link rel="preload" as="font"[^>]*href="([^"]+)"/', $html, $matches);
    $hrefs = collect($matches[1]);

    expect($hrefs)->toHaveCount(2);
    expect($hrefs->contains(fn ($href) => str_contains($href, 'playfair-display-latin-700-normal')))->toBeTrue();
    expect($hrefs->contains(fn ($href) => str_contains($href, 'inter-latin-400-normal')))->toBeTrue();
    expect($hrefs->contains(fn ($href) => str_contains($href, 'playfair-display-latin-400-normal')))->toBeFalse();
});

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

/*
 * Review fonts-applied.md finding 1: --font-sans/--font-serif are declared
 * twice (the critical <style> in layouts/app.blade.php and @theme in
 * resources/css/app.css). The critical one is unlayered, so by the Cascade
 * Layers spec it always wins over @theme (which Tailwind compiles into
 * @layer theme): if the two ever diverge, the @theme copy silently stops
 * describing what's actually rendered. Reads both source files directly
 * (no HTTP, no Vite dev/build dependency) so this holds regardless of
 * which Vite mode is running.
 */
test('the two --font-sans/--font-serif declarations stay identical', function () {
    $critical = file_get_contents(resource_path('views/layouts/app.blade.php'));
    $theme = file_get_contents(resource_path('css/app.css'));

    foreach (['--font-sans', '--font-serif'] as $variable) {
        preg_match('/'.preg_quote($variable, '/').":\s*([^;]+);/", $critical, $criticalMatch);
        preg_match('/'.preg_quote($variable, '/').":\s*([^;]+);/", $theme, $themeMatch);

        expect($criticalMatch[1] ?? null)->not->toBeNull();
        expect($themeMatch[1] ?? null)->not->toBeNull();
        expect(trim($themeMatch[1]))->toBe(trim($criticalMatch[1]));
    }
});
