# T020 — Objetivo táctil mínimo en botones y enlaces de acción

- **Tipo:** SIMPLE
- **Puntos de referencia:** PRF-089, PRF-094
- **Depende de:** T017 (agenda), T005 (cierres)
- **Modelo:** Claude Sonnet 5.5 · **Esfuerzo:** `low`
- **Motivo:** cambio puntual y de bajo riesgo en un componente compartido y en dos vistas; no toca lógica de negocio.
- **Estado:** done (pendiente la medición a 375 px en el navegador, la hace el usuario)
- **PR / rama:** `feature/mobile-admin-ux`

## Objetivo

El panel lo usan las peluqueras desde el móvil, muchas veces al día. `.btn-gold`/`.btn-outline` (`resources/css/app.css`) daban una altura real de ~36-40 px (`px-5 py-2`), por debajo del mínimo táctil de 44 px recomendado. Además, «Editar», «Cancelar cita» (agenda) y «Eliminar» (cierres) eran enlaces de texto suelto, sin zona táctil propia, y «Cancelar»/«Eliminar» no se distinguían visualmente de las acciones no destructivas.

## Cambios

- `resources/css/app.css`: `.btn-gold` y `.btn-outline` pasan a `inline-flex items-center justify-center min-h-11 ... px-5 py-3` (antes `px-5 py-2`, sin `inline-flex`/`min-h-11`). Las páginas que ya sobrescribían el relleno (home, contacto, páginas de servicio, `whatsapp-cta`) no cambian: su `px-*`/`py-*` propio ya era ≥ el nuevo mínimo. Se añade `.btn-danger-outline` (borde y texto en rojo) para las acciones destructivas.
- `resources/views/layouts/admin.blade.php`: «Cerrar sesión» pasa de enlace subrayado a `btn-outline text-xs px-3`.
- `resources/views/admin/agenda/index.blade.php`: «Editar» pasa a `btn-outline`; «Cancelar cita» pasa a `btn-danger-outline`; ambos en una fila `flex flex-wrap gap-3` (antes apilados con `gap-2`), para que tengan zona táctil propia y una separación mayor entre la acción normal y la destructiva (PRF-094).
- `resources/views/admin/blocks/index.blade.php`: «Eliminar» pasa a `btn-danger-outline`.

## Evidencia

`tests/Feature/Admin/AdminLayoutTest.php` (2 tests nuevos): el primero lee `resources/css/app.css` y comprueba que el cuerpo de `.btn-gold` y `.btn-outline` incluye `min-h-11` e `inline-flex`; el segundo comprueba lo mismo para `.btn-danger-outline` y que usa un tono rojo. Son tests de regresión sobre el CSS fuente, no sobre píxeles renderizados: la medición real a 375 px queda para la comprobación manual del usuario.

## Verificación

`docker compose exec -u www-data app php artisan test --compact --filter="AdminLayoutTest|AgendaTest|ScheduleBlockManagementTest"` (30 tests en verde) · suite completa `docker compose exec -u www-data app php artisan test --compact` (346 tests en verde) · `docker compose exec -u www-data app vendor/bin/pint --dirty --format agent` (sin cambios).

Pendiente a mano (la hace el usuario, necesita la sesión del panel): medir con el inspector, a 375 px, que «Editar», «Cancelar cita», «Guardar cita»/«Guardar cambios», «Nueva cita», un módulo del menú, «Cerrar sesión» y «Eliminar» (Cierres) miden al menos 44×44 px, y que «Cancelar cita»/«Eliminar» se distinguen a simple vista de «Editar»/«Guardar».
