# Guía práctica para configurar metas e indicadores

Esta guía explica cómo crear metas con la nueva configuración de indicadores. Los casos son inventados, pero toman como referencia ejes, objetivos estratégicos y objetivos generales de `database/seeders/base-data-app.csv`.

En las pantallas de seguimiento, **Meta** nombra el valor previsto y **Resultado** nombra el dato observado e informado. No deben confundirse con el **objetivo general** al que pertenece la meta de planificación.

Sirve para dos lecturas: quien configura una meta puede seguir las preguntas y los ejemplos sin usar fórmulas; quien trabaja con datos puede verificar cómo se calcula cada resultado.

## 1. Ubicar la meta en la planificación

Una meta forma parte de una cadena:

```text
Eje -> Objetivo estratégico -> Objetivo general -> Meta -> Indicador (si corresponde)
```

La meta es el resultado concreto que se busca. El indicador es la medida que permite seguir ese resultado. No toda meta necesita un indicador numérico.

Por ejemplo, en el eje **Planeamiento, Monitoreo y Evaluación**, bajo el objetivo estratégico **Consolidar el Monitoreo como herramienta de seguimiento de la gestión por resultados** y el objetivo **Impulsar la incorporación de tableros que permitan la toma de decisiones basadas en evidencia**, se puede crear la meta:

> Implementar un tablero de seguimiento para los planes operativos priorizados.

Si interesa comunicar novedades e hitos de implementación, no hace falta medirla numéricamente. Si también interesa saber cuántas áreas ya usan el tablero, sí conviene agregar un indicador.

## 2. Elegir el modo de medición

La primera decisión define qué dato se cargará luego en los reportes de Avance.

| Modo | Cuándo elegirlo | Cómo se carga el avance |
| --- | --- | --- |
| **Sin indicador numérico** | La evidencia es narrativa o por hitos; no hay una magnitud útil. | Solo se cargan Novedades e Hitos. |
| **Valor acumulado** | Se parte de cero y cada avance agrega unidades a una meta final. | Cada avance suma unidades al progreso existente. |
| **Por períodos** | Cada mes, trimestre u otro período tiene su propia meta y resultado. | Cada avance informa el valor medido de un solo período. |

Regla simple: usar valor acumulado si importa saber **cuánto se agregó hasta un total**. Usar por períodos si importa comparar el resultado de **cada momento** con lo esperado para ese momento.

## 3. Ejemplo A: meta sin indicador numérico

| Nivel | Definición |
| --- | --- |
| Eje | Integridad, Transparencia y Gobierno Abierto |
| Objetivo estratégico | Consolidar espacios de participación y control ciudadano en la gestión del MISP, sus empresas, entes y organismos |
| Objetivo general | Impulsar espacios de intercambio y trabajo colaborativo |
| Meta propuesta | Realizar una mesa de trabajo interáreas para acordar el protocolo de participación ciudadana. |

**Configuración:** seleccionar **Sin indicador numérico**.

El valor relevante no es una cantidad ni un porcentaje: es que la mesa se realice, participe quien corresponde y quede un acuerdo. Se pueden cargar novedades como "se definió la agenda" e hitos como "protocolo acordado". El estado de la meta se actualiza explícitamente cuando corresponda.

No usar un indicador numérico solo para obtener un porcentaje: un `100%` sin una medida clara puede ocultar más de lo que explica.

## 4. Ejemplo B: valor acumulado

| Nivel | Definición |
| --- | --- |
| Eje | Género y Diversidad |
| Objetivo estratégico | Promover el desarrollo de obras y la prestación de servicios públicos con perspectiva de género y diversidad |
| Objetivo general | Promover la formación continua e integral en Género y Diversidad |
| Meta propuesta | Capacitar a 240 personas del MISP en contenidos de género y diversidad. |

**Configuración:** seleccionar **Valor acumulado**.

| Campo | Valor |
| --- | --- |
| Indicador | Personas capacitadas |
| Unidad | personas |
| Valor de meta (100%) | 240 |
| Valor actual al crearla | 0 |
| Frecuencia de monitoreo | mensual (opcional) |

Si en abril se capacitan 65 personas y en mayo 55, se cargan dos avances: `65` y `55`. La plataforma mostrará `120 de 240 personas`, es decir, `50%` de la meta.

$$
progreso = \frac{65 + 55}{240} = 0{,}50 = 50\%
$$

Este modo sirve para unidades que se acumulan: personas capacitadas, equipos entregados, talleres realizados o municipios adheridos. No sirve para promedios como "días promedio de respuesta", porque sumar promedios cambia su significado.

## 5. Qué se define en una meta por períodos

Al seleccionar **Por períodos**, se combinan propiedades independientes. Cada una responde una pregunta concreta.

| Propiedad | Pregunta para decidirla | Opciones |
| --- | --- | --- |
| Dirección | ¿Qué resultado representa mejor desempeño? | Mayor; menor; acercarse a la meta. |
| Naturaleza | ¿Qué sentido tiene combinar varios períodos? | Sumar valores; promediar valores. |
| Semántica | ¿Cada meta es lo agregado allí o el nivel esperado al cierre? | Incremental; nivel acumulado. Solo para períodos que se suman. |
| Unidad | ¿En qué se expresa el indicador? | Días, personas, %, pesos, expedientes, etc. |
| Ventana | ¿Cada cuánto se mide, desde cuándo y durante cuántos períodos? | Periodicidad, mes de inicio y cantidad de períodos. |

La pantalla ofrece los atajos **Plazo**, **Cantidad** y **Grado de avance**. Son ayudas para completar estas propiedades, no tipos rígidos.

| Atajo | Dirección | Combinación | Unidad sugerida |
| --- | --- | --- | --- |
| Plazo | Menor es mejor | Promediar | días |
| Cantidad | Mayor es mejor | Sumar | la indicada |
| Grado de avance | Mayor es mejor | Sumar | % |

La **fórmula de cálculo** es un texto descriptivo de cómo el área obtiene el valor desde sus fuentes, por ejemplo: `suma de días de resolución / cantidad de expedientes`. La plataforma no interpreta esa fórmula: recibe el valor ya medido de cada período.

## 6. Ejemplo C: una cantidad que se suma por trimestre

| Nivel | Definición |
| --- | --- |
| Eje | Desarrollo Sostenible |
| Objetivo estratégico | Promover el desarrollo de obras y servicios públicos con una perspectiva de sostenibilidad |
| Objetivo general | Promover la capacitación continua y el fortalecimiento de capacidades, habilidades y aptitudes del personal del MISP en temáticas de sostenibilidad |
| Meta propuesta | Capacitar a 500 personas en criterios de infraestructura sostenible durante 2027. |

**Configuración**

| Campo | Valor |
| --- | --- |
| Modo | Por períodos |
| Indicador y unidad | Personas capacitadas en infraestructura sostenible; personas |
| Dirección | Un valor mayor es mejor |
| Combinación | Los períodos se suman |
| Semántica | Lo que se agrega en cada período (incremental) |
| Periodicidad y ventana | Trimestral; enero de 2027; 4 períodos |

| Período | Meta | Resultado | Lectura |
| --- | ---: | ---: | --- |
| 1T | 100 personas | 110 personas | Se superó lo previsto. |
| 2T | 125 personas | 115 personas | Faltaron 10 personas respecto del trimestre. |
| 3T | 125 personas | pendiente | Todavía no se informa. |
| 4T | 150 personas | futuro | Todavía no corresponde informar. |

Al cierre del 2T hay dos respuestas correctas, pero responden cosas distintas:

1. **¿Cómo viene a la fecha?** `225 / 225 = 100%`. Va a tiempo porque el exceso del 1T compensa el faltante del 2T.
2. **¿Cuánto lleva de toda la meta?** `225 / 500 = 45%`. Es el avance sobre el total planificado.

$$
cumplimiento\ a\ la\ fecha = \frac{110 + 115}{100 + 125} = 100\%
$$

$$
avance\ de\ la\ ventana = \frac{110 + 115}{100 + 125 + 125 + 150} = 45\%
$$

Para cantidades, `100%` significa "va según lo planificado hasta hoy"; `45%` significa "completó el 45% del total previsto".

## 7. Ejemplo D: un plazo que se promedia por trimestre

| Nivel | Definición |
| --- | --- |
| Eje | Integridad, Transparencia y Gobierno Abierto |
| Objetivo estratégico | Garantizar y promover el derecho de acceso a la información pública del MISP, sus empresas, entes y organismos |
| Objetivo general | Optimizar la gestión de solicitudes de información |
| Meta propuesta | Reducir el tiempo promedio de respuesta a solicitudes de acceso a la información. |

**Configuración:** modo **Por períodos**; atajo **Plazo**; indicador `Días promedio de respuesta a solicitudes`; unidad `días`; dirección **Un valor menor es mejor**; períodos **se promedian**; cuatro trimestres desde enero de 2027.

La fórmula descriptiva puede ser: `suma de días entre ingreso y respuesta / cantidad de solicitudes respondidas en el período`.

Supongamos que la meta es `20 días` por trimestre:

| Período | Meta | Resultado | Cumplimiento | Lectura |
| --- | ---: | ---: | ---: | --- |
| 1T | 20 días | 16 días | 125% | Se respondió más rápido de lo previsto. |
| 2T | 20 días | 22 días | 91% | Se respondió más lento de lo previsto. |

Como menos días es mejor, la comparación invierte la división:

$$
cumplimiento = \frac{meta}{resultado}
$$

Para el 1T, $20 / 16 = 125\%$; para el 2T, $20 / 22 \approx 91\%$.

A la fecha se usan promedios simples: meta promedio `20 días` y resultado promedio `19 días`. El cumplimiento es aproximadamente `105%` (`20 / 19`). No se muestra un avance total de la ventana, porque no tiene sentido sumar días promedio de distintos trimestres.

El promedio entre períodos es una aproximación. Si un trimestre tuvo muchas más solicitudes que otro, el promedio exacto requeriría ponderar por la cantidad de solicitudes. La plataforma no solicita ese denominador: recibe el promedio ya calculado por el área.

## 8. Ejemplo E: un nivel acumulado, no un avance nuevo

| Nivel | Definición |
| --- | --- |
| Eje | Innovación y Transformación Digital |
| Objetivo estratégico | Potenciar la innovación y la transformación digital mediante la implementación de una estrategia integral de gobernanza de datos para optimizar la toma de decisiones en la Obra Pública |
| Objetivo general | Implementar herramientas digitales que fortalezcan la planificación estratégica basada en evidencia |
| Meta propuesta | Alcanzar el 80% de las áreas prioritarias con un tablero digital activo. |

El porcentaje de áreas con tablero activo no se debe sumar: si se llega al `25%` en marzo y al `50%` en junio, el resultado del segundo período ya contiene el avance anterior.

**Configuración:** indicador `Áreas prioritarias con tablero activo`; unidad `%`; dirección **Un valor mayor es mejor**; períodos **se suman**; meta de cada período **nivel a alcanzar al cierre**.

| Período | Meta de nivel al cierre | Resultado al cierre |
| --- | ---: | ---: |
| 1T | 25% | 25% |
| 2T | 50% | 40% |
| 3T | 65% | pendiente |
| 4T | 80% | futuro |

Al cierre del 2T, el cumplimiento a la fecha es `40 / 50 = 80%`. No se calcula `(25 + 40) / (25 + 50)`, porque contaría dos veces las mismas áreas. La meta completa lleva `40 / 80 = 50%`.

La diferencia con el ejemplo de capacitación es la pregunta que se hace al cargar la meta:

- **Incremental:** "¿cuánto se agrega en este período?"
- **Nivel acumulado:** "¿a qué nivel se debe llegar al cierre de este período?"

## 9. Ejemplo F: acercarse a un valor exacto

Esta dirección sirve cuando tanto quedarse corto como pasarse del valor buscado es desfavorable.

| Nivel | Definición |
| --- | --- |
| Eje | Género y Diversidad |
| Objetivo estratégico | Promover el desarrollo de obras y la prestación de servicios públicos con perspectiva de género y diversidad |
| Objetivo general | Promover la formación continua e integral en Género y Diversidad |
| Meta propuesta | Realizar cohortes de formación con un cupo que permita participación activa y acompañamiento personalizado. |

En una capacitación con actividades grupales y tutorías, el equipo define que el cupo pedagógico adecuado es de `30 personas` por cohorte. Con menos participantes, no se alcanza la cobertura ni la diversidad de intercambio previstas; con más, las personas facilitadoras no pueden acompañar las actividades ni garantizar que todas participen. El objetivo no es llenar la cohorte al máximo: es sostener el grupo en un tamaño que haga viable la propuesta.

**Configuración:** modo **Por períodos**; indicador `Participantes por cohorte de formación`; unidad `personas`; dirección **Lo mejor es acercarse a la meta**; períodos **se suman**; meta de cada período **lo que se agrega en ese período**. Cada período puede representar una cohorte mensual o trimestral.

Si la meta es `30 personas` y se informan `35`, el cumplimiento es aproximadamente `83%`. También es aproximadamente `83%` si se informan `25`: en ambos casos hay una distancia de 5 personas respecto del cupo pedagógico buscado.

$$
cumplimiento = 1 - \frac{|resultado - meta|}{meta}
$$

No es el modo habitual para una meta de crecimiento. Usarlo solamente cuando el valor de meta sea realmente un equilibrio operativo, de calidad o de capacidad, como el cupo de una cohorte.

## 10. Qué se carga en un reporte de Avance

El reporte conserva título, descripción, fecha, adjuntos, tags y la posibilidad de actualizar el estado de la meta. Lo que cambia es el dato numérico.

| Modo | Dato que se informa |
| --- | --- |
| Valor acumulado | Cuántas unidades se agregan al progreso existente. |
| Por períodos | El valor medido del indicador en el período elegido. No es un incremento salvo que la meta haya sido definida como incremental. |

Para una meta por períodos hay un único reporte de Avance por período. Si el valor fue cargado incorrectamente, se edita el reporte existente; no se crea un segundo reporte. Las Novedades y los Hitos siguen disponibles: pueden explicar el contexto, pero no reemplazan la medición.

## 11. Cómo leer cumplimiento, desvíos y colores

| Dato | Qué responde | ejemplo: meta 20 días, resultado 22 días |
| --- | --- | --- |
| Cumplimiento | Qué tan cerca se está de lo esperado, considerando la dirección. | `20 / 22 = 91%` |
| Desvío relativo | Cuánto se supera o falta para cumplir. | `91% - 100% = -9%` |
| Desvío absoluto | Diferencia en la unidad original. | `22 - 20 = +2 días` |

El color se toma del desvío relativo, no del valor crudo:

| Desvío relativo | Color |
| --- | --- |
| Mayor o igual a 0% | Verde |
| Entre 0% y -10% | Amarillo |
| Menor a -10% | Rojo |

Por eso, `90` frente a `100` puede ser rojo para una cantidad donde más es mejor, pero verde para un máximo donde menos es mejor. La dirección configurada define esa lectura y la pantalla muestra un ejemplo antes de guardar. El cumplimiento no se limita a `100%`: superar una meta puede mostrar `125%`, `174%` u otro valor mayor.

## 12. Períodos pendientes y excepciones

| Situación | En el cálculo | Acción |
| --- | --- | --- |
| Futuro o en curso | No entra todavía. | Informar cuando corresponda. |
| Sin meta | No entra. | Dejar la meta vacía si ese período no aplica. |
| Vencido sin avance | Cuenta como 0% de cumplimiento. | Cargar el dato o gestionar una omisión justificada. |
| Omitido con justificación | No entra, pero queda visible con su motivo. | Solo un administrador puede omitirlo o revertirlo. |
| Informado | Entra con meta y resultado. | Editar el reporte si hay una corrección. |

Un período vencido sin avance no se interpreta como resultado `0`: para un indicador donde menos es mejor, un cero sería excelente. Por eso recibe directamente `0%` de cumplimiento y no premia la falta de información.

## 13. Reglas de edición y responsabilidades

- Los administradores pueden configurar metas y omitir o rehabilitar un período con motivo.
- Quienes gestionan el objetivo pueden configurar la meta y corregir metas por período.
- Los reporteros cargan y corrigen reportes de Avance.
- Una vez cargado un avance por períodos, no se pueden cambiar el modo, la dirección ni la estructura de períodos. Sí se pueden corregir las metas de cada período.
- Los cálculos se actualizan al corregir una meta de período o un avance; no quedan congelados como un valor histórico fijo.
- El estado manual de la meta sigue siendo una decisión explícita. Conviene contrastarlo con el cumplimiento calculado para evitar mensajes contradictorios.

## 14. Lista antes de guardar

1. Verificar que la meta sea concreta y corresponda al objetivo elegido.
2. Elegir el modo que represente el dato, no el que produzca un porcentaje más fácil de mostrar.
3. Escribir indicador y unidad claros: "personas capacitadas", "días promedio de respuesta" o "áreas con tablero activo".
4. En metas por períodos, confirmar con el ejemplo de la pantalla que "mejor" está interpretado en la dirección correcta.
5. Distinguir entre valores incrementales y niveles acumulados al cierre.
6. Dejar sin meta los períodos que no correspondan, en lugar de cargar un cero ficticio.
7. Usar la fórmula descriptiva para documentar el origen del dato, sin esperar que la plataforma la ejecute.

Con estas decisiones, la plataforma puede seguir metas cualitativas, cantidades, avances porcentuales, plazos, promedios y metas de nivel sin convertir casos distintos en un único tipo de indicador.