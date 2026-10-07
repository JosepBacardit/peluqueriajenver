<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

/*
 * Walks every admin route (except the login screen itself) so a route
 * added outside the auth group is caught: a guest must be sent to the
 * login screen whatever the method.
 */
test('every admin route sends guests to the login screen', function () {
    $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

    $routes = collect(Route::getRoutes())->filter(
        fn ($route) => str_starts_with($route->uri(), 'admin') && $route->uri() !== 'admin/login'
    );

    expect($routes->count())->toBeGreaterThan(10);

    foreach ($routes as $route) {
        $uri = '/'.preg_replace('/\{[^}]+\}/', '1', $route->uri());

        foreach (array_diff($route->methods(), ['HEAD']) as $method) {
            $response = $this->call($method, $uri);

            expect($response->isRedirect(route('login')))
                ->toBeTrue("{$method} {$uri} answered {$response->getStatusCode()} to a guest");
        }
    }
});
