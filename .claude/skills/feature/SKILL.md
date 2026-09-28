---
name: feature
description: Implementa una funcionalidad Laravel usando la especificación, el TDD y los límites Request-to-Action del Developer Brain.
---

# Implementación de funcionalidades

Lee `AGENTS.md`, `methodology/sdd.md`, `methodology/tdd.md`, `procedures/feature.md` y la guía de arquitectura de Laravel antes de implementar. Usa el flujo FEATURE salvo que el cambio se califique como SIMPLE según `methodology/workflow.md`.

En el proyecto de destino, crea solo los registros `.ai/specs/`, `.ai/tasks/` y `.ai/reviews/` necesarios. Escribe la especificación con la *skill* `spec` (puntos de referencia `PRF-001`…) y descomponla en tareas con la *skill* `tasks`, que mantiene el índice único en `.ai/tasks/<spec>/index.md`. Antes de cada tarea, confirma su tipo, dependencias, criterios de aceptación, modelo y esfuerzo de razonamiento según `knowledge/model-selection.md`. El código y los tests no citan los identificadores `PRF-*`. Antes de finalizar, verifica que cada punto de referencia esté cubierto por tareas completadas y evidencia satisfactoria. Mantén los controladores limitados a HTTP; valida en Requests, mapea a DTOs, coloca los casos de uso en Actions y las APIs externas en Services, y devuelve Resources o Views. Implementa con TDD y deja un resultado de verificación claro.

<!-- obsidian-links:start -->

## Enlaces

- [[AGENTS]]
- [[methodology/sdd|sdd]]
- [[skills/spec/SKILL|skill: spec]]
- [[skills/tasks/SKILL|skill: tasks]]
- [[methodology/tdd|tdd]]
- [[procedures/feature|procedimiento: feature]]
- [[methodology/workflow|workflow]]
- [[knowledge/model-selection|model-selection]]
- [[knowledge/architecture|architecture]]
- [[knowledge/patterns/request-to-action|request-to-action]]

<!-- obsidian-links:end -->
