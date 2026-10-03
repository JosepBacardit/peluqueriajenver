<?php

use App\Http\Controllers\Admin\AgendaController;
use App\Http\Controllers\Admin\AppointmentController;
use App\Http\Controllers\Admin\BookingSettingsController;
use App\Http\Controllers\Admin\LoginController;
use App\Http\Controllers\Admin\OpeningHoursController;
use App\Http\Controllers\Admin\ScheduleBlockController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CustomerAppointmentController;
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

// Reservas online (sin caché pública, ver CacheHeaders)
Route::get('/reservas', [BookingController::class, 'index'])->name('reservas');
Route::post('/reservas', [BookingController::class, 'store'])->middleware('throttle:booking-submissions')->name('reservas.store');
Route::get('/cita/{token}', [CustomerAppointmentController::class, 'show'])->name('cita.show');
Route::post('/cita/{token}/cancelar', [CustomerAppointmentController::class, 'cancel'])->middleware('throttle:bookings')->name('cita.cancel');

// Panel de administración del salón (privado, sin registro público)
Route::prefix('admin')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', [LoginController::class, 'create'])->name('login');
        Route::post('/login', [LoginController::class, 'store']);
    });

    Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

    Route::middleware('auth')->name('admin.')->group(function () {
        Route::redirect('/', '/admin/agenda')->name('home');

        Route::get('/agenda', [AgendaController::class, 'index'])->name('agenda');
        Route::get('/citas/crear', [AppointmentController::class, 'create'])->name('appointments.create');
        Route::post('/citas', [AppointmentController::class, 'store'])->name('appointments.store');
        Route::post('/citas/{appointment}/cancelar', [AppointmentController::class, 'cancel'])->name('appointments.cancel');

        Route::resource('servicios', ServiceController::class)
            ->only(['index', 'create', 'store', 'edit', 'update'])
            ->names('services')
            ->parameters(['servicios' => 'service']);

        Route::get('/horario', [OpeningHoursController::class, 'edit'])->name('opening-hours.edit');
        Route::put('/horario', [OpeningHoursController::class, 'update'])->name('opening-hours.update');

        Route::get('/cierres', [ScheduleBlockController::class, 'index'])->name('blocks.index');
        Route::post('/cierres', [ScheduleBlockController::class, 'store'])->name('blocks.store');
        Route::delete('/cierres/{block}', [ScheduleBlockController::class, 'destroy'])->name('blocks.destroy');

        Route::get('/ajustes', [BookingSettingsController::class, 'edit'])->name('settings.edit');
        Route::put('/ajustes', [BookingSettingsController::class, 'update'])->name('settings.update');
    });
});
