# T051 — Resolver la revisión de varios servicios por cita

- **Tipo:** FEATURE
- **Puntos de referencia:** PRF-125, PRF-127, PRF-129, PRF-132 (aclarados), además de PRF-128 y PRF-131 (que se refuerzan)
- **Depende de:** T050 (`.ai/reviews/multi-service-appointments.md`: 0 críticos, 0 altos, 2 medios y 6 bajos)
- **Modelo:** Claude Opus 5.5 · **Esfuerzo:** `medium`
- **Motivo:** el agente principal la asignó a Opus porque M1 es de concurrencia (`knowledge/model-selection.md`).
- **Estado:** done (pendientes las comprobaciones en el navegador de la revisión)
- **PR / rama:** `feature/multi-service-appointments`

## Objetivo

Resolver los 8 hallazgos de la revisión con la *skill* `fix-review`, cada uno con su resolución y su evidencia en el propio registro de la revisión.

## Resumen de la resolución

- **M1:** tests que cancelan la cita, o simulan otro movimiento, **entre** la relectura de los servicios y el `UPDATE` condicional (`duringMoveWindow()`). Cada uno tiene su mutación comprobada: quitar `where status` en el primero, y borrar solo las filas releídas en el segundo.
- **M2:** regla `list` para `service_ids` (nunca un 500 por las claves) y `failedValidation()`, que vuelve al paso 1 con el aviso enlazado a las casillas.
- **L1:** el `GET` normaliza los repetidos y los valores no numéricos con un aviso discreto. Está documentado en PRF-127.
- **L2:** un único criterio de orden, `ServiceList::sort()` (`sort_order` y después `id`, nunca el nombre). Se usa al guardar, en el paso 2 y en «Cabe». Está actualizado en PRF-125.
- **L3:** la lista de servicios se compara como conjunto al mover. Reordenar el catálogo no cuenta como cambio y no se envía correo.
- **L4:** `<fieldset>` con `aria-describedby`, `aria-invalid` en cada casilla y un único mensaje con `id`.
- **L5:** `AGENTS.md` habla de seis tablas y pide comprobar con `migrate:status`, antes de desplegar, que ninguna migración de reservas se haya ejecutado.
- **L6:** «Duración total» en el alta y la edición del panel (el valor del servidor y un JS *vanilla* compartido con `/reservas`), y «Cambiar» en `/reservas` con la elección marcada.
- **Extra:** el test de «Cabe» en Semana con 2 servicios.

## Verificación

- `docker compose exec -T -u www-data app php artisan test --compact`: 631 tests en verde (eran 609).
- `vendor/bin/pint --test` sobre los `.php` tocados: en verde.
- `docker compose exec -T node npm run build`: compila.
- Sin datos nuevos en MySQL.

Pendientes en el navegador: los puntos de «Para comprobar en el navegador» de la revisión.