<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('pages.home');
})->name('home');

// Servicios principales (destacados)
Route::get('/mechas', function () {
    return view('pages.services.balayage_mechas');
})->name('mechas');

Route::get('/color', function () {
    return view('pages.services.color');
})->name('color');

Route::get('/alisados', function () {
    return view('pages.services.alisados');
})->name('alisados');

Route::get('/peinados-eventos', function () {
    return view('pages.services.peinados_eventos');
})->name('peinados');

// Otros servicios
Route::get('/corte-de-pelo', function () {
    return view('pages.services.corte');
})->name('corte');

Route::get('/cabello-afro', function () {
    return view('pages.services.afro_rizos');
})->name('afro');

Route::get('/servicios-complementarios', function () {
    return view('pages.services.servicios_complementarios');
})->name('complementarios');
