# T075 — Agenda por peluquera y filtro «Peluquera»

- **Tipo:** ARCHITECTURAL
- **Puntos de referencia:** PRF-171, PRF-172, PRF-109 y PRF-110 (modificados)
- **Depende de:** T071, T072
- **Modelo:** Claude Opus 5.5 · **Esfuerzo:** `medium`
- **Motivo:** Cambia el reparto en columnas y la rejilla, con muchos tests de presentación.
- **Estado:** done
- **PR / rama:** `feature/hairdresser-choice` (desde `main` `b3e879f`), commit `c282e4e`.

## Objetivo

Columnas con nombre, citas con peluquera en su columna, «Cualquiera» marcada, sombreado del cierre de una peluquera, filtro `peluquera` en Día, Semana y Mes, conservado en `volver`.

## Verificación

Pest + Pint + navegador.

## Comprobación en el navegador

Chrome, `iframe` de 360 px sobre la base local (2026-10-13): Día con las columnas «Jennifer» e «Isabel», citas sin peluquera con borde discontinuo y sin *scroll* horizontal (358 px); con `peluquera=1`, solo la columna de Jennifer y sus tarjetas. `/reservas` muestra «¿Con qué peluquera?» con «Cualquier peluquera» marcada.
