# T059 — Resolver la revisión de T052–T055

- **Tipo:** FEATURE
- **Puntos de referencia:** PRF-141 (reforzado)
- **Depende de:** T052–T055 (el PR que revisó `.ai/reviews/opening-hours-ux.md`)
- **Modelo:** Claude Opus 5.5 · **Esfuerzo:** `medium`
- **Motivo:** resolver los 5 hallazgos (2 medios, 3 bajos) de la revisión independiente, más un hallazgo propio del coordinador (N1).
- **Estado:** done
- **PR / rama:** `feature/opening-hours-ux`, desde `feature/agenda-service-filter` (`1ce486e`)

## Hallazgos resueltos

- **M1.** Una petición `HEAD` a una página pública recibía `no-store` sin `ETag`, no el mismo `no-cache, private` + `ETag` que la `GET` equivalente. La causa real, no evidente desde la propia recomendación de la revisión: `Illuminate\Routing\Router::prepareResponse()` llama a `Response::prepare($request)` **antes** de que `CacheHeaders` (middleware global, el más externo) vea la respuesta, y `prepare()` vacía el cuerpo en una petición `HEAD` (RFC 2616 §14.13) — simplemente añadir `HEAD` a la condición de método habría calculado el `ETag` de un cuerpo ya vacío (`md5('')`, siempre el mismo valor, nunca el real). La solución: `CacheHeaders` cambia la petición a `GET` solo para la llamada a `$next($request)` (así `prepare()` no vacía nada), calcula el `ETag` del cuerpo real, restaura el método a `HEAD` y vacía el cuerpo él mismo al final, conservando `Content-Length` — exactamente lo que `prepare()` habría hecho.
- **M2.** El interruptor no se probaba a través de su propio formulario. `BookingSettingsManagementTest.php` añade tres tests que pasan por `PUT admin.settings.update` real: ausente (sigue como estaba), `0` (se apaga), `1` (se enciende).
- **L1.** El `ETag` depende, sin red de seguridad, de que ninguna página pública cacheada lleve contenido de sesión. Documentado en `CacheHeaders`; `BookingPagesCachingTest.php` añade un test que busca `_token`/`csrf-token` en el HTML de `/`, `/contacto` y las 4 páginas de servicio, y otro que repite la misma petición tres veces en la misma sesión y comprueba que el `ETag` no cambia.
- **L2.** `private` (no `public`) no estaba justificado. Documentado en `CacheHeaders`: cada respuesta lleva `Set-Cookie` de sesión, así que una caché compartida no debe reutilizar la copia de un visitante para otro; si se pone un CDN delante de nginx algún día, habría que revisarlo a propósito.
- **L3.** La vista Mes de la agenda gana una consulta a `booking_settings` que antes no hacía. Justificado, no corregido: el aviso del interruptor está pensado para verse también en Mes, así que esa lectura hace falta igual sin importar cómo se organice el código. Documentado en `AgendaController`.
- **N1 (hallazgo propio del coordinador).** El enlace «Ver más formas de contactar» de la página de reserva desactivada medía 24 px de alto. `resources/views/pages/reservas-disabled.blade.php` le añade `inline-flex items-center justify-center min-h-11`, igual que los botones de «Llamar»/«WhatsApp» de la misma página.

## Plan de pruebas

`tests/Feature/Booking/BookingPagesCachingTest.php`: 7 tests nuevos (M1 ×3, L1 ×2, más la comprobación de cuerpo vacío en `HEAD`). `tests/Feature/Admin/BookingSettingsManagementTest.php`: 3 tests nuevos (M2). `tests/Feature/Booking/OnlineBookingToggleTest.php`: la prueba de la página desactivada ahora cuenta exactamente 3 `min-h-11` (los dos botones y el enlace, N1).

## Verificación

`docker compose exec -T -u www-data app php artisan test --compact` (suite completa) · `vendor/bin/pint --test` sobre los archivos tocados · `npm run build`.

## Fuera de alcance

Cualquier cambio de comportamiento no pedido por los hallazgos (p. ej., mover la caché a `public`, o quitar la lectura de Mes a costa de no mostrar el aviso ahí).
