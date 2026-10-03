<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the privacy policy explains how booking data is handled', function () {
    $this->get(route('privacidad'))
        ->assertOk()
        ->assertSeeInOrder([
            'Responsable del tratamiento',
            'Datos que tratamos',
            'Finalidad',
            'Base legal',
            'Destinatarios y encargados del tratamiento',
            'Plazo de conservación',
            'Derechos',
        ])
        ->assertSee('reservas')
        ->assertSee('medidas precontractuales')
        ->assertSee('OVH')
        ->assertSee('Agencia Española de Protección de Datos');
});

test('data the client has not confirmed is clearly marked as pending instead of invented', function () {
    $this->get(route('privacidad'))
        ->assertSee('[Pendiente de confirmar: nombre o razón social del titular]')
        ->assertSee('[Pendiente de confirmar: NIF]')
        ->assertSee('[Pendiente de confirmar: email de contacto]')
        ->assertSee('[Pendiente de confirmar: plazo de conservación de las citas]')
        ->assertSee('[Pendiente de confirmar: proveedor de correo electrónico]');
});

test('no public page shows the invented contact email', function (string $routeName) {
    expect($this->get(route($routeName))->getContent())->not->toContain('@email.com');
})->with(['privacidad', 'avisos-legales', 'cookies', 'home', 'contacto', 'reservas']);
