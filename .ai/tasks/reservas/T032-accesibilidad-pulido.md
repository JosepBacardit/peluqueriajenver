# T032 — Accesibilidad y pulido táctil de Semana y Mes

- **Tipo:** SIMPLE
- **Puntos de referencia:** PRF-099, PRF-105, PRF-107
- **Depende de:** T030 (Vista Semana), T031 (Vista Mes)
- **Modelo:** Claude Sonnet 5.5 · **Esfuerzo:** `low`
- **Motivo:** ajustes puntuales de accesibilidad sobre vistas ya implementadas, sin comportamiento nuevo ni archivos nuevos.
- **Estado:** done
- **PR / rama:** `feature/agenda-calendar-views`

## Objetivo

Cerrar dos huecos de accesibilidad que quedaban en Semana tras T030: el día de hoy, cuando no es el día elegido, solo se distinguía por el color del borde (mobile) o del anillo (escritorio), y la rejilla de escritorio no tenía un rol de encabezado de columna como sí lo tiene la vista Mes (T031). Confirma además que Anterior/Siguiente/Hoy de Semana y Mes reutilizan el mismo botón de 44 px ya medido en Día (T020), sin un estilo nuevo que haya que volver a medir.

## Cambios

`resources/views/admin/agenda/_week.blade.php`:
- Tira móvil: cuando el día de hoy no es el seleccionado, su pastilla añade un texto «hoy» (además del borde dorado), para no depender solo del color (PRF-106, ya cubierto en el objetivo de T030 pero incompleto en la implementación).
- Rejilla de escritorio: el nombre de cada día pasa a `role="columnheader"` (igual que la vista Mes, PRF-107) y, cuando es hoy, añade el texto «· Hoy» junto al nombre, además del anillo dorado.

Sin cambios en el controlador ni en `_month.blade.php` ni en `_day.blade.php` para estos dos puntos: ya cumplían desde T031 y T030.

Al probar esta tarea con los datos reales del entorno local (dos citas: una cancelada el 2026-10-06, una confirmada el 2026-10-07), se detectó un tercer hallazgo del mismo tipo: la pastilla del día en la tira móvil contaba también las citas canceladas (`$d['appointments']->count()`), así que un día con una única cita cancelada mostraba «1 cita» como si quedara una reserva viva, inconsistente con el recuento de la vista Mes, que solo cuenta las confirmadas (PRF-102). Se corrigió para contar solo citas confirmadas (`$d['appointments']->filter->isConfirmed()->count()`); la cita cancelada se sigue viendo, atenuada, en la agenda del día debajo de la tira, igual que en vista Día.

## Evidencia

`tests/Feature/Admin/AgendaCalendarAccessibilityTest.php` (nuevo): hoy sin seleccionar muestra el texto «hoy» en la tira móvil; la rejilla de escritorio muestra el texto «Hoy»; los 7 encabezados de día llevan `role="columnheader"`; los botones Anterior/Siguiente de Semana y Mes son la misma clase `btn-outline w-11 px-0` que ya usa Día.

`tests/Feature/Admin/AgendaWeekViewTest.php` (añadido): una cita cancelada no suma al recuento de la pastilla de su día («sin citas», no «1 cita»), aunque sigue apareciendo en la agenda del día.

## Verificación

`docker compose exec -T -u www-data app php artisan test --compact --filter="AgendaCalendarAccessibilityTest|AgendaWeekViewTest|AgendaMonthViewTest|AgendaTest"` → 50 tests en verde (141 aserciones) · suite completa: **397 tests en verde** (1543 aserciones; partía de 391) · `docker compose exec -T -u www-data app vendor/bin/pint --dirty --format agent` → `{"result":"pass"}` · `docker compose exec -T node npm run build` → sin errores (un aviso informativo de tiempos del plugin de Tailwind, no relacionado con este cambio); **no se ha reiniciado el contenedor `node`** (pedido explícito del coordinador: el build se ejecutó como proceso aparte con `docker compose exec`, que no afecta al `npm run dev` que ya corre como proceso principal del contenedor).

Pendiente a mano (lo hace el coordinador): confirmar visualmente a 375 px que la palabra «hoy» se lee bien dentro de la pastilla sin desbordar, y a 1024 px que «· Hoy» no rompe el encabezado del día en una columna estrecha.
