<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('pages.home');
})->name('home');

// Servicios principales (por categoría)
Route::get('/color-y-mechas', function () {
    return view('pages.services.color_mechas');
})->name('color-mechas');

Route::get('/corte-y-tratamientos', function () {
    return view('pages.services.corte_tratamientos');
})->name('corte-tratamientos');

Route::get('/peinados-eventos', function () {
    return view('pages.services.peinado_eventos');
})->name('peinados-eventos');

Route::get('/belleza-estetica', function () {
    return view('pages.services.belleza_estetica');
})->name('belleza-estetica');

// Sobre nosotros
Route::get('/sobre-nosotros', function () {
    return view('pages.sobre_nosotros');
})->name('sobre-nosotros');

// Páginas legales (no indexables)
Route::get('/privacidad', function () {
    return view('pages.privacidad');
})->name('privacidad');

Route::get('/avisos-legales', function () {
    return view('pages.avisos-legales');
})->name('avisos-legales');

Route::get('/cookies', function () {
    return view('pages.cookies');
})->name('cookies');
