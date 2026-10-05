# Tareas: reservas online y panel de gestión

- **Especificación:** [../../specs/reservas.md](../../specs/reservas.md)
- **Flujo de trabajo:** ARCHITECTURAL, en 4 PRs apilados:
  - PR 1, `feature/booking-admin-foundation`: T001–T003;
  - PR 2, `feature/booking-availability`: T004–T006;
  - PR 3, `feature/booking-public`: T007–T009;
  - PR 4, `feature/booking-notifications`: T010–T012.
  - Ajustes tras la revisión visual del usuario (2026-10-05), `feature/booking-admin-tweaks` apilada sobre `feature/booking-review-fixes`: T015–T018.
  - Adaptación móvil del panel y la reserva pública (2026-10-05), `feature/mobile-admin-ux` apilada sobre `feature/booking-admin-tweaks` (una vez fusionada): T020–T028.
  - Vistas Día, Semana y Mes de la agenda (2026-10-05), `feature/agenda-calendar-views` apilada sobre `feature/mobile-admin-ux` (una vez fusionada): T029–T033.
  - Rejilla horaria de Día y Semana al estilo Google Calendar (2026-10-05), `feature/agenda-timeline-grid` apilada sobre `feature/agenda-calendar-views` (una vez fusionada): T034–T040.
- **Estado general:** implementación (T001–T012), revisión (T013) y resolución (T014) hechas. Queda pendiente el hallazgo M4 de la revisión (analítica y consentimiento, decisión del usuario) y los datos del cliente que bloquean publicar. De los ajustes 2026-10-05: T015–T018 hechos, revisados (`.ai/reviews/booking-admin-tweaks.md`) y con los hallazgos resueltos en T019. Quedan pendientes las comprobaciones manuales: correos en Gmail y Outlook y el recorrido completo del panel en el navegador (filas `partial` de la matriz). Adaptación móvil (T020–T026): implementación hecha con Pest y `npm run build` en verde; el agente principal no pudo confirmar visualmente a 375 px (el navegador disponible no cambia el ancho real de la ventana — `resize_window` no tuvo efecto, comprobado con `window.innerWidth`). Revisada (`.ai/reviews/mobile-admin-ux.md`, 0 críticos, 0 altos, 1 medio, 3 bajos) y resuelta en T027: M1 (enlace de WhatsApp roto con el prefijo `00`), L1 (`rel`) y L2 (test de «Mañana») corregidos; L3 anotado, sin cambio de código. El coordinador comprobó el panel y lo público a 375 px con la sesión del usuario y encontró 4 hallazgos más (N1-N4: llamar/WhatsApp como botones grandes, navegación de días en una fila, `date`/`time`/`datetime-local` a 44 px, casilla de cancelar más grande), aprobados por el usuario y resueltos en el mismo informe de revisión. Después, el usuario cambió la decisión de PRF-090: la barra inferior fija de T021 pasa a ser un menú hamburguesa en T028 (el panel va a crecer con más apartados). Queda pendiente que el coordinador confirme a mano el resultado en el navegador, incluido T028. Vistas Día/Semana/Mes (T029–T032): implementación hecha con Pest, Pint y `npm run build` en verde, sin reiniciar el contenedor `node`. Revisada (`.ai/reviews/agenda-calendar-views.md`, 0 críticos, 1 alto, 3 medios, 3 bajos) y resuelta en T033: H1 (Semana no marcaba cierres totales como Mes), M1 (crear/mover/cancelar desde Semana o Mes volvía siempre a Día — nuevo parámetro `volver`, validado, sin redirección abierta) y M3 (semántica `role="row"`/`"gridcell"` completa en Semana, Mes **y el calendario público**) y L1/L3 (nombre de variable, mes en la cabecera cuando la semana cruza de mes) resueltos; M2/L2 (ancho de celda 39-43 px a 360 px) aceptados por el usuario, anotado en PRF-105. El coordinador añadió tres hallazgos propios vistos en Chrome (N1 mayúsculas de más en el título de Semana, N2 navegación en tres filas en escritorio, N3 texto pequeño y sin enlace en las citas de la rejilla de Semana), también resueltos en T033. Pendiente que el coordinador confirme en el navegador, con la sesión del usuario, las tres vistas a 375 px y en escritorio, y la lectura con lector de pantalla de las rejillas (filas `partial` de la matriz: PRF-099, 100, 101, 102, 107).

## Tareas

| ID | Tarea | Tipo | Puntos de referencia | Depende de | Modelo | Esfuerzo | Verificación | Estado |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| T001 | [Acceso al panel, base regional y layout](T001-acceso-panel.md) | FEATURE | PRF-001–009 | — | Claude Opus 5.5 | `medium` | Pest + Pint | done |
| T002 | [Servicios](T002-servicios.md) | FEATURE | PRF-010–013, 009 | T001 | Claude Sonnet 5 | `medium` | Pest + Pint | done |
| T003 | [Horario y ajustes](T003-horario-y-ajustes.md) | FEATURE | PRF-017–020, 009 | T001 | Claude Sonnet 5 | `medium` | Pest + Pint | done |
| T004 | [Citas y motor de disponibilidad](T004-motor-disponibilidad.md) | ARCHITECTURAL | PRF-015, 025, 026 | T002, T003 | Claude Opus 5.5 | `medium` | Pest | done |
| T005 | [Cierres](T005-cierres.md) | FEATURE | PRF-021–024, 009 | T004 | Claude Sonnet 5 | `medium` | Pest + Pint | done |
| T006 | [Agenda del panel](T006-agenda-panel.md) | FEATURE | PRF-016, 045–049, 009 | T004 | Claude Sonnet 5 | `medium` | Pest + Pint | done |
| T007 | [Reserva pública](T007-reserva-publica.md) | FEATURE | PRF-014, 016, 026–037, 061 | T004 | Claude Sonnet 5 | `high` | Pest + Pint + build | done |
| T008 | [Página de la cita](T008-pagina-cita.md) | FEATURE | PRF-039–044 | T007 | Claude Sonnet 5 | `medium` | Pest + Pint | done |
| T009 | [Caché, cabecera, sitemap y esquema](T009-web-publica-seo-cache.md) | FEATURE | PRF-038, 056–058 | T007, T008 | Claude Sonnet 5 | `medium` | Pest + Pint | done |
| T010 | [Notificaciones](T010-notificaciones.md) | FEATURE | PRF-014, 050–054 | T006, T008 | Claude Sonnet 5 | `high` | Pest + Pint | done |
| T011 | [Despliegue y documentación](T011-despliegue-y-documentacion.md) | FEATURE | PRF-055 | T010 | Claude Sonnet 5 | `medium` | Pest + Pint + `bash -n` | done |
| T012 | [Privacidad](T012-privacidad.md) | FEATURE | PRF-059, 060 | T007 | Claude Sonnet 5 | `medium` | Pest | done |
| T013 | [Revisión independiente](T013-revision.md) | REVIEW | Todos | T001–T012 | Claude Opus 5.5 | `medium` | Matriz completa | done |
| T014 | [Resolver la revisión](T014-resolver-revision.md) | FEATURE | PRF-062–069 | T013 | Claude Opus 5.5 | `medium` | Pest + Pint + `bash -n` | done |
| T015 | [Remitente de producción y deploy:check](T015-remitente-correo.md) | SIMPLE | PRF-070, 071 | T011 | Claude Sonnet 5 | `low` | Pest + Pint | done |
| T016 | [Marca del salón en el tema de los correos](T016-marca-correos.md) | FEATURE | PRF-072–075 | T010 | Claude Sonnet 5 | `medium` | Pest + Pint + revisión visual | done |
| T017 | [Mover y editar una cita desde el panel](T017-editar-cita-panel.md) | ARCHITECTURAL | PRF-077–086 | T004, T006, T010, T014 | Claude Opus 5.5 | `medium` | Pest + Pint + navegador | done |
| T018 | [Botones «Reservar cita» del inicio y servicios](T018-cta-reservar.md) | SIMPLE | PRF-076 | T009 | Claude Sonnet 5 | `low` | Pest + Pint | done |
| T019 | [Resolver la revisión de los ajustes](T019-resolver-revision-ajustes.md) | FEATURE | PRF-070–075, 077–088 | T015–T018 | Claude Opus 5.5 | `medium` | Pest + Pint + navegador | done |
| T020 | [Objetivo táctil mínimo en botones y enlaces de acción](T020-objetivo-tactil.md) | SIMPLE | PRF-089, 094 | T017 | Claude Sonnet 5.5 | `low` | Pest + Pint | done |
| T021 | [Navegación inferior fija del panel en móvil](T021-nav-inferior.md) | FEATURE | PRF-090 | T001 | Claude Sonnet 5.5 | `medium` | Pest + Pint + navegador | done |
| T022 | [Servicios en tarjetas en todos los anchos](T022-servicios-tarjetas.md) | FEATURE | PRF-091 | T002 | Claude Sonnet 5.5 | `low` | Pest + Pint | done |
| T023 | [Agenda pensada para el pulgar](T023-agenda-pulgar.md) | FEATURE | PRF-092–095 | T006, T017 | Claude Sonnet 5.5 | `medium` | Pest + Pint + navegador | done |
| T024 | [Horario semanal sin recorte en 360 px](T024-horario-360.md) | SIMPLE | PRF-096 | T003 | Claude Sonnet 5.5 | `low` | Pest + Pint + navegador | done |
| T025 | [Calendario y horas públicas: zona táctil](T025-calendario-tactil.md) | SIMPLE | PRF-097 | T007 | Claude Sonnet 5.5 | `low` | Pest + Pint + navegador | done |
| T026 | [Botón de cancelar a ancho completo en /cita](T026-cancelar-ancho-completo.md) | SIMPLE | PRF-098 | T008 | Claude Sonnet 5.5 | `low` | Pest + Pint | done |
| T027 | [Resolver la revisión de la adaptación móvil](T027-resolver-revision-mobile-ux.md) | FEATURE | PRF-095 (reforzado) | T020–T026 | Claude Opus 5.5 | `medium` | Pest + Pint + `npm run build` | done |
| T028 | [Menú del panel: hamburguesa en vez de barra inferior](T028-menu-hamburguesa.md) | FEATURE | PRF-090 (reescrito), PRF-093 (ajustado) | T021 | Claude Opus 5.5 | `medium` | Pest + Pint + `npm run build` + navegador | done |
| T029 | [Selector de vista y navegación](T029-selector-vista-navegacion.md) | FEATURE | PRF-099, PRF-104 | T006, T028 | Claude Sonnet 5.5 | `medium` | Pest + Pint | done |
| T030 | [Vista Semana](T030-vista-semana.md) | FEATURE | PRF-100, PRF-101, PRF-105, PRF-106, PRF-107 | T029 | Claude Sonnet 5.5 | `high` | Pest + Pint | done |
| T031 | [Vista Mes](T031-vista-mes.md) | FEATURE | PRF-102, PRF-103, PRF-105, PRF-106, PRF-107 | T029 | Claude Sonnet 5.5 | `medium` | Pest + Pint | done |
| T032 | [Accesibilidad y pulido táctil de Semana y Mes](T032-accesibilidad-pulido.md) | SIMPLE | PRF-099, PRF-105, PRF-107 | T030, T031 | Claude Sonnet 5.5 | `low` | Pest + Pint + `npm run build` | done |
| T033 | [Resolver la revisión de las vistas de la agenda](T033-resolver-revision-agenda-calendar-views.md) | FEATURE | PRF-105 (ampliado) | T029–T032 | Claude Opus 5.5 | `medium` | Pest + Pint + `npm run build` | done |
| T034 | [Rango y escala de la rejilla horaria](T034-rejilla-rango-escala.md) | FEATURE | PRF-108 | T033 | Claude Sonnet 5.5 | `medium` | Pest + Pint | done |
| T035 | [Algoritmo de carriles](T035-algoritmo-carriles.md) | FEATURE | PRF-109, PRF-110 | T034 | Claude Sonnet 5.5 | `high` | Pest + Pint | done |
| T036 | [Vista Día: rejilla horaria](T036-vista-dia-rejilla.md) | FEATURE | PRF-111, PRF-112, PRF-113, PRF-115 | T035 | Claude Sonnet 5.5 | `high` | Pest + Pint | pending |
| T037 | [Tocar un hueco libre crea la cita](T037-tocar-hueco-libre.md) | FEATURE | PRF-114 | T036 | Claude Sonnet 5.5 | `medium` | Pest + Pint | pending |
| T038 | [Línea de «ahora» y desplazamiento automático](T038-ahora-y-scroll.md) | FEATURE | PRF-116, PRF-117 | T036 | Claude Sonnet 5.5 | `medium` | Pest + Pint + `npm run build` | pending |
| T039 | [Semana con la rejilla horaria](T039-semana-rejilla.md) | FEATURE | PRF-118 | T036, T037, T038 | Claude Sonnet 5.5 | `high` | Pest + Pint | pending |
| T040 | [Accesibilidad de la rejilla y cierre de la entrega](T040-accesibilidad-rejilla.md) | FEATURE | PRF-119, PRF-120 | T039 | Claude Sonnet 5.5 | `medium` | Pest + Pint + `npm run build` | pending |

Nota de ejecución: las tareas T001–T012 las ejecutó una misma instancia del agente `programador` (Claude Opus 5.5), por indicación del agente principal, en lugar de alternar modelos por tarea.

## Inventario de puntos y matriz de cobertura

| Punto | Tipo | Tareas | Evidencia esperada | Estado |
| --- | --- | --- | --- | --- |
| PRF-001 | comportamiento | T001 | `AdminAuthenticationTest` | covered |
| PRF-002 | comportamiento | T001 | `AdminAuthenticationTest` | covered |
| PRF-003 | comportamiento | T001 | `AdminAuthenticationTest` | covered |
| PRF-004 | prohibición | T001 | `AdminAuthenticationTest` | covered |
| PRF-005 | prohibición | T001 | `AdminAuthenticationTest` | covered |
| PRF-006 | comportamiento | T001 | `CreateAdminUserCommandTest` | covered |
| PRF-007 | prohibición | T001 | `grep` del seeder | covered |
| PRF-008 | comportamiento | T001 | `AdminAuthenticationTest` | covered |
| PRF-009 | transversal | T001, T002, T003, T005, T006 | `AdminLayoutTest` | covered |
| PRF-010 | comportamiento | T002 | `ServiceManagementTest` | covered |
| PRF-011 | comportamiento | T002 | `ServiceManagementTest` | covered |
| PRF-012 | comportamiento | T002 | `ServiceManagementTest` | covered |
| PRF-013 | prohibición | T002 | `ServiceManagementTest` | covered |
| PRF-014 | prohibición | T007, T010 | `PublicBookingTest`, `AppointmentNotificationsTest` | covered |
| PRF-015 | prohibición | T004 | `CreateAppointmentTest` | covered |
| PRF-016 | prohibición | T006, T007 | `AdminAppointmentTest`, `PublicBookingTest` | covered |
| PRF-017 | comportamiento | T003 | `OpeningHoursManagementTest` | covered |
| PRF-018 | prohibición | T003 | `OpeningHoursManagementTest` | covered |
| PRF-019 | comportamiento | T003 | `OpeningHoursManagementTest` | covered |
| PRF-020 | comportamiento | T003 | `BookingSettingsManagementTest` | covered |
| PRF-021 | comportamiento | T005 | `ScheduleBlockManagementTest` | covered |
| PRF-022 | prohibición | T005 | `ScheduleBlockManagementTest` | covered |
| PRF-023 | comportamiento | T005 | `ScheduleBlockManagementTest` | covered |
| PRF-024 | comportamiento | T005 | `ScheduleBlockManagementTest` | covered |
| PRF-025 | comportamiento | T004 | `AvailabilityCalculatorTest` | covered |
| PRF-026 | prohibición | T004, T007 | `CreateAppointmentTest`, `PublicBookingTest` | covered |
| PRF-027 a PRF-037 | comportamiento y prohibición | T007 | `PublicBookingTest` | covered |
| PRF-038 | prohibición | T009 | `BookingPagesCachingTest` | covered |
| PRF-039 a PRF-044 | comportamiento y prohibición | T008 | `CustomerAppointmentTest` | covered |
| PRF-045 a PRF-049 | comportamiento y prohibición | T006 | `AgendaTest`, `AdminAppointmentTest` | covered |
| PRF-050 a PRF-054 | comportamiento | T010 | `AppointmentNotificationsTest`, `NotifyPendingAppointmentsCommandTest` | covered |
| PRF-055 | prohibición | T011 | `DeployCheckCommandTest` | covered |
| PRF-056 a PRF-058 | comportamiento | T009 | `BookingLinksAndSeoTest` | covered |
| PRF-059, PRF-060 | comportamiento y prohibición | T012 | `PrivacyPolicyTest` | covered (datos del cliente pendientes, bloquean publicar) |
| PRF-061 | prohibición | T007 | `PublicPagesHaveNoPublicPricingTest` | covered |
| PRF-062 | prohibición | T014 | `BookingAbuseLimitsTest` | covered |
| PRF-063 | prohibición | T014 | `BookingAbuseLimitsTest` | covered |
| PRF-064 | prohibición | T014 | `AppointmentPagePrivacyTest` | covered |
| PRF-065 | prohibición | T014 | `MailContentEscapingTest` | covered |
| PRF-066 | comportamiento | T014 | `AgendaTest` | covered |
| PRF-067 | prohibición | T014 | `DeployCheckCommandTest` | covered |
| PRF-068 | prohibición | T014 | `AdminAuthenticationTest` | covered |
| PRF-069 | comportamiento | T014 | `DeployCheckCommandTest` (timeout) y `AppointmentNotificationsTest` | covered |
| PRF-070 | comportamiento | T015, T019 | `MailSenderTest` (el «De» de los 5 correos sale del remitente configurado; `.env.example` usa el nombre del salón) | covered |
| PRF-071 | prohibición | T015, T019 | `DeployCheckCommandTest` (`APP_NAME`, `MAIL_FROM_ADDRESS` y `MAIL_FROM_NAME`) | covered |
| PRF-072 a PRF-075 | comportamiento y prohibición | T016, T019 | `MailBrandingTest` (marca, logo, enlace que se corta, botón para Outlook); pendiente la revisión visual en Gmail y Outlook | partial |
| PRF-076 | comportamiento | T018 | `BookingLinksAndSeoTest` | covered |
| PRF-088 | comportamiento | T019 | `BookingLinksAndSeoTest`; pendiente la revisión visual de la sección en el navegador | partial |
| PRF-077 a PRF-087 | comportamiento y prohibición | T017, T019 | `RescheduleAppointmentTest`, `AdminRescheduleAppointmentTest`, `AvailabilityCalculatorTest`, `AgendaTest`, `MailBrandingTest`, `MailContentEscapingTest`, `MailSenderTest`; pendiente el recorrido completo en el navegador (guardar, teclado y lector de pantalla) | partial |
| PRF-089, PRF-094 | comportamiento | T020 | `AdminLayoutTest` (estilos compartidos `.btn-gold`/`.btn-outline`/`.btn-danger-outline`); pendiente medir a 375 px en el navegador | partial |
| PRF-090 | comportamiento | T028 (sustituye a T021) | `AdminLayoutTest` (botón hamburguesa ≥44×44 px con `aria-expanded`/`aria-controls`/`aria-label`, menú desplazable con `overflow-y-auto`, 5 módulos con zona táctil ≥44 px, activo marcado, «Cerrar sesión» al final y separado, visible sin JavaScript); pendiente comprobar a 375 px y 1024 px en el navegador (Escape, foco, 10-12 módulos simulados) | partial |
| PRF-091 | comportamiento y prohibición | T022 | `ServiceManagementTest` (tarjetas, sin `<table>` ni `overflow-x-auto`); pendiente comprobar a 375 px en el navegador | partial |
| PRF-092, PRF-093 | comportamiento | T023 | `AgendaTest` (atajo «Mañana», botón flotante solo en móvil, botón de escritorio oculto en móvil); pendiente comprobar a 375 px en el navegador | partial |
| PRF-095 | comportamiento y prohibición | T023 | `AgendaTest` (WhatsApp desde la tarjeta, normalización a +34, teléfonos ya internacionales, entradas raras sin excepción, prefijo `00` — añadido al resolver el hallazgo M1 de `.ai/reviews/mobile-admin-ux.md`) | covered |
| PRF-096 | prohibición | T024 | `OpeningHoursManagementTest` (las 14 filas pueden envolver); pendiente comprobar a 360 px en el navegador | partial |
| PRF-097 | comportamiento | T025 | `PublicBookingTest` (celdas del calendario y horas con `min-h-11`); pendiente medir a 375 px en el navegador | partial |
| PRF-098 | comportamiento | T026 | `CustomerAppointmentTest` | covered |
| PRF-099 | comportamiento y prohibición | T029, T032, T033 | `AgendaTest` (pestañas Día/Semana/Mes, `vista`/`fecha` compartibles, valor inválido cae a Día, zona táctil `min-h-11 min-w-11`), `AgendaCalendarAccessibilityTest` (Anterior/Siguiente de Semana/Mes reutilizan el botón de 44 px de Día; T033 pone la navegación en una sola fila desde `md`, revisión M2/L2: celdas de 39-43 px de ancho a 360 px aceptadas por el usuario); pendiente comprobar a 375 px en el navegador | partial |
| PRF-100 | comportamiento | T030 | `AgendaWeekViewTest`; pendiente comprobar a 1024 px en el navegador | partial |
| PRF-101 | comportamiento | T030 | `AgendaWeekViewTest`; pendiente comprobar a 375 px en el navegador | partial |
| PRF-102 | comportamiento | T031 | `AgendaMonthViewTest`; pendiente comprobar en el navegador | partial |
| PRF-103 | comportamiento | T031 | `AgendaMonthViewTest` | covered |
| PRF-104 | comportamiento | T029, T030, T031 | `AgendaTest`, `AgendaWeekViewTest`, `AgendaMonthViewTest` | covered |
| PRF-105 | comportamiento | T030, T031, T033 | `AgendaWeekViewTest`, `AgendaMonthViewTest` (días sin horario y cierres totales marcados «Cerrado» en texto; T033 añade el cierre de capacidad reducida en Semana, que antes solo marcaba Día) | covered |
| PRF-106 | comportamiento | T030, T031, T032 | `AgendaMonthViewTest` (hoy con `aria-current`), `AgendaCalendarAccessibilityTest` (hoy en Semana con texto «hoy»/«Hoy» además del borde/anillo) | covered |
| PRF-107 | comportamiento | T030, T031, T032, T033 | `AgendaMonthViewTest` y `AgendaCalendarAccessibilityTest` (`role="row"`/`"gridcell"`/`"columnheader"` coherentes en Semana escritorio, Mes y el calendario público de `/reservas`, revisión `.ai/reviews/agenda-calendar-views.md` M3); pendiente navegar con lector de pantalla en el navegador | partial |

## Casos revisados que no aplican

- **Datos existentes:** no hay citas ni servicios previos. Solo se crean el horario y los ajustes iniciales.
- ***Feature flag*:** no hace falta. No se publica hasta tener los 4 PRs.
- **Exportaciones:** fuera de alcance.
