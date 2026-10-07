# T029 — Selector de vista y navegación

- **Tipo:** FEATURE
- **Puntos de referencia:** PRF-099, PRF-104
- **Depende de:** T006 (agenda del panel), T028 (layout/menú actuales)
- **Modelo:** Claude Sonnet 5.5 · **Esfuerzo:** `medium`
- **Motivo:** cambio de varios archivos (controlador + vista) con comportamiento nuevo visible, pero sin tocar límites de módulos ni la concurrencia de escritura (es de solo lectura).
- **Estado:** done
- **PR / rama:** `feature/agenda-calendar-views`

## Objetivo

Añadir a `/admin/agenda` un selector Día/Semana/Mes y hacer que Anterior/Siguiente/Hoy se adapten a la vista activa, con `vista` y `fecha` en la URL para que el enlace se pueda compartir y el botón Atrás funcione. La vista Día no cambia de contenido; esta tarea solo añade la infraestructura de navegación. El contenido real de Semana y Mes lo añaden T030 y T031.

## Cambios

`app/Http/Controllers/Admin/AgendaController.php`:
- Nuevo método estático `viewFromQuery()`, igual de defensivo que `dayFromQuery()`: devuelve "dia" si el valor no está en `['dia', 'semana', 'mes']`.
- `index()` calcula también `$weekStart` (lunes de la semana de `$day`, `CarbonInterface::MONDAY`), `$weekEnd` (domingo) y `$month` (primer día del mes de `$day`) y los pasa a la vista — aritmética de fechas, sin consultas nuevas; T030 y T031 añadirán las consultas reales de cada vista.

`resources/views/admin/agenda/index.blade.php`:
- Título dinámico (`$periodLabel`) según la vista: para Día, el mismo texto de antes; para Semana, "Semana del dd/mm al dd/mm/aaaa"; para Mes, "Mes aaaa".
- Pestañas Día/Semana/Mes (`min-h-11 min-w-11`, `aria-current="page"` en la activa). El enlace de Día omite el parámetro `vista` (es el valor por defecto), así que los enlaces y tests existentes que solo llevan `fecha` siguen abriendo Día exactamente igual que antes.
- El bloque Anterior/Siguiente/Hoy pasa a un `@if/@elseif/@else` según `$vista`: en Semana mueve `$weekStart` una semana, en Mes mueve `$month` un mes, en Día seguida igual que antes (incluido el atajo «Mañana», que solo tiene sentido ahí).
- El formulario «Ir a la fecha» añade un campo oculto `name="vista"` cuando la vista activa no es Día, para no perderla al cambiar de fecha.

## Evidencia

`tests/Feature/Admin/AgendaTest.php` (añadidos): selector con enlaces compartibles y zona táctil de 44 px, pestaña activa marcada con `aria-current`, un valor de `vista` inválido cae a Día, Anterior/Siguiente/Hoy se convierten en controles de semana o de mes, y el formulario de fecha conserva la vista activa.

## Verificación

`docker compose exec -T -u www-data app php artisan test --compact --filter=AgendaTest` → 29 tests en verde (82 aserciones) · suite completa: **376 tests en verde** (1484 aserciones; partía de 368) · `docker compose exec -T -u www-data app vendor/bin/pint --dirty --format agent` → `{"result":"pass"}`.

Pendiente a mano (lo hace el coordinador): a 375 px y 1024 px, comprobar visualmente las tres pestañas y que cambiar de vista no rompe el resto del layout.
