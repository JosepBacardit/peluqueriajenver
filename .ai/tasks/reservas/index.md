# Tareas: reservas online y panel de gestión

- **Especificación:** [../../specs/reservas.md](../../specs/reservas.md)
- **Flujo de trabajo:** ARCHITECTURAL, en 4 PRs apilados:
  - PR 1, `feature/booking-admin-foundation`: T001–T003;
  - PR 2, `feature/booking-availability`: T004–T006;
  - PR 3, `feature/booking-public`: T007–T009;
  - PR 4, `feature/booking-notifications`: T010–T012.
  - Ajustes tras la revisión visual del usuario (2026-10-05), `feature/booking-admin-tweaks` apilada sobre `feature/booking-review-fixes`: T015–T018.
- **Estado general:** implementación (T001–T012), revisión (T013) y resolución (T014) hechas. Queda pendiente el hallazgo M4 de la revisión (analítica y consentimiento, decisión del usuario) y los datos del cliente que bloquean publicar. De los ajustes 2026-10-05: T015, T016, T017 y T018 hechos; T017 (mover/editar cita) queda pendiente de la comprobación manual en el navegador.

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
| PRF-070 | comportamiento | T015 | `DeployCheckCommandTest` | covered |
| PRF-071 | prohibición | T015 | `DeployCheckCommandTest` | covered |
| PRF-072 a PRF-075 | comportamiento y prohibición | T016 | `MailContentEscapingTest`, revisión visual en Gmail/Outlook | covered |
| PRF-076 | comportamiento | T018 | `BookingLinksAndSeoTest` | covered |
| PRF-077 a PRF-086 | comportamiento y prohibición | T017 | `RescheduleAppointmentTest`, `AdminRescheduleAppointmentTest`, `AvailabilityCalculatorTest`, `AgendaTest`, `MailBrandingTest`, `MailContentEscapingTest`; revisión manual en el navegador | covered |

## Casos revisados que no aplican

- **Datos existentes:** no hay citas ni servicios previos. Solo se crean el horario y los ajustes iniciales.
- ***Feature flag*:** no hace falta. No se publica hasta tener los 4 PRs.
- **Exportaciones:** fuera de alcance.
