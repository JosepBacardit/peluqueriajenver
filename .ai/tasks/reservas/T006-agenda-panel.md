# T006 — Agenda del panel

- **Tipo:** FEATURE
- **Puntos de referencia:** PRF-016 (parte del panel), PRF-045, PRF-046, PRF-047, PRF-048, PRF-049, PRF-009
- **Depende de:** T004
- **Modelo:** Claude Sonnet 5 · **Esfuerzo:** `medium`
- **Motivo:** son pantallas de panel sobre acciones ya probadas.
- **Estado:** done
- **PR / rama:** PR 2, `feature/booking-availability`

## Plan
1. `Admin\AgendaController@index` con `?fecha=Y-m-d` y navegación. Pasa a ser el destino de `/admin`.
2. `Admin\AppointmentController` con `create`, `store` (usa `CreateAppointment` sin las reglas públicas, rechaza horas pasadas y minutos que no son múltiplo de 5, solo servicios activos) y `cancel` (usa `CancelAppointment`).
3. `StoreAdminAppointmentRequest`.
4. Vistas `admin/agenda/index` y `admin/appointments/create`.

## Criterios de aceptación
- Agenda ordenada, con navegación y mensaje vacío (PRF-045).
- Alta del panel con email opcional y origen `admin` (PRF-046).
- Con la capacidad completa se muestra el mensaje (PRF-047).
- Se puede cancelar sin plazo (PRF-048).
- No hay ruta de borrado y las canceladas se siguen viendo (PRF-049).
- Un servicio inactivo no se ofrece y se rechaza en el alta (PRF-016).

## Plan de pruebas
`tests/Feature/Admin/AgendaTest.php` y `tests/Feature/Admin/AdminAppointmentTest.php`.

## Verificación
`docker compose exec -T app php artisan test --compact` · Pint.

## Riesgos
Ninguno fuera del panel.
