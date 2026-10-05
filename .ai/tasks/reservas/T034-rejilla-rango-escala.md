# T034 — Rango y escala de la rejilla horaria

- **Tipo:** FEATURE
- **Puntos de referencia:** PRF-108
- **Depende de:** T033
- **Modelo:** Claude Sonnet 5.5 · **Esfuerzo:** `medium`
- **Motivo:** base matemática (sin consultas nuevas más allá de una barata a `opening_hours`) que usarán T035-T039; sin lógica de negocio compartida.
- **Estado:** done
- **PR / rama:** `feature/agenda-timeline-grid`

## Objetivo

Calcular, una vez por petición, el rango horario que comparte toda la rejilla (Día y las 7 columnas de Semana) y la escala en píxeles, para que todas las columnas usen el mismo eje de horas.

## Cambios

`app/Booking/DayTimeline.php` (nuevo):
- `PX_PER_HOUR = 88` (44 px por media hora, PRF-115).
- `weekBounds(): array{start:int, end:int}` — apertura más temprana y cierre más tardío de toda la semana (`OpeningHour`), en minutos desde medianoche, redondeados hacia fuera a la hora; con la tabla vacía, cae a 09:00-19:00 para no dividir por cero.
- `pxFromMinutes(int $minutes): int` — conversión a píxeles con `round()`; cada segmento debe calcular su alto como la diferencia de dos llamadas a este método (su inicio y su fin), nunca `round()` de la duración por separado, para que los segmentos consecutivos no dejen un hueco ni se solapen por redondeo.

## Evidencia

`tests/Feature/Booking/DayTimelineTest.php`: `weekBounds()` con el horario por defecto, con horas no redondas y sin ninguna fila; `pxFromMinutes()` en varios valores.

## Verificación

`docker compose exec -T -u www-data app php artisan test --compact --filter=DayTimelineTest` → 4 tests en verde · `docker compose exec -T -u www-data app vendor/bin/pint --dirty --format agent` → `{"result":"pass"}`.
