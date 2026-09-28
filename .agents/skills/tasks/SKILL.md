---
name: tasks
description: Analiza una especificación aprobada y la descompone en archivos de tarea ordenados y verificables de forma independiente, que planifican la implementación y trazan cada punto de referencia PRF.
---

# Planificación de tareas

Usa esta *skill* cuando ya exista una especificación y antes de empezar cualquier implementación: cuando haya que convertirla en un plan ejecutable, cuando haya que ampliar un conjunto de tareas porque la especificación ha cambiado o cuando alguien pida las tareas de una especificación. No la uses para planificar trabajo sin especificación: para un cambio SIMPLE, `methodology/workflow.md` ya admite una tarea clasificada sin especificación, y en el trabajo FEATURE o ARCHITECTURAL la especificación va primero (*skill* `spec`).

Esta *skill* se ocupa del segundo nivel de planificación de `methodology/sdd.md`: la especificación dice *qué debe ser cierto* y las tareas dicen *cómo se entregará*. Antes de escribir ninguna tarea, lee `AGENTS.md`, `methodology/sdd.md`, `methodology/tdd.md`, `methodology/workflow.md`, el procedimiento aplicable de `procedures/` y `knowledge/model-selection.md`. Lee también el `AGENTS.md` del proyecto de destino y sus reglas: los archivos de tarea son el sitio de sus comandos, directorios y restricciones concretos. Escribe las tareas en español.

## 1. Una tarea es técnica; la especificación no

La especificación nunca nombra código. Un archivo de tarea debe hacerlo, porque la tarea es el plan que ejecuta el implementador.

- Nombra los archivos, directorios, clases, rutas, tablas, columnas, comandos, claves de configuración y tests que la tarea tocará o creará, en la medida en que se conozcan antes de implementar.
- Indica los comandos de verificación exactos del proyecto de destino, con cualquier requisito previo de base de datos, entorno o herramientas que ese proyecto registre.
- No copies el texto de la especificación en la tarea como si fuera información nueva: referencia los identificadores de los puntos y añade solo el plan.
- No introduzcas comportamiento que la especificación no exija. Si al planificar aparece un hueco, una contradicción o un caso no contemplado, detente y plantéalo como un punto nuevo o como una pregunta abierta en la especificación; no lo decidas dentro de una tarea.

## 2. Analiza la especificación antes de decidir nada

No elijas de antemano cuántas tareas habrá. Dedúcelo.

1. **Lee la especificación entera**, incluidos los no-objetivos, las prohibiciones, la transición de los datos existentes, los criterios de aceptación, el plan de verificación, los riesgos y las preguntas abiertas.
2. **No planifiques sobre una base inestable.** Detente e informa si el estado no es `Approved` (salvo que el solicitante pida expresamente planificar un `Draft`), si una pregunta abierta bloquea algún punto, si un punto no es verificable o si dos puntos se contradicen.
3. **Haz un inventario de puntos.** Una fila por punto `PRF-*`, con: su tipo (comportamiento, prohibición o `Withdrawn`), las superficies de usuario a las que afecta, las áreas de código responsables, si cambia datos guardados, si se puede demostrar con un test automático y en qué capa, y si es transversal (una regla que toda tarea debe respetar, como «sin desplazamiento horizontal» o «no se puede perder ninguna acción»).
4. **Localiza el código responsable** de cada punto leyendo el repositorio, sin suponer. Anota las rutas concretas en el inventario: serán el alcance de las tareas.
5. **Clasifica el coste de cada punto** en uno o más ciclos de implementación. Un punto que no quepa en un ciclo se reparte entre tareas por superficie o por capa, nunca en «primera parte / segunda parte» del mismo comportamiento.

Guarda el inventario en el índice de la carpeta de tareas, en el orden de los puntos de la especificación, para que la descomposición se pueda auditar.

## 3. Decide cuántas tareas

El número de tareas es el resultado de agrupar el inventario con estas reglas:

- **Cada punto está cubierto por al menos una tarea.** No se omite ninguno; un punto `Withdrawn` se registra como deliberadamente sin cubrir.
- **Una tarea es un ciclo de implementación**: un resultado coherente, que se puede probar y revisar por sí solo, y lo bastante pequeño para terminarlo sin traspaso.
- **Una tarea se ocupa de un área o de una capa.** Divide un grupo que obligaría a tocar dos límites no relacionados; esa división suele marcar también el orden de dependencias.
- **Una tarea debe poder verificarse de forma independiente.** Si una tarea candidata no tiene ningún criterio de aceptación comprobable sin una tarea posterior, únela a la tarea que aporta esa evidencia.
- **Divide cuando cambian el modelo o el esfuerzo.** Dos grupos que `knowledge/model-selection.md` ejecutaría con modelos o esfuerzos distintos son dos tareas.
- **Los puntos transversales no son una tarea de implementación propia.** Se listan en cada tarea a la que condicionan y tienen una tarea final de barrido que los verifica en todo el cambio.
- **Los cimientos compartidos van primero.** Cuando varios grupos dependen del mismo componente, contrato o migración nuevos, esos cimientos son la `T001` y el resto depende de ella.
- **El TDD da forma a la primera tarea de un grupo, no es una fase aparte.** No crees una tarea «escribir los tests» y otra «implementar» para el mismo comportamiento: cada tarea de implementación sigue su propio ciclo rojo–verde–refactorización. Una tarea aparte con el test que falla solo se justifica cuando hay que reproducir primero un defecto o cuando un test de aceptación condiciona varias tareas posteriores.
- **La revisión es siempre una tarea propia**, de tipo `REVIEW`, que depende de todas las tareas de implementación, con el modelo y el esfuerzo de Claude que exija `knowledge/model-selection.md`, y con los hallazgos escritos solo en `.ai/reviews/<spec-folder>/`.
- **La comprobación de completitud forma parte de la tarea final.** La tarea de revisión o una tarea de cierre explícita debe producir la matriz de cobertura que exige `methodology/sdd.md`.
- **No crees de antemano una tarea para resolver hallazgos de revisión.** Los hallazgos se desconocen hasta que se hace la revisión; añade esa tarea cuando aparezcan, siguiendo `procedures/review.md` y la *skill* `fix-review`.

Comprueba que el resultado es razonable antes de escribir los archivos: una especificación cuyos puntos caen en tres superficies y unos cimientos compartidos no produce quince tareas, y una con cincuenta puntos no produce dos.

## 4. Dónde van los archivos

Sigue `methodology/sdd.md` al pie de la letra:

- Una carpeta por especificación, con el nombre del archivo de la especificación: `.ai/tasks/<spec-file-name-without-extension>/`. Créala en el proyecto de destino si no existe. Si el proyecto ya tiene establecida otra variante de nombre de carpeta, mantenla.
- Un archivo por tarea, `T<NNN>-<short-description>.md`, con identificadores de tres dígitos de ancho fijo que empiezan en `T001` y se incrementan dentro de la carpeta.
- Un `index.md` en la misma carpeta. Es el único índice de tareas: la especificación solo lo enlaza.
- Los identificadores son estables. Nunca se renumeran ni se reutilizan: una tarea descartada se queda en su sitio con el estado `cancelled` y el motivo.

## 5. Cómo se escribe un archivo de tarea

Cada archivo de tarea empieza con esta cabecera, en este orden:

- **Tipo**: `SIMPLE`, `FEATURE`, `ARCHITECTURAL` o `REVIEW`.
- **Puntos de referencia**: todos los identificadores `PRF-*` que cubre la tarea, incluidos los transversales que debe respetar.
- **Depende de**: identificadores de tarea, o `—`.
- **Modelo** y **Esfuerzo**: elegidos según `knowledge/model-selection.md` para esta tarea, nunca heredados de la especificación ni de otra tarea.
- **Motivo**: por qué ese modelo y ese esfuerzo, en una o dos frases.
- **Estado**: `pending`, `in progress`, `blocked`, `done` o `cancelled`.

Después, estas secciones:

1. **Objetivo**: el resultado en un párrafo, con el vocabulario del producto.
2. **Contexto**: lo que existe hoy en el código que la tarea cambia, con rutas, y todo lo que el implementador tendría que redescubrir.
3. **Plan**: los pasos ordenados, con los archivos que se crean o cambian, los contratos que se respetan y los límites que impone la guía de arquitectura del proyecto. Suficiente para un implementador competente que no haya participado en la conversación, sin llegar a ser la implementación.
4. **Fuera de alcance**: lo que la tarea deja deliberadamente a otra, nombrándola si existe.
5. **Criterios de aceptación**: afirmaciones comprobables, cada una trazable a uno de los puntos de la tarea, que cubren el camino principal, los caminos de fallo y cada prohibición entre esos puntos.
6. **Plan de pruebas**: qué tests se añaden o cambian, en qué capa y qué demuestra cada uno. Sigue `methodology/tdd.md`: el responsable del comportamiento decide la capa. Cuando un punto no se pueda demostrar automáticamente, dilo y da el escenario manual y la evidencia que hay que registrar.
7. **Verificación**: los comandos exactos del proyecto de destino, con sus requisitos previos, y los pasos de formato o compilación que el proyecto exija antes de terminar. Si la especificación declara un entorno de destino, indica cómo se verifica contra él (no solo contra el equivalente local o de Docker).
8. **Riesgos**: qué podría romperse fuera del alcance de la tarea y cómo demuestra la tarea que no ha pasado.

**Evidencia de cobertura.** Una tarea solo pasa a `done`, y un punto solo cuenta como cubierto, con evidencia reproducible: el nombre del test concreto que lo demuestra o, si es manual, una tabla escenario → resultado con la evidencia guardada (y la captura o el archivo citados deben existir). Nunca bastan una frase genérica («verificado manualmente») ni un recuento global de la suite («172 tests en verde»). Si falta evidencia, el estado es `blocked` o `in progress`, no `done`.

**El código y los tests no citan los identificadores `PRF-*`.** La trazabilidad vive solo en `.ai/`, que en algunos proyectos no se sube a Git. Un test explica con su nombre (y, si hace falta, con un comentario breve) el comportamiento que demuestra; la relación entre el test y el punto se registra en el plan de pruebas de la tarea y en la matriz de cobertura.

## 6. El índice

- El `index.md` de la carpeta de tareas contiene: la ruta de la especificación, el flujo de trabajo, el estado general, el inventario de puntos de la sección 2, la tabla de tareas y la matriz de cobertura.
- La tabla de tareas lleva las columnas que exige `methodology/sdd.md`: ID, título enlazado de la tarea, tipo, puntos de referencia, depende de, modelo, esfuerzo, verificación y estado.
- La matriz de cobertura relaciona cada punto `PRF-*` con sus tareas, la evidencia esperada y un estado que solo puede ser `covered` cuando todas sus tareas estén en `done` con evidencia satisfactoria.
- La especificación enlaza este índice en su sección **Tareas** y no copia la tabla. Nada más de la especificación cambia: planificar no es el momento de editar puntos.

## 7. Cubre todos los casos

Recorre esta lista y, para cada caso, planifícalo o anota en `index.md` por qué no aplica:

- Cada punto, incluidas las prohibiciones: cada prohibición necesita una tarea que demuestre que lo prohibido se rechaza.
- Los puntos que el comportamiento actual ya cumple: también se asignan a una tarea, cuyo trabajo es demostrarlo con un test que fallaría si hubiera una regresión.
- Los puntos transversales: en cada tarea a la que condicionan, más el barrido final.
- Los datos guardados: migración, relleno, marcha atrás y qué pasa con los registros creados antes del cambio.
- El orden y las dependencias: ninguna tarea depende de una posterior y el grafo de dependencias no tiene ciclos.
- Estados vacíos y de primer uso, límites, permisos por rol, acciones simultáneas o repetidas y dependencias no disponibles, donde la especificación los exija.
- Las superficies que la especificación nombra y es fácil olvidar: pantallas sin autenticación, páginas públicas o de clientes, áreas de administración o de propietario, tareas programadas, notificaciones, exportaciones y documentos generados.
- La verificación que no se puede automatizar: nombrada, con su escenario manual.
- El orden de despliegue y cualquier *feature flag* o paso de despliegue que necesite el cambio.
- La tarea de revisión y la comprobación de completitud.

## 8. Control de calidad antes de la entrega

No entregues un conjunto de tareas hasta que se cumpla todo esto:

- Cada punto `PRF-*` aparece en los **Puntos de referencia** de al menos una tarea, y la matriz de cobertura lista todos los puntos.
- Ninguna tarea lista un punto que sus criterios de aceptación no comprueben de verdad.
- Cada tarea tiene tipo, dependencias, modelo, esfuerzo, motivo, criterios de aceptación, plan de pruebas y comandos de verificación exactos.
- Cada criterio de aceptación dice qué evidencia concreta lo demostrará (test nombrado o escenario manual con su registro).
- Cada tarea se puede verificar de forma independiente y cabe en un ciclo de implementación.
- El orden de dependencias no tiene ciclos y la tarea de revisión depende de todas las de implementación.
- Ninguna tarea contradice la especificación, inventa comportamiento ni decide una pregunta abierta.
- La especificación enlaza el índice de tareas y no mantiene una copia propia.
- Un implementador competente que no haya participado en la conversación podría ejecutar cualquier tarea solo con su archivo.

## 9. Después de aprobar el plan

Cambia el plan solo cuando cambie la especificación o cuando la ejecución demuestre que una tarea estaba mal planteada. Mantén estables los identificadores, añade tareas nuevas en lugar de reescribir las antiguas, actualiza las filas de cobertura afectadas y registra en `index.md` qué cambió y por qué. Cuando se añada más tarde un punto nuevo a la especificación, recibe una tarea nueva o se añade a una tarea pendiente; nunca queda sin cubrir.

<!-- obsidian-links:start -->

## Enlaces

- [[methodology/sdd|sdd]]
- [[skills/spec/SKILL|skill: spec]]
- [[methodology/tdd|tdd]]
- [[methodology/workflow|workflow]]
- [[knowledge/model-selection|model-selection]]
- [[procedures/review|procedimiento: review]]
- [[skills/fix-review/SKILL|skill: fix-review]]
- [[syntheses/lecciones-revisiones|lecciones de las revisiones]]

<!-- obsidian-links:end -->
