# T038 — Línea de «ahora» y desplazamiento automático

- **Tipo:** FEATURE
- **Puntos de referencia:** PRF-116, PRF-117
- **Depende de:** T036
- **Modelo:** Claude Sonnet 5.5 · **Esfuerzo:** `medium`
- **Motivo:** una posición más sobre el mismo eje de T034-T036, más un *script* mínimo (el único JS nuevo de toda la rejilla).
- **Estado:** done
- **PR / rama:** `feature/agenda-timeline-grid`

## Objetivo

Mostrar una línea en la hora actual cuando se ve el día de hoy, y desplazar la rejilla hasta ella al cargar la página (o dejarla en la apertura si no aplica).

## Cambios

`app/Booking/DayTimeline.php`: nuevo `nowLineTop(CarbonImmutable $day, int $gridStart, int $gridEnd, CarbonImmutable $now): ?int` — `null` si `$day` no es hoy o si `$now` cae fuera de `[$gridStart, $gridEnd)`; si no, el desplazamiento en píxeles desde el inicio de la rejilla.

`app/Http/Controllers/Admin/AgendaController.php`: `dayData()` y cada día de `weekData()` añaden `nowLineTop`.

`resources/views/admin/agenda/_timeline.blade.php`: el contenedor de la rejilla pasa a desplazable internamente (`overflow-y-auto`, `max-height: 70vh`, para que un día largo no se trague toda la página) con `id="timeline-scroll"`; si `$nowLineTop` no es `null`, una línea roja absoluta (`aria-hidden`, no ocupa ningún hueco de tabulación) con el texto «Ahora» junto a ella (no depende solo del color), y un `<script>` mínimo que hace `scrollTop` hasta esa posición al cargar — el único JS nuevo de toda la rejilla, como pedía el encargo.

`resources/views/admin/agenda/_day.blade.php`, `_week.blade.php`: pasan `nowLineTop` igual que ya pasan `timeline`/`gridStart`/`gridEnd`, para que la vista Semana en el móvil (que reutiliza `_day.blade.php`) también lo tenga.

## Evidencia

`tests/Feature/Booking/DayTimelineTest.php` (+3): `nowLineTop` da el desplazamiento correcto hoy dentro del rango, `null` si el día no es hoy, `null` si «ahora» cae antes de abrir/al cerrar/mucho después.

`tests/Feature/Admin/AgendaTimelineGridTest.php` (+3): hoy, con «ahora» en horario, muestra la línea en el `top` correcto, el texto «Ahora» y el *script* con el mismo desplazamiento; otro día no muestra ni línea ni *script*; hoy pero antes de la apertura tampoco.

## Verificación

`docker compose exec -T -u www-data app php artisan test --compact --filter=AgendaTimelineGridTest` → 11 tests en verde (29 aserciones) · suite completa: **463 tests en verde** (1734 aserciones; partía de 455) · `docker compose exec -T -u www-data app vendor/bin/pint --dirty --format agent` → `{"result":"pass"}` · `docker compose exec -T node npm run build` → sin errores (un aviso informativo de tiempos del plugin de Tailwind, no relacionado); **no se ha reiniciado el contenedor `node`**.

Pendiente a mano (lo hace el coordinador): comprobar en el navegador que la rejilla se abre ya desplazada a la hora actual, sin tener que bajar a mano.
