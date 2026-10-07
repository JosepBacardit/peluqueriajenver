# T023 — Agenda pensada para el pulgar

- **Tipo:** FEATURE
- **Puntos de referencia:** PRF-092, PRF-093, PRF-095 (PRF-094 ya resuelto en T020)
- **Depende de:** T006 (agenda), T017 (editar/mover cita)
- **Modelo:** Claude Sonnet 5.5 · **Esfuerzo:** `medium`
- **Motivo:** varios cambios en la misma vista (navegación de días, acción principal, tarjeta de cita) más un método nuevo en el modelo; sin cambios de datos ni de controlador.
- **Estado:** done (pendiente comprobar a 375 px en el navegador, la hace el usuario)
- **PR / rama:** `feature/mobile-admin-ux`

## Objetivo

La agenda es la pantalla que la peluquera abre más veces al día, casi siempre desde el móvil, para apuntar una cita que llega por teléfono o WhatsApp. Decisión del usuario, 2026-10-05 (recomendación de la fase 1): añadir un atajo a «Mañana», un botón flotante para crear una cita siempre al alcance del pulgar, y abrir WhatsApp con la clienta en un toque desde su tarjeta.

## Cambios

`resources/views/admin/agenda/index.blade.php`:
- Nuevo enlace «Mañana» en la navegación de días, entre «Hoy» y «Día siguiente →» (calcula `CarbonImmutable::today()->addDay()`, no depende del día que se esté viendo). El botón «Ir» de la fecha concreta pasa a `btn-outline` (ya estaba dentro del alcance de PRF-089, quedó fuera de T020 por descuido).
- El botón «Nueva cita» de la cabecera pasa a `hidden md:inline-flex`: en móvil lo sustituye un botón flotante nuevo (`md:hidden fixed`, círculo de 56 px, icono «+»), con el mismo destino (`admin.appointments.create` con la fecha del día que se está viendo) y `bottom: calc(4.5rem + env(safe-area-inset-bottom))` para no tapar la navegación inferior de T021 ni quedar bajo la zona segura del iPhone.
- Cada tarjeta de cita añade, junto al teléfono, un enlace «WhatsApp» que abre `$appointment->customerWhatsappUrl()` en una pestaña nueva (`target="_blank" rel="noopener"`).

`app/Models/Appointment.php`: método nuevo `customerWhatsappUrl(): string`. Construye `https://wa.me/<teléfono>?text=<mensaje>` con el mismo formato que ya usa el resto de la web (`wa.me/34...`, sin «+»). Normaliza el teléfono: si no empieza por «+» y tiene 9 dígitos o menos (el mínimo de `PhoneNumber`, es decir, sin prefijo de país), antepone `34`; si ya lleva «+» o ya tiene más de 9 dígitos, se deja tal cual, solo dígitos. El mensaje es fijo: «Hola {nombre}, te escribimos de Peluquería Jenver sobre tu cita.».

## Evidencia

`tests/Feature/Admin/AgendaTest.php`, 5 tests nuevos:
- «the agenda offers a shortcut to tomorrow»: el enlace a mañana existe y apunta a la fecha correcta.
- «the agenda has a floating "new appointment" button for phones»: un solo `aria-label="Nueva cita"`, la clase del botón flotante y la del botón de escritorio oculto en móvil.
- «an appointment card offers to open WhatsApp...»: con el teléfono «633 912 050», el enlace exacto es `https://wa.me/34633912050?text=Hola%20Marta%20Ruiz%2C%20te%20escribimos%20de%20Peluquer%C3%ADa%20Jenver%20sobre%20tu%20cita.`.
- «customerWhatsappUrl keeps an already-international phone as is and never breaks on odd input»: con «+34 633 912 050» o «34633912050» (ya con prefijo) el resultado empieza por `wa.me/34633912050`; con un teléfono extranjero (`+1 (555) 123-4567`, válido para `PhoneNumber`) el método no lanza ninguna excepción.

## Verificación

`docker compose exec -u www-data app php artisan test --compact --filter="AgendaTest"` (13 tests en verde) · suite completa (352 tests en verde) · `docker compose exec -u www-data app vendor/bin/pint --dirty --format agent` (sin cambios) · `docker compose exec node npm run build` (sin avisos).

Pendiente a mano (la hace el usuario): a 375 px, comprobar que el botón flotante no tapa la última cita ni la navegación inferior al hacer *scroll*, que «Mañana» lleva al día siguiente al de hoy (no al día siguiente del que se esté viendo) y que el enlace de WhatsApp abre la app o WhatsApp Web con el mensaje prellenado.
