## Plan: roles por meta, circuito de validación y metas internas

La única fuente de verdad es [decisiones-finales-roles-y-reportes-v2.md](./feedback/decisiones-finales-roles-y-reportes-v2.md). Los documentos anteriores de `docs/feedback/` son antecedentes y no tienen autoridad.

Hoy la plataforma tiene tres limitaciones:

- los permisos se asignan **por objetivo** (`objective_user`);
- la visibilidad solo existe **por objetivo** (`objectives.hidden`);
- el circuito de revisión de `da0da59` aprueba y publica en un único paso.

Hay staging y producción con datos, así que todo se hace con migraciones incrementales y reversibles, sin `migrate:fresh` y con backup según `DEPLOY.md`.

Cada punto lleva una etiqueta:

- **[Acordado]**: está cerrado en las secciones 1 a 5 del v2.
- **[Propuesta]**: viene de las secciones 8 y 9 o es un diseño técnico de este plan.
- **[Pendiente]**: está abierto en las secciones 6 y 7 del v2. No se implementa por suposición.

**Tus decisiones incorporadas**
- **Rama:** `feature/roles-y-reportes`, creada desde `dev/0.3.0`.
- **Sin migración de asignaciones:** `objective_user` no se convierte. Administración asigna Responsables y Reporteros desde cero. La tabla se conserva como referencia de solo lectura hasta la fase de cierre.
- **Metas existentes:** quedan **públicas**. El portal no cambia.
- **Metas nuevas:** en el formulario hay que elegir la visibilidad, que no tiene valor por defecto. El importador las crea **internas**.
- **Reportes cargados por Administración:** se mantiene la publicación directa en metas públicas, con una acción explícita.

### 1. Análisis de brechas

**Existe y sirve**
- **`ReportReview`:**
  - transiciones con bloqueo de fila y `review_version`;
  - hash de contexto (meta, hito y período);
  - `apply()` aplicado una sola vez al aprobar;
  - registro en `action_logs` (canal `mysql`);
  - notificaciones por plataforma y correo, con `notification_preferences`, cola y `NotificationRateLimited`;
  - archivos privados hasta la aprobación.

  Es la base del nuevo circuito.
- **Unicidad de avance por período** (`progress_period_key`): ya permite tener pendientes a la vez un avance y un hito en la misma meta y período **[Acordado §5]**. Falta un test que lo fije.
- **`AuthorizeReportManagement`:** separa 404 de 403 y envía `Cache-Control: private, no-store`. Es el patrón a mantener cuando se pase a policies.
- **`Organization`:** tiene un ABM en Administración y logos. Es candidato a catálogo de áreas (ver D6).
- **Panel `/panel`:** ya tiene notificaciones y "Mis objetivos". Es el lugar natural de la Mesa de trabajo.
- **`objectives.hidden`:** sirve como interruptor de publicación del objetivo.

**Hay que cambiar**
- **Autorización por objetivo:**
  - `User::is*Objective` (unos 37 usos) y `hasManagerPrivileges` (33 usos);
  - el middleware `can_manage_objective`;
  - las directivas `@isManager` y `@isMember`;
  - `Report::canManage`, `scopeManageableBy`, `canViewInManagement` y `canDelete`;
  - la etiqueta "Equipo" de `Comment` y `Reply`.

  Todo pasa a ser **por meta [Acordado §1, §3]**.
- **Facultades de estructura del manager:** hoy edita el objetivo, las metas, los hitos, el equipo, las comunidades, la portada y los archivos, y puede borrar. Todo eso pasa a ser **solo de Administración [Acordado §1]**.
- **`formObjectiveConfigurationHide` (ObjectivePanelController ~364):** no verifica el rol, así que cualquier miembro publica u oculta el objetivo. Pasa a ser solo de Administración.
- **`formAddTeam` y `UserController::fetch`:** aceptan y buscan a **cualquier** usuario. Tienen que limitarse al universo PFI y al alcance de quien opera **[Acordado §3]**.
- **`ReviewStatus` (`pending|approved|returned`):** no tiene validación del Responsable, y aprobar o devolver es solo de admin. Además, `create` avisa a todos los admins en cada envío. En la tabla del §5 el aviso va a los Responsables de la meta.
- **Visibilidad:** `Objective::isVisibleTo`, `Report::isVisibleTo` y `scopeFromVisibleObjectives` solo miran el objetivo.
- **Agregados:**
  - `HomeStats` usa una caché global `home.stats`.
  - `AdminDashboardStats` reutiliza los semáforos públicos, cuando Administración debe ver el total.
  - `ObjectiveStats` no filtra las metas y sirve tanto al panel como a la API pública.
- **El portal expone nombres de personas:** el equipo en `objective/view.blade.php:56-64` y el autor en `report/view.blade.php:33-34`. Tiene que mostrar áreas **[Acordado §5]**.
- **Avisos a suscriptores** (`NewReport`, `EditReport`, `CompletedGoal`, `NewGoal`, `EditGoal`, …): solo miran `objective->hidden`.
- **`AuthServiceProvider` está roto:** le faltan imports y el gate `isAdmin` llama a un `hasRoles` que no existe. `ObjectivePolicy` está vacía y hoy no se usan gates ni policies.

**Falta**
- **Asignaciones por meta:**
  - varios Responsables por meta;
  - Reporteros asignados por meta, o por objetivo como expansión a las metas existentes;
  - reasignación de la atención de un pendiente sin cambiar la autoría.
- **Universo PFI:** la pertenencia la gestiona Administración.
- **`goals.visibility`** y un **conjunto autorizado por contexto**: portal, gestión y Mesa de trabajo.
- **Áreas por meta:** una responsable y N vinculadas, con la unión hacia objetivo y eje.
- **Bloqueo de edición desde la validación**, con registro de los cambios de contenido. Hoy se registran las acciones, no qué cambió.
- **Avisos por meta.**
- **Mesa de trabajo y pendientes de Administración.**

**Superficies que hoy filtran metas internas** (inventario para la fase 3)
- **Portal:**
  - `ObjectiveController::index` y `viewCatalog`;
  - los árboles `portal/catalogs/partials/*tree.blade.php`;
  - las fichas de meta y reporte;
  - `SeoMetadata`.
- **API `api-service`:**
  - `fetch` de objetivos, con sus `withCount` y estados;
  - el Resource `Objective` (`objective_goals`, `latest_goals`, `goals_count`, `goals_status` y `latest_reports`);
  - `fetchReports`, `fetchStats` y `GoalController::index/fetchReports`;
  - `ReportController::fetch`, con su búsqueda por `trace`, sus filtros y `mappable`, que alimentan los mapas.
- **Archivos y sitemap:** `ReportAttachmentController` y `PublicSitemap`.
- **Estadísticas:** `HomeStats`, `ObjectiveStats` y `AdminDashboardStats`.
- **Exportaciones:** `ObjectiveGoalsExport`, `ObjectiveIndicatorsExport`, `GoalReportsExport` (incluye reportes no aprobados), `ObjectivesExport` y `CommentsExport`.
- **Gestión:** `CommentModeration`, los directorios `app/Services/Admin/*Directory.php` y el panel de gestión del objetivo (listas, comentarios, mapa, logs y descargas).
- **Participación y avisos:** comentarios, testimonios, "Me gusta" y avisos a suscriptores.

**Riesgos**
1. **Corte de acceso.** Como no hay migración, coordinadores y reporteros pierden el acceso en cuanto la autorización pasa a ser por meta. Para mitigarlo:
   - Las asignaciones se cargan en una fase previa (fase 2) que todavía no cambia permisos.
   - El corte (fase 4) es un despliegue separado y coordinado con Administración.
   - Administración cuenta con un listado de referencia del equipo anterior.
2. **Fugas por superficies no inventariadas.** Se mitigan con un único servicio de audiencia y un test de contrato de confidencialidad que crece en cada fase.
3. **Cachés compartidas.** Las claves dependen del contexto, y el conjunto de gestión nunca se guarda en una caché compartida entre usuarios.
4. **Pendientes en curso.** Los `pending` que hoy esperan a Administración pasan a esperar a un Responsable. Los `returned` de autores sin asignación se quedan sin quién los atienda. Se mitiga con la reasignación de atención y la intervención de Administración.
5. **Regresión del circuito actual.** `ReportReviewTest`, `ReportReviewConcurrencyTest`, `ReportReviewMigrationTest` y `PeriodicGoalReportsTest` se adaptan. No se elimina ningún test sin aprobación.
6. **Rendimiento.** `hasRole` consulta la base en cada llamada. Hay que precargar roles y asignaciones una vez por request.
7. **Cambios de visibilidad con historial.** Los define §6.2, que está [Pendiente]; ver la fase 3, paso 18.
8. **Manual desactualizado.** Los capítulos 02, 05 y 06 describen el modelo por objetivo.

**Migración de datos**
- **`objective_user`:** no se convierte. Queda intacto y de solo lectura hasta la fase 9.
- **Suscripciones** que generó `formAddTeam`: se conservan.
- **Universo PFI:** nadie queda marcado automáticamente.
- **`goals.visibility`:** todas las metas existentes se completan como `public`.
- **Reportes `approved`**, incluidos los de `LegacyReportApproval`: quedan como publicados, sin inventar una validación del Responsable (`validated_by` queda `null`).
- **Reportes `pending` y `returned`:** conservan su estado.
- **`objective_organization`:** su destino depende de D6.
- **`users.organization`:** se mantiene como texto libre, sin cambios.

### 2. Regla transversal: confidencialidad

Se diseña en la fase 0 y se implementa en la fase 3, antes del circuito. Desde ahí, **toda consulta de metas, reportes o agregados pasa por un único servicio de audiencia** **[Propuesta técnica]**, con tres contextos **[Acordado §5]**:

| Contexto | Conjunto |
| --- | --- |
| `public` (portal, API pública, sitemap, avisos a suscriptores) | Meta pública **y** objetivo no oculto. Ignora la sesión, también la de un admin. |
| `management(user)` | Administración: todas. Resto: públicas efectivas más las asignadas. |
| `workspace(user)` | Solo las asignadas, sean públicas o internas. |

Los conteos, porcentajes, indicadores y exportaciones usan el mismo conjunto que el listado. Cada fase suma casos al test de contrato:

- **Cifras:** 20, 23 y 40.
- **Sin pistas:**
  - una meta interna da el mismo 404 que un id inexistente;
  - la búsqueda por su título no devuelve nada;
  - no figura en el sitemap;
  - sus adjuntos dan 404;
  - no genera ningún aviso público.

### 3. Fases

**Fase 0: fundaciones de autorización, sin cambio de comportamiento**
1. Reparar `AuthServiceProvider`: agregar los imports y cambiar el gate `isAdmin` para que use `hasRole('admin')`. Registrar las policies.
2. Crear `ObjectivePolicy`, `GoalPolicy` y `ReportPolicy`. Al principio reproducen las reglas actuales por objetivo. Reemplazan:
   - `hasManagerPrivileges` y `Report::canManage/canDelete/canViewInManagement`, en controladores y middleware, por `authorize`;
   - las directivas Blade, por `@can`.
3. Precargar roles y asignaciones una vez por request.
4. Corregir `formObjectiveConfigurationHide` para que solo Administración oculte o publique un objetivo.
5. Definir la interfaz del servicio de audiencia (sección 2) y sus tests con datos sintéticos.

**Fase 1: universo PFI** (*depende de la fase 0*)
6. Guardar la pertenencia a un equipo PFI. **Bloqueado por D1.** Si alcanza con una marca, se propone una tabla `pfi_memberships(user_id único, granted_by, granted_at)`, que permite sumar categorías después sin migrar datos.
7. En Administración → Usuarios:
   - dar de alta la pertenencia;
   - filtrar por pertenencia en `UserDirectory`;
   - registrar las operaciones en `action_logs`.

   Ser PFI no da acceso a nada **[Acordado §1]**.
8. Retirar la pertenencia. **Bloqueado por D2.** Mientras no se defina, la interfaz no permite retirarla a quien tenga asignaciones vigentes. Es una regla provisoria y hay que confirmarla.
9. Crear una búsqueda de candidatos por nombre, apellido o correo, limitada al universo PFI. `UserController::fetch` deja de usarse para asignar.

**Fase 2: asignaciones por meta, todavía sin efecto en permisos** (*depende de la fase 1*)
10. Crear la tabla `goal_user(goal_id, user_id, role responsible|reporter, assigned_by, timestamps)`:
    - índice único por meta, usuario y rol;
    - al borrar la meta, se borran sus asignaciones en cascada.

    Agregar las relaciones `Goal::responsibles/reporters` y `User::assignedGoals`.
11. Crear el servicio `GoalAssignments` **[Acordado §1, §3]**:
    - Administración asigna y quita Responsables y Reporteros.
    - El Responsable asigna y quita Reporteros solo en sus metas.
    - La asignación de Reporteros **por objetivo** crea filas para las metas existentes que están dentro del alcance de quien opera. No alcanza a las metas futuras.
    - Solo se pueden elegir candidatos del universo PFI.
    - Cada operación queda registrada.
12. Agregar en Administración:
    - el equipo de cada meta;
    - el alta de Reporteros por objetivo;
    - un **listado de referencia del equipo anterior** (`objective_user`), de solo lectura.
13. Esta fase se despliega sola. Administración carga las asignaciones antes de la fase 4.

**Fase 3: confidencialidad** (*depende de la fase 2*)
14. Agregar la columna `goals.visibility` (`public|internal`, no nula) y completarla con `public`.
    - El alta de metas exige elegir la visibilidad.
    - `StructureImportPlanner` crea las metas internas.
    - Solo Administración cambia la visibilidad **[Acordado §1]**.
15. Implementar el servicio de audiencia y los scopes `Goal::visibleIn()` y `Report::visibleIn()`. Los objetivos visibles en gestión son los no ocultos más los que tienen metas asignadas.
16. Aplicar el servicio a **todo** el inventario de la sección 1:
    - `HomeStats` usa el contexto público, con una caché solo pública.
    - `ObjectiveStats` recibe la audiencia.
    - `AdminDashboardStats` calcula sobre el total.
    - Las exportaciones usan el conjunto de quien descarga.
    - Los avisos a suscriptores nunca salen por metas internas.

    En la gestión de un objetivo, las metas internas no asignadas desaparecen incluso para los miembros por objetivo que todavía existen.
17. Quitar los nombres de personas del portal: el bloque del equipo, el autor del reporte y `author_id` en los Resources públicos **[Acordado §5]**. El área llega en la fase 6.
18. Cambios de visibilidad:
    - **De pública a interna:** se permite. El acceso futuro respeta la nueva visibilidad **[Acordado §6.2]**. Hay que avisar que no se recuperan las copias ya descargadas.
    - **De interna a pública con reportes:** **bloqueado por D4**. Mientras tanto, solo se permite en metas sin reportes.

**Fase 4: corte a autorización por meta** (*depende de las fases 2 y 3; despliegue coordinado*)
19. Las policies pasan a leer `goal_user`:
    - Responsable y Reportero actúan solo en sus metas.
    - Administración actúa sobre todo.
    - Pertenecer al mismo objetivo o eje no da permisos **[Acordado §1]**.
20. La estructura queda solo para Administración: el objetivo, las metas, los hitos, el equipo, las comunidades, la portada, los archivos, el borrado y la acción de ocultar.
21. El panel de gestión de un objetivo:
    - lo pueden abrir Administración y quienes tengan alguna meta asignada en ese objetivo;
    - muestra el conjunto `management`;
    - solo habilita acciones en las metas asignadas.
22. Retirar `can_manage_objective`, `formAddTeam`, el uso de `is*Objective` y la lectura de `objective_user`. La etiqueta "Equipo" de los comentarios pasa a significar "asignado a la meta del reporte".
23. Quitar una asignación revoca el acceso de inmediato. Un enlace o aviso anterior no conserva el acceso **[Acordado §9]**.

**Fase 5: circuito de validación** (*depende de la fase 4*)
24. Cambios en el modelo de datos:
    - `ReviewStatus` suma `validated`.
    - `approved` se mantiene como valor técnico de "Publicado", así no hay que reescribir datos.
    - Columnas nuevas: `validated_by`, `validated_at` y `attendant_id` (quién atiende el pendiente).
25. Transiciones **[Acordado §1, §4]**:
    - **Envío:** el reporte queda `pending`.
    - **Revisión:** cualquier Responsable de la meta, o Administración, puede devolver con motivo (`returned`) o validar (`validated`). La autovalidación es una acción explícita y separada de la carga.
    - **Desde `validated`:** Administración edita o publica. Publicar solo es posible si la meta es pública, y ejecuta `apply()`. Validar no publica ni aplica cambios.
    - **Carga de Administración:** publica directamente en metas públicas, con una acción explícita (tu decisión). Además puede intervenir en cualquier etapa.
    - **Meta interna:** el reporte se queda en `validated`. La acción que lo cierra y lo consolida está **bloqueada por D3**.
    - **No hay devolución desde Administración** que reabra la edición [Pendiente §7].
26. Permisos de edición, que incluyen archivos, fotos y mapa:
    - **Antes de validar:** el autor, quien atiende el pendiente, los Responsables de la meta y Administración.
    - **Desde `validated`, incluida una meta interna:** **solo Administración**, también si el autor es Responsable.
    - **Publicado:** solo Administración. Su propia edición cuenta como su validación.
27. Registrar quién cambió qué campos cada vez que edita alguien que no es el autor. **[Propuesta]**: el formato del detalle está por acordar (§9).
28. Reasignar la atención: lo hace un Responsable de la meta o Administración, solo sobre reportes no validados y hacia personas asignadas a la meta. `author_id` no cambia.
29. Se mantiene la unicidad de avance por período, con un test de coexistencia de avance e hito. Cualquier otro límite está **bloqueado por D5**.
30. Si dos Responsables resuelven a la vez, `mutate()` usa bloqueo y versión, y el segundo ve "ya resuelto".

**Fase 6: áreas por meta** (*depende de la fase 3; puede ir en paralelo con la fase 5*)
31. Definir el catálogo de áreas (D6). La propuesta es reutilizar `organizations` y llamarlas "Áreas" en la interfaz.
32. Crear la tabla `goal_organization(goal_id, organization_id, kind responsible|linked)`:
    - índice único por meta y área;
    - como máximo un área `responsible` por meta.

    Las metas existentes quedan sin área hasta que Administración la cargue. Mientras tanto, el portal no muestra nada.
33. Las áreas de objetivos y ejes son la unión, sin duplicados, de las áreas de sus metas dentro del conjunto de la audiencia **[Acordado §5]**.
34. En el portal, mostrar el área responsable como dato principal y las vinculadas con menor jerarquía, en la meta y en sus reportes publicados.
    - Al publicar se guarda una copia del área.
    - Qué área se muestra en los reportes históricos está **bloqueado por D7**.
35. El filtro por áreas y las sugerencias del Responsable quedan fuera hasta que se definan [Pendiente §7].

**Fase 7: avisos por meta** (*depende de la fase 5*)
36. Implementar los eventos de la tabla del §5, que son una base a ajustar:
    - **Asignación o retiro:** a la persona afectada.
    - **Envío o reenvío:** a los Responsables de la meta. Si la meta no tiene Responsables, a Administración.
    - **Devolución:** al autor o a quien atiende el pendiente.
    - **Validación:** aviso al autor y tarea para Administración.
    - **Publicación:** al autor y a los Responsables, con el enlace público.
    - **Revisión resuelta por otro Responsable:** se actualiza el pendiente, sin enviar correo.
37. Los avisos de metas internas no llevan enlaces públicos. Los enlaces de gestión vuelven a verificar el permiso al abrirse.
38. Mientras §7 esté sin definir, las tareas de Administración van a todos los admins, como hoy. No hay recordatorios ni resúmenes.

**Fase 8: Mesa de trabajo y pendientes de Administración** (*depende de las fases 5 y 7*)
39. Crear `/panel/mesa` para los integrantes PFI **[Acordado §8]**. Muestra:
    - las metas asignadas (`workspace`), una sola vez aunque la persona tenga los dos roles;
    - las tareas pendientes.

    Las tareas se calculan a partir del estado de los reportes, no de las notificaciones **[Propuesta §8]**.
40. Sumar una sección de pendientes de Administración: reportes validados por resolver, que distingue las metas internas de las públicas.
41. Antes de diseñar las pantallas hay que acordar las secciones de §8: Requiere mi acción, Esperando a otra persona, filtros, alertas de continuidad y vista pública identificable. Ver D8.

**Fase 9: cierre**
42. Actualizar el manual de usuario (capítulos 02, 05, 06 y 07, e `historial.md`).
43. Eliminar `objective_user` y el código que queda sin uso, con aprobación previa y con una migración que primero lo archive.

### 4. Verificación
- Todas las pruebas son feature tests de PHPUnit con `RefreshDatabase` sobre MySQL (`participes_testing`).
- Cada fase corre `php artisan test --compact` con sus tests, más la regresión de revisión y de superficies públicas: `ReportReview*`, `PeriodicGoalReportsTest`, `SitemapTest`, `HomeStatsTest`, `*SearchTest`, `CsvExportsTest`, `ObjectivePageTest` y `ReportPageTest`.
- Los tests públicos ponen `hidden = false` (`.ai/rules/tests.md`).
- El test de contrato de confidencialidad usa 40 metas (20 públicas y 20 internas), con una persona asignada a 3 internas, y verifica 20, 23 y 40 en el inicio, la ficha de objetivo, la API, las exportaciones y el dashboard de Administración.
- Las migraciones de datos se prueban como `ReportReviewMigrationTest`: reportes `approved` existentes siguen públicos y las metas existentes quedan públicas.
- Cada fase se despliega por separado, con backup y con escrituras y workers detenidos (`DEPLOY.md`).

### 5. Decisiones abiertas

**Bloqueantes**

| # | Decisión | Fuente | Bloquea |
| --- | --- | --- | --- |
| D1 | ¿Alcanza una marca PFI o hacen falta categorías? | §6.1 | Fase 1, paso 6 |
| D2 | Al retirar la pertenencia PFI, ¿qué pasa con las asignaciones y los pendientes? | §6.1 | Fase 1, paso 8 |
| D3 | ¿Qué acción de Administración cierra un reporte interno y desde cuándo se considera consolidado? | §6.2 | Fase 5, paso 25 (meta interna), e indicadores internos |
| D4 | Al pasar de interna a pública o al revés, ¿qué pasa con los reportes históricos y cómo se comunica el cambio? | §6.2 | Fase 3, paso 18 |
| D5 | Cupo de pendientes: tipos, períodos, cuándo se libera y cómo cuentan los devueltos, las propuestas y los internos | §6.3 | Fase 5, paso 29 |
| D6 | ¿Se reutiliza `organizations` como catálogo de áreas o se crea una tabla nueva? ¿Qué pasa con `objective_organization`? | Técnica | Fase 6 |
| D7 | ¿Los reportes muestran el área que tenían al publicarse o la actual? ¿Qué pasa con un área dada de baja? | §7 | Fase 6, paso 34 |
| D8 | Secciones y filtros de la Mesa de trabajo | §8, propuestas | Diseño de la fase 8 |

**No bloqueantes**
- **Devolución desde Administración** (§7): no se implementa. Administración edita directamente.
- **Propuestas de corrección de lo publicado** (§7).
- **Sugerencias de áreas y filtro por áreas** (§7).
- **Avisos operativos** (§7): qué admins reciben tareas, recordatorios, resúmenes y deduplicación.
- **Presentación de fechas** de reporte, validación y publicación (§7).
- **Objetivo público sin metas públicas:** ¿se muestra igual o se oculta?
- **Vista previa de objetivos ocultos** en el portal para admins y miembros: hoy existe.
- **Criterios propuestos del §9:** WCAG 2.2 AA y p95 de 2 s en la Mesa.
- **Descargas CSV para Responsables**, limitadas a su conjunto.

**Supuestos que tomé por defecto (decime si alguno no va)**
- Una meta es públicamente visible si es pública **y** su objetivo no está oculto. `objectives.hidden` se mantiene y solo lo cambia Administración.
- En gestión, quien no es admin ve las metas públicas efectivas más las suyas, y el contexto del objetivo o eje de sus metas aunque esté oculto.
- El portal usa siempre el contexto público. Un admin no ve metas internas en el portal.
- Administración puede intervenir en cualquier etapa: devolver, validar o publicar un pendiente.
- Los `pending` existentes pasan a esperar a un Responsable. Si la meta no tiene Responsables, los atiende Administración.
- Las comunidades, la portada y los archivos del objetivo pasan a ser solo de Administración.
- No se agregan dependencias. Los permisos se resuelven con policies y gates.
