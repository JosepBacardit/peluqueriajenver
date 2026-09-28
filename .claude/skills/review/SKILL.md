---
name: review
description: Revisa de forma independiente un cambio en Laravel sin modificar archivos de implementación.
---

# Revisión independiente

Lee `AGENTS.md`, `procedures/review.md`, el flujo de trabajo seleccionado y el conocimiento aplicable. Inspecciona el diff y los tests antes de sacar conclusiones.

Durante la revisión, no edites código de aplicación, tests, configuración, migraciones ni archivos generados. Escribe los hallazgos en `.ai/reviews/<topic>.md` con severidad, evidencia de archivo/línea, impacto, recomendación y estado. Registra `No findings` cuando ese sea el resultado. El agente `programador`, no el revisor, implementa las correcciones.

## Comprobaciones obligatorias

Además de revisar el diff, comprueba siempre estos puntos, que son los fallos que más se repiten en las revisiones ([[syntheses/lecciones-revisiones|lecciones]]):

- **Reproduce la evidencia, no la leas.** Si una tarea o un informe cita un test, un comando, una captura o una tabla, ábrelo o ejecútalo. Un punto marcado como cubierto con una frase genérica o con un recuento global de tests («172 tests en verde») no está cubierto.
- **Contrato cliente-servidor.** Para cada campo validado en los dos lados, compara la regla del frontend con la del backend (valores, obligatoriedad, formato) y comprueba que el campo realmente se envía.
- **Comentarios.** Los comentarios y docblocks del diff y de su entorno describen el comportamiento actual, no el anterior. Si el cambio elimina o renombra algo, busca las menciones que queden.
- **Multi-tenant.** En aplicaciones multi-tenant, toda clave de `updateOrCreate`/`firstOrCreate`, consulta o archivo guardado queda acotada al tenant.
- **Valores mágicos.** Si el cambio corrige una constante o un literal, busca con grep si el mismo valor está repetido en otros archivos.
- **Accesibilidad de los componentes interactivos.** En menús, diálogos, desplegables y formularios del frontend, comprueba con el teclado (Tab, Shift+Tab, Enter, Espacio, Escape) que:
  - el foco queda atrapado mientras un menú o diálogo está abierto, el fondo queda `inert`, y al cerrar el foco vuelve al disparador;
  - los disparadores son `<button>` con `aria-expanded` y `aria-controls`, y los conmutadores llevan `aria-pressed`;
  - los errores de validación están enlazados con `aria-describedby` y `aria-invalid`.

  Comprueba también que los tests e2e lo verifican, no solo el comportamiento visual.
- **Imágenes (proyectos propios, no micasino).** Toda imagen que se sirve en la web es WebP optimizada: redimensionada al uso con `srcSet`, con `width`/`height` (sin CLS), `alt` y `loading="lazy"` fuera de la primera pantalla. Comprueba en el HTML o en la red que no se sirve ningún PNG ni JPG, salvo las excepciones aprobadas por el usuario (favicon e iconos de app, emails, PDFs y `og:image`) ([[knowledge/coding-style|coding-style]]).
- **Textos comerciales.** Contrasta cada afirmación del texto (precios, plazos, resultados, capacidades, clientes, testimonios, certificaciones) con la lista de hechos confirmados por el usuario de la spec o del proyecto. Cualquier promesa de resultado o capacidad que no esté en esa lista es un hallazgo de texto que decide el usuario. Comprueba también que un test bloquea los términos prohibidos en toda la fuente del texto, no solo en un archivo ([[syntheses/lecciones-revisiones|patrón H]]).
- **Entorno de destino.** Lo que se da por hecho sobre el entorno (disco persistente, red, colas, programador de tareas) coincide con el entorno donde se despliega.

<!-- obsidian-links:start -->

## Enlaces

- [[AGENTS]]
- [[procedures/review|procedimiento: review]]
- [[methodology/workflow|workflow]]
- [[skills/fix-review/SKILL|skill: fix-review]]
- [[syntheses/lecciones-revisiones|lecciones de las revisiones]]

<!-- obsidian-links:end -->
