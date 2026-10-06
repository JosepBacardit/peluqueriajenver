# T055 — Caché del HTML público: `no-cache` con `ETag` en vez de `max-age` largo

- **Tipo:** FEATURE
- **Puntos de referencia:** PRF-141, PRF-142
- **Depende de:** ninguna (toca el mismo horario y el mismo interruptor de T052–T054, pero sin depender de su código)
- **Modelo:** Claude Sonnet 5.5 · **Esfuerzo:** `medium`
- **Motivo:** con `max-age` de 24 h/7 días, un cambio de horario o del interruptor de reserva podía tardar ese tiempo en verse en un navegador con la página ya en caché (riesgo anotado en T053).
- **Estado:** done
- **PR / rama:** `feature/opening-hours-ux`, desde `feature/agenda-service-filter` (`1ce486e`)

## Objetivo

Que las páginas HTML públicas se revaliden en cada visita (`ETag` + 304) en vez de quedarse cacheadas varios días, sin tocar la política de `/admin`, `/cita`, `/reservas` ni `/api`.

## Decisión del usuario (2026-10-06)

`Cache-Control: no-cache` (sin `max-age` largo) con `ETag`; 304 cuando no ha cambiado. `/admin`, `/cita`, `/reservas` y `/api` siguen con su `no-store` de siempre. Estáticos (imágenes, fuentes, `/build/`) siguen con su caché larga de nginx, sin tocar `NGINX-CACHE-CONFIG.md` más que para que deje de instruir cachear el HTML.

## Implementación

`App\Http\Middleware\CacheHeaders`: la rama de «páginas HTML públicas» (antes `public, max-age=86400|604800, must-revalidate`) pasa a:

```php
$response->header('Cache-Control', 'no-cache, private');

if ($response->isSuccessful()) {
    $response->setEtag(md5($response->getContent()));
    $response->isNotModified($request); // 304 + cuerpo vacío si coincide con If-None-Match
}
```

`Response::setEtag()`/`isNotModified()` son de Symfony (ya en el proyecto vía Laravel): `setEtag()` entrecomilla el valor; `isNotModified()` compara con `If-None-Match`, y si coincide deja el `status` en 304 y quita `Content-Type`/`Content-Length`/etc. (nunca `Cache-Control` ni el `ETag` ni las cabeceras de seguridad que se añaden después). El `ETag` sale de un hash del cuerpo ya renderizado, así que cambia solo si cambia el HTML — el horario (T053) y el interruptor (T054) son parte de ese HTML, sin ningún cableado aparte.

Solo para respuestas 2xx (`isSuccessful()`): una redirección (3xx) nunca recibe `ETag` ni pasa por `isNotModified()`, porque si el navegador mandara un `If-None-Match` que coincidiera por azar, Symfony convertiría esa redirección en un 304 — que el navegador no sigue, rompiendo la redirección. `/admin`, `/cita`, `/reservas` y `/api` no entran en esta rama en absoluto (el `isApi()` de siempre), así que no cambian.

`NGINX-CACHE-CONFIG.md`: se quita el bloque `location ~* \.html?$` (en la práctica nunca se aplicaba: las páginas no son ficheros `.html` en disco, las sirve PHP-FPM) y se explica que nginx no debe añadir ni sobrescribir `Cache-Control` para las peticiones a `index.php`, para que el header que pone la aplicación llegue intacto; se añade un `curl` de verificación para el `ETag`/304 de una página HTML, junto al de los estáticos que ya había.

`AGENTS.md`: la nota de la trampa de `CacheHeaders` (antes «excluidas de la caché larga») se actualiza para describir `no-cache`+`ETag` en vez del `max-age` largo, y el paso del *checklist* de despliegue sobre nginx se extiende a todo el HTML, no solo a `/reservas`/`/cita/`.

## Plan de pruebas

`tests/Feature/Booking/BookingPagesCachingTest.php` (reescrito): `/` y `/contacto` llevan `no-cache` y un `ETag`, nunca `max-age=86400`/`604800`; repetir la petición con ese `ETag` en `If-None-Match` → 304 sin cuerpo; el `ETag` cambia si cambia el horario (`OpeningHour`) o el interruptor (`BookingSetting`); una página 404 no lleva `ETag`; `/admin`, `/cita` y `/reservas` siguen con `no-store` y sin `ETag`. Las dos pruebas ya existentes de `/reservas`/`/cita` sin caché pública no cambian.

## Verificación

`docker compose exec -T -u www-data app php artisan test --compact` (suite completa) · `vendor/bin/pint --test` sobre los archivos tocados · `npm run build`.

## Qué comprobar en el navegador (no cubierto por los tests)

- Sin Docker con nginx delante, el middleware es la única verificación posible en este entorno; en producción, repetir las comprobaciones de `NGINX-CACHE-CONFIG.md` (`curl -I`) para confirmar que nginx no añade su propio `Cache-Control` al HTML.

## Fuera de alcance

El interruptor de reserva online (T054, aunque su cambio de contenido es uno de los casos que prueba el `ETag`).
