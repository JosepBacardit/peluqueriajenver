# T042 — Motor: dónde cabe un servicio, sin consultas

- **Tipo:** ARCHITECTURAL
- **Puntos de referencia:** PRF-121, PRF-122 (base de PRF-120, que se retoma en T043–T045)
- **Depende de:** T041 (rejilla horaria con carriles y medias horas tocables)
- **Modelo:** Claude Opus 5.5 · **Esfuerzo:** `medium`
- **Motivo:** toca el motor de disponibilidad, que es la única fuente de verdad de las reglas de reserva. El método nuevo tiene que dar siempre lo mismo que la comprobación del panel.
- **Estado:** done
- **PR / rama:** `feature/agenda-service-filter`, desde `feature/agenda-timeline-grid`

## Objetivo

Que la agenda pueda saber, para un servicio de duración D, en qué horas de inicio cabe completo, sin ninguna consulta adicional. El cálculo usa el contexto que `AgendaController` ya carga para `DayTimeline::build()` en las vistas Día y Semana. Solo esta tarea: el selector, el resaltado y la preselección son T043, T044 y T045.

## Implementación

`App\Booking\AvailabilityCalculator::fittingStartMinutes(int $durationMinutes, array $candidateMinutes, CarbonImmutable $day, Collection $ranges, Collection $appointments, Collection $blocks, int $capacity): array`

- **Qué devuelve:** la lista, en el orden recibido, de los minutos candidatos (minutos de reloj desde medianoche, la unidad de `DayTimeline`) en los que el servicio cabe por las reglas 1 y 2.
  - Nunca aplica la regla 3.
  - Tampoco comprueba si la hora ya ha pasado: lo decide la agenda, y `DayTimeline` ya no hace tocables las medias horas pasadas.
- **Contexto que recibe:**
  - los tramos del día (`$dayRanges`);
  - las citas del día de cualquier estado (filtra las confirmadas);
  - los cierres que se solapan con el día;
  - la capacidad.
- **Una sola fuente de verdad.** La regla 1 está ahora en un método privado, `rangeContaining()`, que usan `unavailabilityReason()` (y, a través de él, `isAvailable()`) y el método nuevo. `availableStartTimes()` sigue recorriendo los tramos, así que cumple la regla 1 por construcción. La regla 2 es la misma `hasCapacity()`/`capacityProblem()` para los cuatro métodos. Los tests existentes siguen en verde sin tocarlos.
- **Candidatas: las pasa el llamador.**
  - La rejilla ya sabe qué medias horas son tocables y por carril, incluidos los huecos que no empiezan en media hora exacta (p. ej. 11:55). Si el método generara sus propias candidatas, duplicaría esa lógica o calcularía horas que no se pintan.
  - Los minutos de reloj evitan tener que convertir y son correctos los días de cambio de hora, porque el método construye cada hora con `atMinute()`, igual que el resto del motor.
- **Uso previsto en el controlador** (documentado en el docblock): en `dayData()`, y en `weekData()` día a día con los `$dayRanges`, `$dayAppointments` y `$dayBlocks` que ya calcula, llamar al método con `$service->duration_minutes` y las `start` de los segmentos libres tocables, y hacer `array_flip()` del resultado para consultarlo por segmento.
- **Requisito sobre el contexto:** las citas tienen que cubrir todo lo que se pueda solapar con el día. Con las que empiezan ese día basta, porque ninguna cita cruza la medianoche.

## Plan de pruebas

`tests/Feature/Booking/AvailabilityFitForServiceTest.php` (nuevo), con el contexto cargado exactamente como lo carga `AgendaController`. Casos:

- una cita que no deja hueco y una que termina justo cuando empieza la candidata;
- capacidad 1 y 2;
- un cierre parcial que empieza a mitad del servicio;
- un cierre total;
- la pausa de mediodía y el cierre;
- un día cerrado;
- citas canceladas;
- que la regla 3 no se aplica;
- los dos días de cambio de hora;
- **cero consultas** (`DB::enableQueryLog`);
- **equivalencia con `isAvailable(..., applyPublicRules: false)`** en 25 días aleatorios con semilla. Cada día tiene una capacidad de 1 a 3, a veces horario partido, hasta 6 citas (algunas canceladas) y hasta 2 cierres parciales o totales, y se comprueban 4 duraciones con candidatas cada 15 minutos de 08:00 a 19:30.

Mutación comprobada (y deshecha): si el método deja de filtrar las citas confirmadas, la batería de equivalencia falla en 4 semillas.

## Verificación

`docker compose exec -T -u www-data app php artisan test --compact` (suite completa) · `docker compose exec -T -u www-data app vendor/bin/pint --dirty --format agent`.

## Fuera de alcance

Las vistas, el controlador y el selector (T043–T045).
