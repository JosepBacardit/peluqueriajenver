# T070 — Peluqueras: modelo, migración y apartado del panel

- **Tipo:** FEATURE
- **Puntos de referencia:** PRF-161, PRF-162 (en parte), PRF-163, PRF-164 (columnas)
- **Depende de:** T069
- **Modelo:** Claude Sonnet 5.5 · **Esfuerzo:** `medium`
- **Motivo:** CRUD acotado con una migración aditiva.
- **Estado:** done
- **PR / rama:** `feature/hairdresser-choice` (desde `main` `b3e879f`), commit `e6a4d0c`.

## Objetivo

Tabla `hairdressers` con Jennifer e Isabel, columnas nulas en `appointments` y `schedule_blocks`, modelo `Hairdresser`, apartado «Peluqueras» (crear, renombrar, activar/desactivar, sin borrar) y rechazo de la desactivación con citas futuras asignadas.

## Verificación

Pest + Pint.
