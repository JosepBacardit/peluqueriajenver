# T078 — Resolver la revisión de «Elegir peluquera»

- **Tipo:** ARCHITECTURAL
- **Puntos de referencia:** PRF-163, PRF-165, PRF-170, PRF-171
- **Depende de:** T077
- **Modelo:** Claude Opus 5.5 · **Esfuerzo:** `high`
- **Motivo:** el hallazgo H1 cambia el motor de disponibilidad (reparto exacto sin relevos).
- **Estado:** done
- **PR / rama:** `feature/hairdresser-choice`, commits `882e085` (H1), `a98a1b2` (M1), `70a32eb` (M2), `954a015` (M3), `f1240cc` (L1), `5daf4d0` (L2), `ba552b9` (L3 y L4).

## Objetivo

Resolver los 8 hallazgos de `.ai/reviews/hairdresser-choice.md` con las decisiones del usuario («las recomendadas, corrige todo»).

## Verificación

Suite completa: 1009 tests en verde y 1 omitido. Pint sin cambios pendientes en los 52 archivos PHP de la rama. Coste del reparto global (calendario de un mes con 10 citas al día): 0,33 s con citas y cierres de peluquera, 0,19 s sin peluqueras asignadas.
