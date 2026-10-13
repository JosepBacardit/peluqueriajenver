# T072 — Cierres por peluquera

- **Tipo:** FEATURE
- **Puntos de referencia:** PRF-170, PRF-021 (modificado)
- **Depende de:** T071
- **Modelo:** Claude Sonnet 5.5 · **Esfuerzo:** `medium`
- **Motivo:** Formulario y validación acotados.
- **Estado:** in progress
- **PR / rama:** `feature/hairdresser-choice` (desde `main` `b3e879f`).

## Objetivo

«Cierre total» o «No está {peluquera}» (varias a la vez, un cierre por peluquera, `capacity_reduction = 1`), lista y aviso de citas afectadas solo de esa peluquera; los «Reducir N» antiguos siguen mostrándose.

## Verificación

Pest + Pint.
