<?php

use App\Http\Controllers\DiscountCodeController;
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

Route::post('/descuento', [DiscountCodeController::class, 'store'])
    ->name('discount.store')
    ->middleware('throttle:3,60');
