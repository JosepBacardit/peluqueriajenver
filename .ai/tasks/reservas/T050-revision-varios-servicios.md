# T050 — Revisión independiente de varios servicios por cita

- **Tipo:** REVIEW
- **Puntos de referencia:** PRF-125 a PRF-132 y los PRF ajustados
- **Depende de:** T046, T047, T048, T049
- **Modelo:** Claude Opus 5.5 · **Esfuerzo:** `medium` (subir si hay hallazgos de concurrencia)
- **Motivo:** cambio de arquitectura del modelo de datos y de las Actions con bloqueo, en un contexto limpio, nunca con la instancia que lo implementó.
- **Estado:** pending
- **PR / rama:** `feature/multi-service-appointments`

## Alcance

`git diff feature/agenda-service-filter...feature/multi-service-appointments`, contrastado con la especificación y las tareas T046 a T049. Hallazgos en `.ai/reviews/multi-service-appointments.md`, ordenados por severidad, con evidencia, y comprobación en el navegador de `/reservas` y del panel a 375 px y en escritorio.

Puntos que no hay que olvidar:

- la concurrencia en las dos Actions: el bloqueo primero, el `UPDATE` condicional antes de sustituir los servicios, sin huérfanos ni mezclas;
- que el precio congelado no se filtre a ninguna página ni a ningún correo;
- la validación de `servicio[]` (duplicados, máximo y mezcla con servicios no reservables);
- el número fijo de consultas de la agenda.