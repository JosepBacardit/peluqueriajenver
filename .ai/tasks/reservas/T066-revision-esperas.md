# T066 — Revisión independiente de las esperas

- **Tipo:** REVIEW
- **Puntos de referencia:** PRF-151 a PRF-160
- **Depende de:** T062–T065
- **Modelo:** Claude Opus 5.5 · **Esfuerzo:** `medium`
- **Motivo:** cambio del núcleo de disponibilidad y de la agenda.
- **Estado:** pending
- **PR / rama:** `feature/service-wait-times`.

## Plan

Revisión en un contexto limpio con la *skill* `review`, con los hallazgos en `.ai/reviews/service-wait-times.md`. Hay que prestar atención a:

- el *overbooking*: momentos revisados y cierres en la espera;
- la concurrencia, que tiene que mantener el mismo bloqueo;
- la compatibilidad con las citas sin esperas;
- la no revelación pública.
