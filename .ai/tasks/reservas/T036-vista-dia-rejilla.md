# T036 — Vista Día: rejilla horaria

- **Tipo:** FEATURE
- **Puntos de referencia:** PRF-111, PRF-112, PRF-113, PRF-115
- **Depende de:** T035
- **Modelo:** Claude Sonnet 5.5 · **Esfuerzo:** `high`
- **Motivo:** compone el rango/escala (T034) y los carriles (T035) en la rejilla completa, con un algoritmo de particionado por intervalos elementales (tramos, cierres y citas) para decidir banda de todo el ancho frente a carriles por columna.
- **Estado:** done
- **PR / rama:** `feature/agenda-timeline-grid`

## Objetivo

Construir la rejilla horaria completa de un día (bandas Cerrado/Fuera de horario/Cierre, citas como bloques por carril) y mostrarla en la vista Día, con la lista de tarjetas de siempre debajo, enlazada por ancla.

## Cambios

`app/Booking/DayTimeline.php` — nuevo método `build()`:
- Un día sin tramos: una sola banda «Cerrado» de toda la rejilla.
- Con tramos: separa el rango de la rejilla en «fuera de horario» (huecos antes/después/entre tramos), «cierre» (tramos de un cierre puntual sin ninguna cita superviviente) y piezas «abiertas». El reparto se calcula por intervalos elementales (límites = bordes de tramo, de cierres y de citas), de modo que un cierre con una cita que sobrevive (PRF-022) no la oculta: ese tramo se queda «abierto», con la cita en su carril y el resto sombreado «cierre-parcial» en los demás carriles.
- Dentro de cada pieza abierta, cada carril es una secuencia en flujo normal (sin posicionamiento absoluto): hueco libre, cita o cierre-parcial, con el alto exacto de cada segmento (diferencia de dos `pxFromMinutes()`), así que los carriles quedan alineados al eje de horas compartido sin más cálculo.
- Un hueco libre de menos de 30 minutos no se marca `tappable` (PRF-114, que lo usará T037).
- `weekBounds()` pasa a recibir la colección de tramos ya cargada (en vez de consultar ella misma), para que `AgendaController` cargue `opening_hours` una sola vez y la reutilice tanto para el rango compartido como para los tramos del día.

`app/Http/Controllers/Admin/AgendaController.php`:
- `dayData()` carga también todos los tramos (`OpeningHour`) y la capacidad (`BookingSetting`), y llama a `DayTimeline::build()` — 4 consultas en total para Día (antes 2), ninguna repetida por bloque o por carril.
- `weekData()` hace lo mismo para cada uno de los 7 días (reutilizando las citas/bloques ya cargados de la semana, sin consulta nueva por día) para que la vista Semana en el móvil, que reutiliza `_day.blade.php`, no se rompa.

Vistas nuevas:
- `resources/views/admin/agenda/_timeline-hour-axis.blade.php`: eje de horas compartido, una fila de 44 px por media hora.
- `resources/views/admin/agenda/_timeline-column.blade.php`: una columna de un día (bandas y carriles), con `$compact` para ocultar el servicio en contextos estrechos (Semana, T039).
- `resources/views/admin/agenda/_timeline.blade.php`: compone eje + columna para Día.

`resources/views/admin/agenda/_day.blade.php`: incluye la rejilla entre el aviso de cierres y la lista de tarjetas; cada tarjeta lleva `id="cita-{id}"` para que un bloque de la rejilla pueda enlazar a ella (`href="#cita-{id}"`, PRF-115) sin página ni panel nuevos.

## Evidencia

`tests/Feature/Booking/DayTimelineBuildTest.php` (nuevo, 13 tests): día cerrado, día abierto sin citas, huecos fuera de los tramos propios del día dentro de una rejilla más ancha, hueco entre dos tramos, una cita, dos citas solapadas en carriles distintos, una cancelada (no ocupa carril), un cierre total sin cita (banda limpia), un cierre total con una cita que sobrevive (se sigue viendo, el otro carril se sombrea), el mismo caso cuando la cita termina antes que el cierre (la banda «cierre» aparece después, limpia), un cierre parcial (sombrea solo el carril por encima de la capacidad reducida), más citas que la capacidad (carril extra, `overCapacity`), y un hueco libre de menos de 30 minutos (no `tappable`).

`tests/Feature/Admin/AgendaTest.php`: ajustado un test existente que asumía que el nombre de una clienta solo aparecía una vez en la página (ahora aparece también en su bloque de la rejilla, antes de su tarjeta).

Añadido al cerrar la entrega (T040): `tests/Feature/Admin/AgendaTimelineGridTest.php` › «vista Día loads the timeline grid with a fixed number of queries, not one per appointment» — con 10 citas en el día, 1 sola consulta a `appointments`, `schedule_blocks`, `opening_hours` y `booking_settings`, nunca una por cita, por bloque o por carril.

## Verificación

`docker compose exec -T -u www-data app php artisan test --compact --filter="AgendaTest|AgendaWeekViewTest|AgendaMonthViewTest|AgendaCalendarAccessibilityTest|AdminAppointmentTest|AdminRescheduleAppointmentTest"` → 127 tests en verde (405 aserciones) · suite completa: **447 tests en verde** (1700 aserciones; partía de 422) · `docker compose exec -T -u www-data app vendor/bin/pint --dirty --format agent` → `{"result":"pass"}`.

Trampa encontrada y corregida: Blade no reconoce una directiva (`@endif`) pegada directamente a texto sin espacio ni salto de línea delante (`Cierre@endif`); se vuelca como texto literal en vez de compilarse, y el motor de plantillas lanza «syntax error, unexpected token "endforeach"» varios niveles más arriba, en el primer `@endforeach` que encuentra. Corregido separando `@endif` en su propia línea.

Pendiente a mano (lo hace el coordinador): comprobar a 375 px que dos carriles de ~140 px dejan leer la hora y la clienta con el recorte `ellipsis`, y a 1024 px que el texto y el ancho general se ven bien.
