<?php

use App\Http\Controllers\Admin\BookingSettingsController;
use App\Http\Controllers\Admin\LoginController;
use App\Http\Controllers\Admin\OpeningHoursController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('pages.home');
})->name('home');

// SEO Sitemap
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');

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

// Contacto
Route::get('/contacto', function () {
    return view('pages.contacto');
})->name('contacto');

// Panel de administración del salón (privado, sin registro público)
Route::prefix('admin')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', [LoginController::class, 'create'])->name('login');
        Route::post('/login', [LoginController::class, 'store']);
    });

    Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

    Route::middleware('auth')->name('admin.')->group(function () {
        Route::view('/', 'admin.home')->name('home');

        Route::resource('servicios', ServiceController::class)
            ->only(['index', 'create', 'store', 'edit', 'update'])
            ->names('services')
            ->parameters(['servicios' => 'service']);

        Route::get('/horario', [OpeningHoursController::class, 'edit'])->name('opening-hours.edit');
        Route::put('/horario', [OpeningHoursController::class, 'update'])->name('opening-hours.update');

        Route::get('/ajustes', [BookingSettingsController::class, 'edit'])->name('settings.edit');
        Route::put('/ajustes', [BookingSettingsController::class, 'update'])->name('settings.update');
    });
});
