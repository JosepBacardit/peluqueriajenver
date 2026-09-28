---
name: spec
description: Escribe una especificación funcional rigurosa y verificable antes de implementar, con puntos de referencia PRF-001…, que cubra todos los casos relevantes y diga explícitamente lo que no debe ocurrir, sin citar nunca código.
---

# Redacción de especificaciones

Usa esta *skill* cuando un cambio necesite una especificación, cuando haya que ampliar una existente o cuando alguien la pida. No la uses para documentar algo que ya funciona y no requiere ninguna decisión: una especificación registra decisiones y resultados exigidos.

Sigue las convenciones del proyecto en el que trabajas. Lee antes `AGENTS.md` y `methodology/sdd.md`: la metodología decide el flujo de trabajo, el desglose en tareas (*skill* `tasks`), la revisión y los registros de trazabilidad; esta *skill* se ocupa de la calidad de la especificación. Guárdala en `.ai/specs/<topic>.md`, un archivo por especificación, y escríbela en español.

## 1. Una especificación es funcional, nunca técnica

La especificación dice qué debe ser cierto para las personas que usan el producto. Nunca dice cómo se construirá.

- No cites, pegues, resumas ni referencies código: ni rutas de archivo, ni nombres de clases, métodos, tablas, columnas, rutas o *endpoints*, ni códigos de estado o de error, ni librerías, *frameworks*, comandos o claves de configuración, ni *diffs*.
- Escribe con el vocabulario del dominio y de la interfaz que usan las personas: los registros, roles, pantallas, acciones, mensajes, cantidades y fechas que el producto ya nombra.
- Expresa cada garantía como comportamiento observable, no como el mecanismo que la proporciona. Di que dos registros nunca pueden acabar con el mismo identificador aunque dos personas actúen a la vez, en lugar de nombrar la salvaguarda que lo impide.
- Describe los fallos como lo que se le dice a la persona y lo que queda sin cambiar.
- Cuando una restricción técnica forme parte de la decisión, expresa su efecto: qué pasa a ser posible o imposible, qué se conserva y qué se pierde.

Entender el producto lo suficiente para escribir esto puede exigir leer el código. Esa investigación informa la especificación, pero nunca aparece en ella.

## 2. Establece los hechos antes de escribir

- Averigua cómo se comporta hoy el área y descríbelo funcionalmente, para que la diferencia que introduce el cambio sea demostrable.
- No inventes comportamientos, límites, valores ni textos de interfaz. Toda afirmación es un comportamiento observado, una decisión del solicitante o una pregunta abierta.
- Reúne las reglas que ya condicionan el cambio: quién puede actuar, a qué información puede acceder, cómo se tratan cantidades y fechas, los límites de lo que se sube o se exporta y cualquier obligación externa que afecte al área.
- Declara el **entorno de destino** cuando condicione el comportamiento, expresado por su efecto: por ejemplo, que lo guardado debe seguir disponible tras un despliegue o una hibernación, o que no hay procesos en segundo plano. Es una restricción del cambio, no un detalle de implementación.
- Pregunta al solicitante solo lo que no se pueda deducir: reglas de negocio, prioridades y compromisos. Registra cada respuesta, y cada pregunta sin responder en `Preguntas abiertas` con un valor por defecto propuesto y la consecuencia de elegirlo.

## 3. Estructura

Escribe estas secciones, en este orden. Una sección que no aplique se indica en una línea con el motivo; no se rellena.

1. **Título y estado**: `Draft`, `Approved`, `In progress`, `Implemented` o `Superseded by <spec>`.
2. **Flujo de trabajo**: el flujo que sigue el cambio y por qué es el más ligero que lo protege.
3. **Resultado para el usuario**: qué podrá hacer después un actor concreto que hoy no puede hacer.
4. **Comportamiento actual**: qué ocurre hoy, qué falta o está mal, y la consecuencia de dejarlo así.
5. **Alcance**: los cambios observables que exige la especificación.
6. **No-objetivos y prohibiciones**: véase la sección 5.
7. **Especificación funcional**: los puntos de referencia numerados; véase la sección 4.
8. **Datos existentes y transición**: qué pasa con todo lo creado antes del cambio: qué se conserva, qué cambia, qué deben hacer las personas y qué notarán.
9. **Criterios de aceptación**: las condiciones que deben ser demostrablemente ciertas para aceptar el trabajo, cada una trazable a uno o más puntos.
10. **Plan de verificación**: para cada punto, la comprobación que lo demuestra, descrita como un escenario observable: situación de partida, acción y resultado esperado.
11. **Riesgos y marcha atrás**: qué puede fallar, cómo se detecta y cómo se deshace el cambio.
12. **Preguntas abiertas**: con responsable y valor por defecto; vacía cuando no hay ninguna.
13. **Tareas**: un enlace a `.ai/tasks/<spec>/index.md` cuando exista. La especificación no copia el índice de tareas.

## 4. Redacción de los puntos de referencia

- Identifica cada punto con un identificador estable `PRF-<NNN>` (punto de referencia) de tres dígitos: `PRF-001`, `PRF-002`… Los identificadores duran lo que dura la especificación: nunca se renumeran ni se reutilizan. Un punto que deja de aplicar se queda en su sitio, marcado `Withdrawn` y con el motivo.
- Un punto expresa un único requisito verificable. Si necesita una «y» para unir dos comportamientos que podrían aceptarse por separado, divídelo.
- Usa verbos normativos: **debe**, **no debe**, **puede**. Reserva **debería** para recomendaciones reales e indica quién decide.
- Cada punto de comportamiento nombra el actor y su rol, lo que lo desencadena, lo que ya debe ser cierto, lo que la persona ve como resultado y lo que queda registrado después.
- Cuantifícalo todo: límites, longitudes, formatos, redondeos, unidades, zonas horarias, cuántos elementos aparecen a la vez y cuánto tiempo sigue siendo válido algo. Quedan prohibidas palabras como rápido, pronto, sencillo, intuitivo, amigable, robusto, según corresponda o si es necesario, salvo que vayan seguidas de un número o de una definición explícita.
- Indica el fallo esperado: qué se le dice a la persona, si la operación surte efecto en parte o no lo surte en absoluto, y qué queda sin cambiar.
- Cuando un mismo dato se introduce en una pantalla y lo valida también el sistema, declara **una sola vez** su conjunto de valores válidos (por ejemplo, los tipos de IVA admitidos) y haz que los demás puntos lo referencien. Así no pueden divergir la pantalla y la validación.
- Prefiere tablas para los estados y sus transiciones permitidas, para quién puede hacer qué, para las reglas de los campos y para ejemplos resueltos con valores exactos.

## 5. Normaliza lo que no debe ocurrir

Las prohibiciones son requisitos. Escríbelas explícitamente y distingue dos tipos:

- **Prohibiciones**: son puntos numerados con **no debe** y se verifican como cualquier otro punto: transiciones que nunca deben ocurrir, roles que no deben ver ni cambiar algo, registros que nunca deben borrarse, renumerarse ni reutilizarse, información que nunca debe llegar a otra cuenta, cliente o persona, y datos que nunca deben mostrarse ni guardarse.
- **No-objetivos**: no se numeran ni se verifican. Registran lo que queda deliberadamente fuera del alcance para que nadie lo añada en silencio. Para cada uno, indica si se pospone (y adónde) o se descarta (y por qué).

Todo comportamiento destructivo o irreversible dentro del alcance necesita una prohibición que lo acote o un requisito explícito de confirmación.

## 6. Cubre todos los casos, en proporción al cambio

Recorre esta lista y, para cada caso, especifícalo o anota en una línea por qué no aplica. La especificación debe ser proporcional al cambio: si ocupa más que lo que describe, sobra texto.

- El camino principal y cada camino alternativo.
- Nada creado todavía, nada encontrado y el primer uso de la funcionalidad, con el texto exacto que ven las personas.
- Límites: ninguno, uno, el máximo permitido, más del máximo, valores negativos o cero, textos muy largos y caracteres acentuados o especiales.
- Entradas erróneas o maliciosas, y entradas válidas pero no permitidas para esa persona.
- Permisos: cada rol, alguien sin identificar y la información de otra cuenta o cliente.
- Dos personas actuando a la vez, la misma acción repetida o enviada dos veces, y qué garantiza que el resultado no se duplique.
- Algo de lo que depende el producto no está disponible, y qué ven entonces las personas.
- Una operación que falla a medias: qué queda hecho y qué se deshace.
- Todo lo creado antes del cambio, y qué deben hacer las personas al respecto.
- Fechas, zonas horarias, unidades, monedas y redondeos, incluida la forma de mostrarlos.
- Borrar y conservar: qué desaparece, qué se conserva por trazabilidad y durante cuánto tiempo.
- Grandes volúmenes: cuántos elementos se muestran a la vez, en qué orden y qué se puede exportar.
- Trazabilidad: qué debe quedar registrado y qué no debe registrarse nunca.

## 7. Criterios de aceptación y verificación

- Escribe los criterios de aceptación como resultados que un revisor puede comprobar usando el producto, sin saber cómo se ha construido.
- Para cada punto, describe al menos un escenario que lo demuestre: la situación de partida, la acción y el resultado esperado con valores exactos.
- Incluye al menos un escenario que demuestre que cada prohibición se rechaza.
- Deja a la implementación la elección del nivel y de las herramientas de verificación: la especificación dice qué hay que demostrar, no cómo se comprueba.

## 8. Control de calidad antes de la aprobación

No entregues una especificación hasta que se cumpla todo esto:

- Cada punto está numerado y es único, verificable y cuantificado.
- Cada punto tiene criterios de aceptación y un escenario; cada prohibición tiene uno que demuestra que se rechaza.
- Se ha recorrido la lista de la sección 6 y los casos descartados están justificados por escrito.
- El alcance, los no-objetivos y las prohibiciones son coherentes entre sí y con los puntos.
- Ningún requisito contradice a otro, y ninguno contradice el comportamiento actual sin decirlo y sin indicar qué pasa con lo que ya existe.
- Nada del documento nombra código, archivos ni tecnología, y ningún requisito describe una implementación.
- Las preguntas abiertas indican responsable, valor por defecto y consecuencia, y ninguna bloquea un punto marcado como listo.
- El entorno de destino está declarado si condiciona el comportamiento, y cada dato validado en varios sitios tiene un único conjunto de valores válidos.
- La longitud es proporcional al cambio.
- Un implementador competente que no haya participado en la conversación podría construirlo, y un revisor independiente podría rechazar el resultado con pruebas.

## 9. Después de la aprobación

Cambia la especificación solo cuando cambie una decisión sustancial: mantén estables los identificadores, añade puntos nuevos en lugar de reescribir los antiguos y registra qué cambió y por qué. El comportamiento que no respalde ningún criterio de aceptación no se implementa; plantéalo como un punto nuevo o como una decisión posterior. Mantén el estado al día: una especificación desactualizada es peor que ninguna.

<!-- obsidian-links:start -->

## Enlaces

- [[methodology/sdd|sdd]]
- [[skills/tasks/SKILL|skill: tasks]]
- [[methodology/workflow|workflow]]
- [[AGENTS]]
- [[syntheses/lecciones-revisiones|lecciones de las revisiones]]

<!-- obsidian-links:end -->
