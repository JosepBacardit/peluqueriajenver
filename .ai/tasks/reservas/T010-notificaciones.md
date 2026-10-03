# T010 — Notificaciones por correo y reintento

- **Tipo:** FEATURE
- **Puntos de referencia:** PRF-014 (correo), PRF-050, PRF-051, PRF-052, PRF-053, PRF-054
- **Depende de:** T006, T008
- **Modelo:** Claude Sonnet 5 · **Esfuerzo:** `high`
- **Motivo:** hay que tratar los fallos parciales y la idempotencia del reintento.
- **Estado:** pending
- **PR / rama:** PR 4, `feature/booking-notifications`

## Plan
1. `config/booking.php` con `salon_notification_email` (variable `BOOKING_NOTIFICATION_EMAIL`).
2. Mailables síncronos con vistas Markdown en español:
   - `AppointmentConfirmedMail` (al cliente);
   - `NewAppointmentMail` (al salón);
   - `AppointmentCancelledMail` (al cliente, con variantes según quién cancela);
   - `AppointmentCancelledByCustomerMail` (al salón).
3. `App\Booking\AppointmentNotifier`:
   - `sendCreationNotices(Appointment)`: envía cada aviso pendiente, marca `customer_notified_at` y `salon_notified_at`, y captura y registra con `report()` los fallos;
   - `sendCancellationNotices(Appointment, bool $byCustomer)`: igual, pero sin marca.
4. Se llama desde los controladores después de crear o cancelar (público y panel).
5. Comando `appointments:notify-pending`: citas confirmadas, futuras y creadas hace más de 5 minutos, con avisos pendientes.

## Plan de pruebas
`tests/Feature/Booking/AppointmentNotificationsTest.php` (`Mail::fake`, fallo simulado con un *mailer* que lanza una excepción, sin precio) y `tests/Feature/Booking/NotifyPendingAppointmentsCommandTest.php`.

## Verificación
`docker compose exec -T app php artisan test --compact` · Pint.

## Riesgos
El envío síncrono añade la latencia del SMTP a la respuesta. Es aceptable con este volumen y es el mismo patrón que cobaprojects y obranur-web.
