# T018 — Botones «Reservar cita» del inicio y las páginas de servicio

- **Tipo:** SIMPLE
- **Puntos de referencia:** PRF-076
- **Depende de:** T009 (CTAs de cabecera, mapa del sitio y datos estructurados ya apuntan a `/reservas`)
- **Modelo:** Claude Sonnet 5 · **Esfuerzo:** `low`
- **Motivo:** cambio de enlace, localizado a 5 archivos de vista y un test existente, sin lógica nueva.
- **Estado:** done
- **PR / rama:** `feature/booking-admin-tweaks`, apilada sobre `feature/booking-review-fixes`

## Contexto

`resources/views/pages/home.blade.php:40` (hero, texto `home.hero.cta_primary` = «Reservar cita →») y las 4 páginas de servicio (`resources/views/pages/services/{belleza_estetica,color_mechas,corte_tratamientos,peinado_eventos}.blade.php`, líneas 35-37 de cada una, mismo texto «Reservar cita →») usan `href="tel:+34633912050"`. Los demás `tel:` de esas páginas («Solicitar Diagnóstico», «Llamar ahora: 633 912 050», «Llamar: 633 912 050», el teléfono de contacto) no dicen «Reservar» y quedan fuera de alcance. `partials/schema-local.blade.php` ya apunta a `route('reservas')` desde T009 (comprobado, no requiere cambios). `tests/Feature/BookingLinksAndSeoTest.php:11` fija hoy `expect($bookingLinks)->toBe(2)` contando `href="{{ route('reservas') }}" class="btn-gold` en la portada (cabecera desktop + móvil); al cambiar el botón del hero pasará a 3.

## Plan

1. `resources/views/pages/home.blade.php:40`: `href="tel:+34633912050"` → `href="{{ route('reservas') }}"`.
2. Mismo cambio en el hero de las 4 páginas de servicio.
3. `tests/Feature/BookingLinksAndSeoTest.php:11`: `toBe(2)` → `toBe(3)`.
4. Nuevo test que recorra las 4 páginas de servicio y confirme que su CTA «Reservar cita →» usa `route('reservas')` y que el CTA inferior de cada página («Llamar: ...») sigue en `tel:+34633912050`.

## Verificación

`docker compose exec -T -u www-data app php artisan test --compact --filter=BookingLinksAndSeo` y la suite completa · `docker compose exec -T -u www-data app vendor/bin/pint --dirty --format agent`.

## Riesgos

Ninguno: solo cambia el destino de un enlace ya existente en vistas públicas sin lógica de servidor.
