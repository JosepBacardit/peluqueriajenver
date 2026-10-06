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

/**
 * The data controller's legal name, NIF, contact email and the citas
 * retention period were confirmed by the client on 2026-10-06. Only the
 * email provider (decided with the SMTP setup, see AGENTS.md) is still
 * clearly marked as pending instead of invented.
 */
test('only the email provider is still marked as pending, nothing else invented', function () {
    $this->get(route('privacidad'))
        ->assertSee('[Pendiente de confirmar: proveedor de correo electrónico]')
        ->assertDontSee('[Pendiente de confirmar: nombre o razón social del titular]')
        ->assertDontSee('[Pendiente de confirmar: NIF]')
        ->assertDontSee('[Pendiente de confirmar: email de contacto]')
        ->assertDontSee('[Pendiente de confirmar: plazo de conservación de las citas]');
});

test('no public page shows any pending marker other than the email provider\'s, on /privacidad', function (string $routeName) {
    $html = $this->get(route($routeName))->getContent();

    expect(substr_count($html, 'Pendiente de confirmar'))->toBe($routeName === 'privacidad' ? 1 : 0);
})->with(['privacidad', 'avisos-legales', 'cookies', 'home', 'contacto', 'reservas']);

test('no public page shows the invented contact email', function (string $routeName) {
    expect($this->get(route($routeName))->getContent())->not->toContain('@email.com');
})->with(['privacidad', 'avisos-legales', 'cookies', 'home', 'contacto', 'reservas']);

/**
 * Coordinator's explicit ask: the confirmed titular/NIF/email appear on
 * both the privacy policy and the legal notice.
 */
test('the confirmed legal data appears on both the privacy policy and the legal notice', function (string $routeName) {
    $this->get(route($routeName))
        ->assertSee('Isabel Lechuga Valverde')
        ->assertSee('53650299Q')
        ->assertSee('peluqueriajenver@gmail.com');
})->with(['privacidad', 'avisos-legales']);
