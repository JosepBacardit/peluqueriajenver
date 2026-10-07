# T002 — Módulo de servicios

- **Tipo:** FEATURE
- **Puntos de referencia:** PRF-010, PRF-011, PRF-012, PRF-013, PRF-009
- **Depende de:** T001
- **Modelo:** Claude Sonnet 5 · **Esfuerzo:** `medium`
- **Motivo:** es un CRUD estándar con validación.
- **Estado:** done
- **PR / rama:** PR 1, `feature/booking-admin-foundation`

## Objetivo
Dar de alta, editar y listar los servicios con su duración y su precio interno, sin poder borrarlos.

## Plan
1. Migración `create_services_table` con `name`, `duration_minutes` (entero corto sin signo), `price_cents` (entero sin signo, nullable), `is_bookable_online`, `is_active`, `sort_order` y `timestamps`.
2. Modelo `Service` con *casts*, `ServiceFactory` y el *scope* `ordered()`.
3. `Admin\ServiceController` con `index`, `create`, `store`, `edit` y `update`. Sin `destroy`.
4. `Admin\ServiceRequest` con las reglas de la tabla de la especificación: precio con `decimal:0,2` entre 0 y 9999.99, guardado en céntimos.
5. Vistas `admin/services/index`, `create`, `edit` y un `_form` compartido.

## Criterios de aceptación
- Alta válida → se guarda. Duración que no es múltiplo de 5, o fuera de 5–600 → error (PRF-010).
- Edición con las mismas reglas (PRF-011).
- La lista sale ordenada y, sin servicios, muestra el mensaje de lista vacía (PRF-012).
- No existe la ruta de borrado (PRF-013).

## Plan de pruebas
`tests/Feature/Admin/ServiceManagementTest.php`: alta, edición, *dataset* de valores inválidos, orden, lista vacía, que no hay ruta `DELETE` y que hace falta sesión.

## Verificación
`docker compose exec -T app php artisan test --compact` · `docker compose exec -T app vendor/bin/pint --dirty --format agent`

## Riesgos
Ninguno fuera del panel.
