<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

// The booking page reads services from the database.
uses(RefreshDatabase::class);

it('serves public pages without prices, self-reported reviews or the dead contact form', function (string $routeName) {
    $response = $this->get(route($routeName));

    $response->assertOk();

    $html = strtolower($response->getContent());

    expect($html)->not->toContain('€');
    expect($html)->not->toContain('pricerange');
    expect($html)->not->toContain('aggregaterating');
    expect($html)->not->toContain('"review"');
    expect($html)->not->toContain('tarifas orientativas');
    expect($html)->not->toContain('precios especiales');
    expect($html)->not->toContain('enviar mensaje');
})->with([
    'home',
    'color-mechas',
    'corte-tratamientos',
    'peinados-eventos',
    'belleza-estetica',
    'contacto',
    'reservas',
]);
