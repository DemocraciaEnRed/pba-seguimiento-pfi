## Plan: roles por meta, circuito de validación y metas internas

La única fuente de verdad es [decisiones-finales-roles-y-reportes-v2.md](./feedback/decisiones-finales-roles-y-reportes-v2.md). Los documentos anteriores de `docs/feedback/` son antecedentes y no tienen autoridad.

Al comenzar el plan, la plataforma tiene tres limitaciones:

- los permisos se asignan **por objetivo** (`objective_user`);
- la visibilidad solo existe **por objetivo** (`objectives.hidden`);
- el circuito de revisión de `da0da59` aprueba y publica en un único paso.

Hay staging y producción con datos, así que todo se hace con migraciones incrementales y reversibles, sin `migrate:fresh` y con backup según `DEPLOY.md`.

Cada punto lleva una etiqueta:

- **[Acordado]**: está cerrado en las secciones 1 a 5, en los requisitos de la sección 8 o es consecuencia de esas decisiones según la sección 9 del v2.
- **[Propuesta]**: está identificada como propuesta en las secciones 8 y 9 o es un diseño técnico de este plan.
- **[Pendiente]**: está abierto en las secciones 6 y 7 del v2. No se implementa por suposición.

**Tus decisiones incorporadas**
- **Rama:** `feature/roles-y-reportes`, creada desde `dev/0.3.0`.
- **Sin migración de asignaciones:** `objective_user` no se convierte. Administración asigna Responsables y Reporteros desde cero. La tabla se conserva como referencia de solo lectura hasta la fase de cierre.
- **Metas existentes:** quedan **públicas**. El portal no cambia.
- **Metas nuevas:** en el formulario hay que elegir la visibilidad, que no tiene valor por defecto. En la importación se elige una visibilidad obligatoria para las metas nuevas (ver D4).
- **Reportes cargados por Administración:** se mantiene la publicación directa en metas públicas, con una acción explícita.
- **Pertenencia PFI (D1 resuelta):** catálogo administrable por Administración, con una o varias categorías por persona. Las categorías se desactivan en lugar de eliminarse: se conservan las asociaciones y no se ofrecen para nuevas altas. Los ejemplos recibidos (Integrante MISTO, Ente, Empresa, Organismos MISP) orientan el catálogo; no se convierten en un enum cerrado ni en permisos.
- **Baja y continuidad (D2 resuelta):** al quitar un Responsable, advertir si conviene asignar otro para mantener la cobertura; no exigir reemplazo si ya hay otros Responsables y no introducir unanimidad para validar. Al retirar un Reportero con pendientes no validados, exigir que Administración o quien tenga facultad sobre esa meta elija quién los continúa. Puede seleccionar otro Reportero asignado o incorporar uno nuevo del universo PFI. Se conserva la autoría histórica.
- **Alcance de esa continuidad:** rige tanto al retirar la pertenencia PFI como al quitar la asignación de Reportero a una meta. La operación no se confirma hasta resolver los destinos necesarios; no hay transferencias automáticas. Los reportes ya validados permanecen bajo Administración.
- **Continuidad al retirar Responsables, confirmada en fase 4:** si la persona pierde su última facultad para atender pendientes a su cargo, también debe elegir los destinos antes de confirmar. No se exige nombrar otro Responsable. Si conserva otro rol que permite atenderlos, no hace falta transferirlos.
- **D3, cierre interno:** después de validar, Administración realiza una acción explícita de cierre. Desde ese momento se aplican los cambios y el reporte se consolida para el seguimiento interno; no se publica. El reporte cerrado no se puede editar ni eliminar.
- **D5, cupos:** no se agrega un cupo global. Se conserva un Avance por período; el período permanece reservado mientras exista el reporte, incluso devuelto o validado. Un Hito puede coexistir con el Avance.
- **Devolución desde Administración:** no se habilita. Administración corrige directamente; la devolución corresponde a Responsables sobre propuestas pendientes.
- **D4, visibilidad fija:** la visibilidad se define al crear la meta y no cambia nunca. Una meta interna no puede pasar a pública ni una pública a interna. Así no hay reportes históricos que tratar ni cambios que comunicar. Una posible «representación pública» de metas internas queda fuera del alcance actual.
- **Importación:** Administración elige obligatoriamente Pública o Interna al importar; esa visibilidad se aplica a todas las metas nuevas del archivo. Las metas existentes actualizadas por el importador conservan la suya.
- **D6, catálogo:** se reutiliza `organizations` con el nombre **Organizaciones**, sin tipo. Cada meta tiene una **Organización responsable** y puede tener **Organizaciones participantes**. Los vínculos objetivo-organización (`objective_organization`) se retiran: la ficha del objetivo muestra la unión de las organizaciones de sus metas visibles. La tabla se archiva y elimina en la fase 9.
- **D7, historial:** cada reporte publicado conserva una copia de las organizaciones que tenía la meta al publicarse. Los reportes publicados antes de existir esa copia no muestran organizaciones. Una organización dada de baja se desactiva: no se ofrece para nuevas asociaciones y conserva su nombre en el historial.
- **D8, Mesa de trabajo:** se adoptan como primera versión las secciones Mis metas, Requiere mi acción y Esperando a otra persona; filtros por rol, objetivo/eje y tipo de tarea, con orden por antigüedad. Administración suma validados por resolver (separando Publicar y Cerrar), alertas de continuidad y acceso a la vista pública.
- **Manual sincronizado por fase:** actualizar los capítulos afectados de `docs/manual-usuario`, su índice, historial e instrucciones de capturas al cerrar cada fase, antes de iniciar la siguiente. La fase 9 conserva una revisión integral final, no la primera actualización. Documentar lo implementado y distinguirlo de lo aún no desplegado.

**Estado de ejecución**
- Plan aprobado; implementación iniciada en `feature/roles-y-reportes`.
- **Fase 0, pasos 1 a 4 implementados:** policies registradas y utilizadas por controladores, middleware, servicio de revisión y vistas; gate administrativo reparado; precarga por petición; visibilidad del objetivo reservada a Administración.
- En aquel primer incremento, los permisos generales seguían basados en `objective_user`. Las fases 2 y 3 prepararon asignaciones y visibilidad, y la fase 4 realizó el corte a `goal_user`. El circuito inicial conservaba `pending|returned|approved`; la fase 5 agrega `validated` y mantiene `approved` como valor técnico de los reportes publicados.
- El contrato de audiencia del paso 5 quedó diseñado abajo. Su implementación y las pruebas ejecutables 20/23/40 se incorporaron con el esquema de las fases 2 y 3.
- **D1 y D2 resueltas después del primer incremento.** La fase 1 puede incorporar catálogo, altas y clasificación PFI. La baja de personas se habilita con la reasignación efectiva de la fase 4, no antes. D3 y D5 quedaron resueltas para la fase 5. D4 y D6 a D8 se resolvieron después del commit de la fase 5.
- **Fase 1, pasos 6 a 9 implementados:** catálogo de categorías PFI (`pfi_categories`), pertenencia (`pfi_memberships`) y clasificación (`pfi_category_user`). Administración da de alta y reclasifica desde la ficha del usuario, filtra por pertenencia y categoría y exporta ambas columnas en el CSV. Cada operación queda en `action_logs`. Ser PFI no da acceso a nada.
- **Decisiones de implementación de la fase 1, confirmadas por el usuario:**
  - El catálogo arranca con las cuatro categorías de ejemplo, cargadas por una migración de datos idempotente. Administración puede editarlas o desactivarlas.
  - La búsqueda de candidatos PFI (`apiService.pfi.candidates`) queda reservada a Administración, según la corrección posterior del usuario. Coordinar un objetivo no habilita este acceso en la fase 1, incluso si la persona es integrante PFI. La fase 2 la extiende a los Responsables cuando existan asignaciones por meta.
  - «Agregar miembro» del equipo del objetivo se conservó hasta la fase 4. El corte retiró sus formularios, rutas y mutadores; las asignaciones nuevas usan exclusivamente candidatos PFI.
  - La baja PFI no estaba disponible en la fase 1; quedó habilitada con continuidad efectiva en la fase 4.
- **Fase 2, pasos 10 a 13 implementados:** `goal_user` almacena asignaciones `responsible` y `reporter`, con `assigned_by`, unicidad por meta/persona/rol y borrado en cascada al borrar una meta. Administración administra ambos roles por meta y puede sumar Reporteros a las metas existentes de un objetivo. Un Responsable solo puede sumar o quitar Reporteros de sus metas asignadas. La búsqueda de candidatos habilita ese mismo alcance; para Administración sigue siendo global. La ficha administrativa conserva `objective_user` como referencia de solo lectura.
- Las asignaciones de fase 2 no se convirtieron desde `objective_user` ni alteraron por sí solas los permisos generales. Las metas futuras no heredan la asignación por objetivo; requieren asignación expresa. La coexistencia con la gestión del equipo anterior terminó en la fase 4.
- **Fase 3, pasos 14 a 17 implementados:** las metas existentes se migran como públicas; las nuevas requieren una elección explícita y las importadas son internas. `GoalAudience` delimita consultas públicas, de gestión y de trabajo para metas y reportes, incluidos accesos por ID, agregados, exportaciones y avisos. Los recursos públicos no exponen nombres de personas. El paso 18 quedó bloqueado por D4 hasta su resolución posterior a la fase 5. La migración se añadió, pero no se aplicó fuera de las bases de prueba.
- **Fase 4, pasos 19 a 23 implementados:** policies, paneles, directorios, acciones y moderación usan asignaciones por meta. Administración conserva la estructura; una meta pública del mismo objetivo no habilita acciones fuera de las asignaciones. `objective_user` queda únicamente como referencia histórica administrativa, sin permisos ni mutadores. La etiqueta «Equipo» depende de la asignación del autor del comentario o respuesta, no de quien lo lee.
- **Continuidad de fase 4:** `attendant_id` separa la atención de la autoría. La baja PFI, los retiros por meta y la reasignación individual validan destinos vigentes, permisos y estado dentro de la transacción. Las bajas exigen resolver todos los pendientes afectados, incluidos los que aparecieron desde la confirmación. Se bloquean primero las personas participantes y destinos por ID, luego las metas y los reportes; la carga y modificación de reportes respetan ese orden. Las devoluciones se notifican a quien atiende actualmente.
- **Despliegue de fase 4 pendiente de coordinación:** preparar manualmente las asignaciones antes del corte; no se convierten los equipos anteriores. Aplicar las nuevas migraciones incrementales con backup: una agrega `attendant_id` y otra inicializa la atención de `pending|returned` con su autor sin sobreescribir reasignaciones. Revisar antes de habilitar el acceso los pendientes cuyos autores no tengan una asignación vigente ni facultades administrativas, y resolver su continuidad. Estas migraciones no se aplicaron a la base de la aplicación.
- **Fase 5, pasos 24 a 30 implementados en el código y con pruebas focalizadas aprobadas:** se documentan abajo; sus migraciones aún no se aplicaron a la base de la aplicación.
- **Manual actualizado al cerrar la fase 5:** v0.4.5, con los capítulos afectados, índice, historial e instrucciones de capturas. Las áreas por meta y la Mesa de trabajo todavía no se describen como disponibles.
- **Fase 3, paso 18 implementado tras D4:** la edición de metas rechaza cualquier cambio de visibilidad y la importación exige elegir Pública o Interna para las metas nuevas, sin alterar las existentes. `StructureImportTest` y `GoalConfidentialityTest` pasaron: **18 pruebas y 159 aserciones**. Manual v0.4.6.
- **Fase 6, pasos 31 a 35 implementados:** Administración asigna en el alta y la edición de cada meta una organización responsable y varias participantes; desactiva organizaciones en lugar de eliminarlas. La ficha pública de la meta, sus reportes publicados y la página del objetivo muestran las organizaciones; el objetivo las reúne desde sus metas dentro de la audiencia. Al publicar, cada reporte guarda una copia. La migración no se aplicó a la base de la aplicación. Manual v0.4.7.
- **Cambio de diseño en la fase 6:** la responsable se guarda en `goals.responsible_organization_id` y `goal_organization` registra solo las participantes. MySQL no admite claves foráneas con `CASCADE` sobre las columnas base de una columna generada, de modo que una tabla con `kind` no podía garantizar una única responsable por meta. Así, el esquema garantiza una sola responsable y la aplicación impide que también figure como participante.
- El análisis siguiente conserva la línea de base anterior al primer incremento; no describe como pendientes los cambios ya registrados arriba.

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

**Contrato diseñado para la fase 3 [Propuesta técnica]**
- `GoalAudience::publicQuery(): Builder<Goal>`: sin usuario implícito ni dependencia de la sesión.
- `GoalAudience::managementQuery(User $user): Builder<Goal>` y `workspaceQuery(User $user): Builder<Goal>`: requieren una persona explícita; no admiten una caída silenciosa al conjunto completo.
- Devuelven consultas, no colecciones ya paginadas: filtros, conteos, agregaciones y exportaciones se aplican después de delimitar la audiencia.
- Los reportes se restringen por las metas de esa consulta y, por separado, por su estado autorizado. Leer una meta no concede acceso a todos sus reportes ni permiso de edición.
- No se introduce un scope global ni una caché de gestión compartida. Los accesos singulares y las descargas usan el mismo conjunto antes de buscar el identificador.
- Matriz sintética para implementar con el esquema de fase 3: 40 metas en objetivos visibles, 20 públicas y 20 internas; una persona asignada a 3 internas y 2 públicas debe obtener 20 en público, 23 en gestión y 5 en su Mesa. Administración obtiene 40 en gestión y 20 en público; una cuenta ciudadana consulta las 20 públicas, sin acceso a la Mesa PFI. Retirar una de las asignaciones internas reduce 23 a 22 y revoca sus enlaces, sin borrar historia.
- La misma matriz debe verificar identificadores y valores agregados, no solo cantidades; también ausencia en búsquedas, sitemap, mapas, adjuntos, avisos y CSV. Su ejecución es condición para habilitar metas internas, no una garantía ya implementada.

### 3. Fases

**Fase 0: fundaciones de autorización, conservando el comportamiento salvo la visibilidad del objetivo**
1. Reparar `AuthServiceProvider`: agregar los imports y cambiar el gate `isAdmin` para que use `hasRole('admin')`. Registrar las policies.
2. Crear `ObjectivePolicy`, `GoalPolicy` y `ReportPolicy`. Al principio reproducen las reglas actuales por objetivo. Reemplazan:
   - `hasManagerPrivileges` y `Report::canManage/canDelete/canViewInManagement`, en controladores y middleware, por `authorize`;
   - las directivas Blade, por `@can`.
3. Precargar roles y asignaciones una vez por request, sin agregarlos a la representación pública del usuario ni conservarlos entre peticiones. Las mutaciones de reportes vuelven a consultar permisos dentro de la transacción.
4. Corregir `formObjectiveConfigurationHide` para que solo Administración oculte o publique un objetivo.
5. Definir el contrato del servicio de audiencia y su matriz sintética (sección 2). Las pruebas ejecutables de confidencialidad se incorporan en la fase 3, cuando existan visibilidad y asignaciones por meta.

**Fase 1: universo PFI** (*depende de la fase 0*)
6. Incorporar el catálogo y la pertenencia PFI según D1:
   - ABM de categorías, con nombre y estado activo/inactivo; sin valores cerrados por código.
   - Pertenencia de la persona al universo PFI, separada de sus roles y asignaciones.
   - Relación de una o varias categorías por integrante. Desactivar una categoría no retira pertenencias ni permisos; conserva sus asociaciones y la excluye de nuevas selecciones.
   - No marcar automáticamente como PFI a usuarios existentes ni derivar permisos del organismo o la categoría.
7. En Administración → Usuarios:
   - dar de alta la pertenencia;
   - filtrar por pertenencia en `UserDirectory`;
   - registrar las operaciones en `action_logs`.

   Ser PFI no da acceso a nada **[Acordado §1]**.
8. Preparar la gestión de la baja según D2, pero no habilitarla en este despliegue: depende de las asignaciones por meta y de que los destinatarios puedan continuar los reportes. La operación completa se incorpora en la fase 4. La baja lógica de categorías del catálogo sí forma parte de esta fase.
9. Crear una búsqueda de candidatos por nombre, apellido o correo, limitada al universo PFI. `UserController::fetch` deja de usarse para asignar a partir de la fase 4, cuando se reemplaza «Agregar miembro»; hasta entonces no se modifica.

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
18. Visibilidad fija **[D4 resuelta]**:
    - La visibilidad se elige al crear la meta y no se puede modificar después, ni desde la edición ni desde la importación.
    - La importación exige elegir Pública o Interna para las metas nuevas del archivo; las existentes conservan la suya.

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
    - Incorporar `attendant_id` y la reasignación de atención de reportes no validados, sin modificar `author_id`. El destino debe ser un Reportero vigente de esa meta; Administración o un Responsable autorizado puede agregar previamente uno nuevo del universo PFI.
    - Habilitar la baja PFI y el retiro de asignación de Reportero con selección obligatoria de destino para cada pendiente no validado que deje de poder atender. No confundir autoría histórica con la persona que actualmente atiende un pendiente ya reasignado.
    - Validar permisos, pertenencias, estados y destinos nuevamente al confirmar; reasignaciones y retiro se realizan de forma atómica. No retirar primero y dejar pendientes sin atención si falla un destino.
    - Al retirar un Responsable, informar la cobertura restante y advertir si no queda ninguno. No bloquear la baja por una obligación de reemplazo que no fue acordada.
    - Si ese Responsable pierde su última facultad sobre pendientes que atiende, exigir continuidad antes de confirmar, sin exigir otro Responsable **[Confirmado durante la fase 4]**. Hasta la fase 5, «pendientes no validados» se implementa sobre `pending|returned`; `approved` queda fuera.
    - Las pruebas de esta fase cubren retiro PFI y por meta, varias metas y destinos, incorporación de un nuevo Reportero, exclusión de validados, conservación de autoría y rechazo sin cambios parciales ante destinos inválidos.

**Fase 5: circuito de validación** (*depende de la fase 4*)
24. Cambios en el modelo de datos:
    - `ReviewStatus` suma `validated`.
    - `approved` se mantiene como valor técnico de "Publicado", así no hay que reescribir datos.
    - Columnas nuevas: `validated_by`, `validated_at`, `closed_by` y `closed_at`. `attendant_id` se incorpora en la fase 4 para habilitar la continuidad antes del nuevo circuito.
25. Transiciones **[Acordado §1, §4]**:
    - **Envío:** el reporte queda `pending`.
    - **Revisión:** cualquier Responsable de la meta puede devolver con motivo (`returned`) o validar (`validated`). Administración también puede validar; la autovalidación es una acción explícita y separada de la carga.
    - **Desde `validated`:** Administración edita; publica una meta pública o cierra una interna. Publicar y cerrar ejecutan `apply()`; validar no publica ni aplica cambios.
    - **Carga de Administración:** publica directamente en metas públicas. En metas internas, la carga queda validada y Administración debe cerrarla.
    - **Meta interna:** al cerrar, queda consolidada para indicadores internos y no se puede editar ni eliminar.
    - **Devolución administrativa:** no existe; Administración corrige directamente.
26. Permisos de edición, que incluyen archivos, fotos y mapa:
    - **Antes de validar:** el autor, quien atiende el pendiente, los Responsables de la meta y Administración.
    - **Desde `validated`, incluida una meta interna:** **solo Administración**, también si el autor es Responsable.
    - **Publicado:** solo Administración. Su propia edición cuenta como su validación.
    - **Interno cerrado:** no se permite editar ni eliminar; protege el resultado consolidado.
27. Registrar quién cambió qué campos en cada edición, incluida la del autor y la de Administración después de validar. **[Propuesta]**: el formato del detalle está por acordar (§9).
28. Integrar la reasignación de atención de la fase 4 con el estado `validated`: solo sobre reportes `pending|returned` y hacia Reporteros asignados a la meta. `author_id` no cambia; los validados no se reasignan.
29. Se mantiene el único Avance por período: su reserva persiste mientras exista, incluso devuelto o validado; el Hito puede coexistir. No se agrega un cupo global **[D5 resuelta]**.
30. Si dos Responsables resuelven a la vez, `mutate()` usa bloqueo y versión, y el segundo ve "ya resuelto". La validación simultánea tampoco aplica cambios ni genera dos resoluciones.

**Fase 6: organizaciones por meta** (*depende de la fase 3; puede ir en paralelo con la fase 5*)
31. Reutilizar `organizations` con el nombre **Organizaciones**, sin tipo, y agregar su desactivación (`is_active`) **[D6 resuelta]**. La eliminación se retira del ABM; una organización con historial no se borra. **Hecho.**
32. Guardar la organización responsable en `goals.responsible_organization_id` (anulable, con `restrictOnDelete`) y las participantes en `goal_organization(goal_id, organization_id)`, con índice único por meta y organización. **Hecho**, con el cambio de diseño indicado en el estado de ejecución.
    - El esquema garantiza a lo sumo una responsable por meta; la validación impide que la responsable figure también como participante.
    - Solo se ofrecen organizaciones activas, además de las que la meta ya tenía.

    Las metas existentes quedan sin organización hasta que Administración la cargue. Mientras tanto, el portal no muestra nada.
33. Las organizaciones de objetivos y ejes son la unión, sin duplicados, de las de sus metas dentro del conjunto de la audiencia **[Acordado §5]**. `objective_organization` deja de usarse y se elimina en la fase 9. **Hecho** para la página del objetivo, que ya no lee `objective_organization`. Las páginas de eje no mostraban organizaciones; el alcance `Organization::associatedWithGoals()` permite sumarlas cuando se defina su presentación.
34. En el portal, mostrar la organización responsable como dato principal y las participantes con menor jerarquía, en la meta y en sus reportes publicados.
    - Cada reporte guarda una copia de las organizaciones de la meta al publicarse; los reportes publicados antes de esa copia no muestran organizaciones **[D7 resuelta]**.
    - Una organización desactivada no se ofrece para nuevas asociaciones y conserva su nombre en el historial.
    - **Hecho.** La copia se guarda al publicar por la transición de Administración y en la carga directa de Administración. El cierre de un reporte interno no la guarda.
35. El filtro por organizaciones y las sugerencias del Responsable quedan fuera hasta que se definan [Pendiente §7].

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
41. Secciones acordadas **[D8 resuelta]**: Mis metas, Requiere mi acción y Esperando a otra persona; filtros por rol, objetivo/eje y tipo de tarea, ordenados por antigüedad. Administración suma validados por resolver, alertas de continuidad y acceso a la vista pública.

**Fase 9: cierre**
42. Realizar la revisión integral final del manual de usuario, sus capturas y recorridos. Los capítulos afectados, el índice y `historial.md` se actualizan durante cada fase, antes de pasar a la siguiente; no se acumulan para este cierre.
43. Eliminar `objective_user` y el código que queda sin uso, con aprobación previa y con una migración que primero lo archive.

### 4. Verificación
- Primer incremento: `AuthorizationPoliciesTest` cubre el gate, la matriz de permisos vigente, el cambio de visibilidad, la precarga sin consultas repetidas, su limpieza ante errores y la revocación antes de modificar reportes. Pasaron 6 pruebas y 178 aserciones.
- Regresión del incremento: pasaron 90 pruebas y 611 aserciones en `ReportReviewTest`, `ReportReviewConcurrencyTest`, `PeriodicGoalReportsTest`, `ObjectivePanelListsTest`, `ObjectivePanelCommentsTest`, `PanelSideMenuTest`, `ObjectivePageTest`, `ReportPageTest` y `CsvExportsTest`. El test de concurrencia se adaptó a `AuthorizationException` y volvió a pasar por separado (3 pruebas, 42 aserciones).
- Suite completa, ejecutada con confirmación del usuario: **435 pruebas y 6262 aserciones aprobadas**. Sintaxis PHP y `git diff --check` sin errores.
- Fase 1: `PfiMembershipTest` cubre el acceso exclusivo de Administración, el ABM con nombre único, la desactivación que conserva asociaciones y excluye nuevas selecciones, el alta con al menos una categoría y sin duplicados, la reclasificación, la ausencia de permisos para integrantes PFI, los filtros, el CSV, la búsqueda de candidatos y la idempotencia de la migración de datos. Incluye regresiones explícitas: los coordinadores no acceden al buscador PFI, pero conservan la búsqueda y el alta de usuarios no PFI en el equipo heredado. Pasaron 12 pruebas y 172 aserciones.
- Regresión de la fase 1: pasaron 42 pruebas y 362 aserciones en `AdminUsersTest`, `AuthorizationPoliciesTest`, `CsvExportsTest`, `PanelSideMenuTest` y los tests de migración. También pasaron 108 pruebas y 972 aserciones de revisión y superficies públicas. `git diff --check` sin errores.
- Suite completa de la fase 1, ejecutada con confirmación del usuario y repetida al retomar la sesión: **447 pruebas y 6434 aserciones aprobadas**.
- Fase 2: `GoalAssignmentsTest` cubre asignación y baja independiente de ambos roles, acceso de Responsable limitado a sus metas, búsqueda PFI con el alcance correcto, rechazo de personas no PFI, idempotencia, asignación a metas existentes, referencia del equipo anterior y cascada al borrar una meta. Pasaron 6 pruebas y 62 aserciones.
- Regresión de fases 1 y 2: pasaron `PfiMembershipTest`, `AuthorizationPoliciesTest`, `PanelSideMenuTest`, `AdminObjectivesListTest`, `AdminUsersTest` y `CsvExportsTest`: **58 pruebas y 550 aserciones**.
- Suite completa de fase 2, ejecutada con confirmación del usuario: **453 pruebas y 6496 aserciones aprobadas**.
- Fase 3 y regresión adyacente: pasaron `GoalConfidentialityTest`, `ObjectivePageTest`, `ReportPageTest`, `SitemapTest`, `HomeStatsTest`, `CsvExportsTest`, `StructureImportTest`, `ObjectivePanelListsTest`, `ObjectivePanelCommentsTest`, `ReportReviewTest`, `ReportReviewConcurrencyTest`, `AuthorizationPoliciesTest`, `GoalAssignmentsTest`, `PfiMembershipTest`, `PanelSideMenuTest`, `AdminObjectivesListTest` y `AdminUsersTest`: **159 pruebas y 1606 aserciones**.
- Suite completa de fase 3: **459 pruebas y 6575 aserciones aprobadas**.
- Fase 4: regresión de autorización, asignaciones, continuidad, directorios, paneles, comentarios, exportaciones, indicadores y revisión: **156 pruebas y 1367 aserciones aprobadas**. Incluye la revocación del legado, límites entre metas públicas, etiquetas «Equipo», privacidad de reservas de otros Reporteros y bajas atómicas con autoría intacta.
- Las pruebas de concurrencia real reprodujeron y permitieron corregir dos fallos: reasignar hacia una persona que sale simultáneamente y un bloqueo cruzado entre carga y baja PFI. Verifican que siempre quede una persona habilitada atendiendo cada pendiente.
- Suite completa de fase 4, ejecutada con confirmación del usuario: **471 pruebas y 6722 aserciones aprobadas**. Sintaxis PHP y `git diff --check` correctos. `npm run build` completó; conserva avisos de imágenes estáticas no resueltas durante el empaquetado.
- Fase 5 y regresión adyacente: `ReportReviewTest`, `AuthorizationPoliciesTest`, `ReportContinuityTest`, `PeriodicGoalReportsTest`, `GoalConfidentialityTest`, `HomeStatsTest`, `ObjectivePageTest`, `ReportPageTest`, `CsvExportsTest` y `HomeReportsGridTest`: **108 pruebas y 1069 aserciones aprobadas**. La prueba de concurrencia, ejecutada por separado, pasó **6 pruebas y 87 aserciones**, incluidas dos validaciones simultáneas sin doble resolución ni aplicación de cambios.
- Fase 5: sintaxis PHP y `git diff --check` correctos; `npm run build` completó con los avisos ya conocidos de imágenes estáticas no resueltas. Los artefactos generados por el build se limpiaron. Las migraciones se probaron en la base de pruebas; no se aplicaron a la base de la aplicación.
- Suite completa de fase 5, ejecutada con confirmación del usuario: **476 pruebas y 6927 aserciones aprobadas**.
- Fase 6: `GoalOrganizationsTest` cubre la asignación y el intercambio de responsable y participantes, el rechazo de una responsable también participante, la desactivación que excluye nuevas asociaciones y conserva las existentes, la presentación pública de la meta, la unión del objetivo limitada a metas públicas sin leer `objective_organization`, la copia al publicar (también ante renombres y desactivaciones posteriores), los reportes anteriores sin copia y el cierre interno sin copia, y que un error de validación no vuelva a marcar «Activa»: **5 pruebas y 58 aserciones**.
- Regresión de la fase 6 en Administración, autorización, seeders, importación, metas, objetivos, reportes, paneles, portal y jerarquías: **329 pruebas aprobadas**. Detectó que `DemoStructureImportTest` no enviaba la visibilidad exigida desde el paso 18; se corrigió la prueba. La migración se aplicó, revirtió y volvió a aplicar en la base de pruebas. Sintaxis PHP y `git diff --check` correctos.
- Suite completa de fase 6, ejecutada con confirmación del usuario: **482 pruebas y 7019 aserciones aprobadas**.
- Los comandos de pruebas se ejecutaron con `APP_ENV=testing DB_DATABASE=participes_testing`. No se ejecutaron migraciones ni escrituras sobre la base de la aplicación.
- Las pruebas de integración usan PHPUnit y MySQL (`participes_testing`), con `RefreshDatabase` o el aislamiento existente de `DatabaseMigrations` para concurrencia. Se conservan las pruebas unitarias de cálculo.
- Cada fase corre `php artisan test --compact` con sus tests, más la regresión de revisión y de superficies públicas: `ReportReview*`, `PeriodicGoalReportsTest`, `SitemapTest`, `HomeStatsTest`, `*SearchTest`, `CsvExportsTest`, `ObjectivePageTest` y `ReportPageTest`.
- Los tests públicos ponen `hidden = false` (`.ai/rules/tests.md`).
- El test de contrato de confidencialidad usa 40 metas (20 públicas y 20 internas), con una persona asignada a 3 internas, y verifica 20, 23 y 40 en el inicio, la ficha de objetivo, la API, las exportaciones y el dashboard de Administración.
- Las migraciones de datos se prueban como `ReportReviewMigrationTest`: reportes `approved` existentes siguen públicos y las metas existentes quedan públicas.
- Cada fase se despliega por separado, con backup y con escrituras y workers detenidos (`DEPLOY.md`).

### 5. Decisiones abiertas

**Bloqueantes**

No quedan decisiones bloqueantes. D1 a D8 se resolvieron en las conversaciones posteriores a la implementación inicial; sus definiciones están en **Tus decisiones incorporadas**. La dependencia técnica de la baja con la continuidad en fase 4 no es una decisión de producto pendiente.

**No bloqueantes**
- **Devolución desde Administración** (§7): resuelto; no se habilita. Administración edita directamente.
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
- Administración puede validar una propuesta pendiente, editar reportes no cerrados y resolver reportes validados según la visibilidad de la meta; no puede devolver.
- Los `pending` existentes pasan a esperar a un Responsable. Si la meta no tiene Responsables, los atiende Administración.
- Las comunidades, la portada y los archivos del objetivo pasan a ser solo de Administración.
- No se agregan dependencias. Los permisos se resuelven con policies y gates.
