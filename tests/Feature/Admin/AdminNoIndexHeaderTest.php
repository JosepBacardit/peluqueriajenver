<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * robots.txt disallowing /admin used to stop Google from ever crawling it,
 * so the <meta name="robots" content="noindex, nofollow"> already rendered
 * by layouts/admin.blade.php was never actually read: a linked admin URL
 * could still be indexed with no content shown. The X-Robots-Tag header
 * is read without crawling the page body, and must cover every /admin
 * response, including redirects that never render that layout at all
 * (e.g. a guest sent to the login screen).
 */
test('every admin response carries the X-Robots-Tag noindex header', function (string $path, bool $authenticated) {
    if ($authenticated) {
        $this->actingAs(User::factory()->create());
    }

    $response = $this->get($path);

    expect($response->headers->get('X-Robots-Tag'))->toBe('noindex, nofollow');
})->with([
    'login screen (guest)' => ['/admin/login', false],
    'guest redirected from /admin to the login screen' => ['/admin', false],
    'authenticated admin page' => ['/admin/agenda', true],
]);

test('the admin login POST response also carries the X-Robots-Tag noindex header', function () {
    $response = $this->post(route('login'), ['email' => 'nobody@example.test', 'password' => 'wrong']);

    expect($response->headers->get('X-Robots-Tag'))->toBe('noindex, nofollow');
});

test('a public page does not carry the X-Robots-Tag header', function () {
    $response = $this->get('/');

    expect($response->headers->get('X-Robots-Tag'))->toBeNull();
});
