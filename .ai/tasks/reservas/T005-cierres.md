# T005 — Módulo de cierres y bloqueos

- **Tipo:** FEATURE
- **Puntos de referencia:** PRF-021, PRF-022, PRF-023, PRF-024, PRF-009
- **Depende de:** T004
- **Modelo:** Claude Sonnet 5 · **Esfuerzo:** `medium`
- **Motivo:** es un CRUD sencillo sobre una tabla ya creada.
- **Estado:** done
- **PR / rama:** PR 2, `feature/booking-availability`

## Plan
`Admin\ScheduleBlockController` con `index`, `store` y `destroy`, `ScheduleBlockRequest` y la vista `admin/blocks/index` con el formulario y la lista. Al guardar se cuentan las citas confirmadas que se solapan y se muestra el aviso.

## Criterios de aceptación
- La validación rechaza un fin anterior o igual al inicio (PRF-021).
- Se muestra el aviso con N citas y estas siguen confirmadas (PRF-022).
- La lista solo incluye cierres futuros, por inicio, con su mensaje vacío (PRF-023).
- Eliminar un cierre libera las horas (PRF-024).

## Plan de pruebas
`tests/Feature/Admin/ScheduleBlockManagementTest.php`.

## Verificación
`docker compose exec -T app php artisan test --compact` · Pint.

## Riesgos
Ninguno fuera del panel.
