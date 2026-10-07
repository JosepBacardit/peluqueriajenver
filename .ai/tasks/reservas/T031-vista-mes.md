# T031 — Vista Mes

- **Tipo:** FEATURE
- **Puntos de referencia:** PRF-102, PRF-103, PRF-105, PRF-106, PRF-107
- **Depende de:** T029 (selector de vista y navegación)
- **Modelo:** Claude Sonnet 5.5 · **Esfuerzo:** `medium`
- **Motivo:** una consulta agregada nueva y una plantilla, reutilizando el patrón de rejilla ya existente en `pages/partials/reservas-calendar.blade.php`; sin tocar la concurrencia de escritura.
- **Estado:** done
- **PR / rama:** `feature/agenda-calendar-views`

## Objetivo

Añadir el contenido de la vista Mes: una rejilla mensual con el número de citas confirmadas de cada día, que al tocar un día abre su vista Día.

## Cambios

`app/Http/Controllers/Admin/AgendaController.php`:
- `index()` añade el caso `'mes' => $this->monthData($month)` al `match`.
- `monthData()`: una consulta agregada de `Appointment::confirmed()` (`selectRaw('DATE(starts_at) as date, COUNT(*) as total')->groupBy('date')->pluck('total', 'date')`) — nunca carga cada cita del mes, solo el recuento por día — más una consulta de `ScheduleBlock` filtrada a cierres totales (`whereNull('capacity_reduction')`) que se solapan con el mes, y los días de la semana con horario (`openWeekdays()`, reutilizado de T030).

`resources/views/admin/agenda/_month.blade.php` (nuevo): rejilla de 7 columnas con el mismo patrón ya usado en `pages/partials/reservas-calendar.blade.php` (`role="grid"`/`role="columnheader"`, huecos iniciales con `aria-hidden`, celdas de `min-h-11`), pero con el número de citas en vez de disponibilidad. Cada día es un enlace a `vista=dia&fecha=...` (PRF-103, tenga o no citas). Hoy lleva `aria-current="date"` y un borde dorado (PRF-106); un día sin horario semanal o cubierto por un cierre total muestra el texto «Cerrado» en vez del número (PRF-105); una reducción de capacidad parcial no marca el día como cerrado. El `aria-label` de cada celda dice la fecha completa y si está cerrada, sin citas o cuántas tiene, para que un lector de pantalla no dependa del color ni del número suelto.

`resources/views/admin/agenda/index.blade.php`: el `@if/@elseif` de contenido añade la rama `@elseif ($vista === 'mes') @include('admin.agenda._month') @endif`.

## Evidencia

`tests/Feature/Admin/AgendaMonthViewTest.php` (nuevo): número de citas confirmadas por día (las canceladas no cuentan); hoy marcado con `aria-current`; un día sin horario y un cierre total se marcan «Cerrado», una reducción parcial no; tocar cualquier día (con o sin citas) lleva a la vista Día de esa fecha; Anterior/Siguiente mueven un mes entero; una sola consulta agregada a `appointments` para todo el mes (comprobado con `DB::enableQueryLog()`); rejilla con `role="grid"`/`role="columnheader"` y celdas de 44 px.

## Verificación

`docker compose exec -T -u www-data app php artisan test --compact --filter="AgendaMonthViewTest|AgendaTest|AgendaWeekViewTest"` → 44 tests en verde (128 aserciones) · suite completa: **391 tests en verde** (1530 aserciones; partía de 383) · `docker compose exec -T -u www-data app vendor/bin/pint --dirty --format agent` → `{"result":"pass"}`.

Pendiente a mano (lo hace el coordinador): a 375 px y 1024 px, comprobar que la rejilla mensual se lee bien, que «Cerrado» y el número de citas no dependen del color, y que tocar un día lleva a su agenda.
