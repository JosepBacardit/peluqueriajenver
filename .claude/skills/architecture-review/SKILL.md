---
name: architecture-review
description: Evalúa de forma independiente los cambios de arquitectura en Laravel buscando riesgos de límites, contratos y dependencias, sin editar código.
---

# Revisión de arquitectura

Úsala solo para trabajo ARCHITECTURAL o para una revisión de arquitectura explícita. Lee `knowledge/architecture.md`, `knowledge/laravel.md`, `methodology/workflow.md` y el registro `.ai/specs/` correspondiente. Evalúa la propiedad de los módulos, la dirección de las dependencias, los contratos públicos, las migraciones de datos, los riesgos operativos, la estrategia de tests y los supuestos de marcha atrás.

No modifiques archivos de implementación. Añade hallazgos accionables a `.ai/reviews/<topic>.md`, con severidad, evidencia, impacto, recomendación y estado. El agente `programador` es responsable de todos los cambios posteriores.

<!-- obsidian-links:start -->

## Enlaces

- [[knowledge/architecture|architecture]]
- [[knowledge/laravel|laravel]]
- [[methodology/workflow|workflow]]
- [[procedures/review|procedimiento: review]]

<!-- obsidian-links:end -->
