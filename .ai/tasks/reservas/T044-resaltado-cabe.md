# T044 — Resaltado «Cabe» en cada carril libre

- **Tipo:** FEATURE
- **Puntos de referencia:** PRF-124
- **Depende de:** T042, T043
- **Modelo:** Claude Opus 5.5 · **Esfuerzo:** `high`
- **Estado:** done
- **PR / rama:** `feature/agenda-service-filter`

## Objetivo

Con un servicio elegido (T043), resaltar en la rejilla cada media hora libre donde cabe completo, en cada carril libre de esa media hora (la disponibilidad es de capacidad, no de un carril en concreto), sin ninguna consulta añadida más allá de la que T043 ya hace para el `<select>`.

## Implementación

`DayTimeline::tappableFreeMinutes(array $timeline): array` recorre un `build()` ya construido y devuelve, deduplicados, los minutos de inicio de cada segmento libre y tocable — exactamente las candidatas que la rejilla ya ofrece como zona táctil (PRF-114).

`DayTimeline::markServiceFit(array $timeline, array $fittingMinutes): array` marca `'fits' => true` en cada uno de esos segmentos cuyo minuto está en `$fittingMinutes`; el resto queda sin tocar, así que la vista trata una clave `fits` ausente igual que `false`.

`AgendaController::markServiceFit()` (privado) ata ambos extremos con `AvailabilityCalculator::fittingStartMinutes()` (T042): sin servicio, no hace nada; con uno, usa los mismos `$ranges`/`$appointments`/`$blocks`/`$capacity` que `dayData()`/`weekData()` ya cargaron. Se llama una vez por día (`dayData()`) o una vez por cada uno de los 7 días de la semana (`weekData()`), nunca con una consulta nueva.

`_timeline-column.blade.php`: el enlace de un hueco libre con `fits` añade un borde (`border-2 border-gold`) y un fondo (`bg-gold/15`) — nunca solo un color distinto del normal — más el texto «Cabe» cuando `! $compact` (vista Día) y siempre `, cabe {{ $servicio->name }}` al final de su `aria-label` (Semana se apoya solo en eso, sus columnas no tienen sitio para el texto).

## Plan de pruebas

`tests/Feature/Admin/AgendaServiceFilterTest.php`: un servicio de 2 h cabe de 09:00 a 17:00 (ambos carriles) y no de 17:30 en adelante; capacidad reducida a 1 con una cita real hace que un hueco que la solaparía tampoco cabe, en el único carril que hay; Semana lleva el `aria-label` pero nunca el texto visible (comprobado con un recuento exacto: solo la rejilla móvil de Día puede mostrarlo); un `servicio` inválido o inactivo no resalta nada; recuento fijo de consultas en Día y Semana con un servicio elegido, con y sin citas.

## Verificación

`docker compose exec -T -u www-data app php artisan test --compact` (suite completa) · `docker compose exec -T -u www-data app vendor/bin/pint --dirty --format agent` · `docker compose exec -T node npm run build`.

## Fuera de alcance

El selector y su persistencia (T043), la preselección en «Nueva cita» (T045).
