<?php

/*
 * robots.txt used to disallow /admin, which stops Google from ever
 * crawling it and therefore from ever reading the noindex meta tag/header
 * already served there — a linked admin URL could still show up indexed
 * with no content. Google must be allowed to crawl it so it can see the
 * noindex instead (App\Http\Middleware\NoIndexAdmin sends the matching
 * X-Robots-Tag header on every /admin response).
 */
test('robots.txt does not disallow /admin', function () {
    $robotsTxt = file_get_contents(public_path('robots.txt'));

    expect($robotsTxt)->not->toContain('Disallow: /admin');
});
