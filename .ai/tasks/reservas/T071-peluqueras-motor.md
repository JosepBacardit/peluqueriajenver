# T071 — Peluqueras: motor de disponibilidad

- **Tipo:** ARCHITECTURAL
- **Puntos de referencia:** PRF-162, PRF-165, PRF-153 y PRF-159 (modificados)
- **Depende de:** T070
- **Modelo:** Claude Opus 5.5 · **Esfuerzo:** `high`
- **Motivo:** Cambia el cálculo que evita el *overbooking* en la web, el panel y «Cabe».
- **Estado:** done
- **PR / rama:** `feature/hairdresser-choice` (desde `main` `b3e879f`).

## Objetivo

`AvailabilityCalculator` con peluquera opcional: capacidad = peluqueras activas, cierres por peluquera, citas con y sin peluquera; `CreateAppointment`/`RescheduleAppointment` con peluquera; fuera el campo «Capacidad» de Ajustes; ayuda de tests `setStaffCount()`.

## Verificación

Pest (fuerza bruta por minutos) + Pint.
