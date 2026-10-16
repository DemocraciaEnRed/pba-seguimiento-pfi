# Backlog de mejoras SEO

## Estado actual

Las fases 1 a 3 están implementadas:

- Selector de indexación en SEO y Analytics, desactivado por defecto.
- Directivas coherentes en meta robots y `X-Robots-Tag`. Los paneles, la autenticación, las API y el contenido oculto permanecen excluidos.
- Metadatos automáticos por sección y contenido, canónicas sin tracking e imágenes sociales con respaldo global.
- Sitemap dinámico con `spatie/laravel-sitemap`, limitado al contenido público, y robots dinámico condicionado al selector.
- División del sitemap al superar 1.000 URLs, sin crawler ni archivos persistentes.

Esta base permite publicar sin completar las mejoras siguientes. Las fases 4 y 5 quedan diferidas como tareas deseables, no como requisitos para habilitar indexación. Cada una requiere una decisión explícita antes de implementarse.

Las instrucciones operativas vigentes están en [DEPLOY.md](../DEPLOY.md).

## Fase 4 pendiente: listados rastreables sin depender de JavaScript

**Prioridad sugerida:** media; abordar antes de ampliar los datos estructurados.

### Motivación e impacto

Los listados de objetivos y reportes cargan sus resultados mediante Vue y la API después de recibir el HTML inicial. Google puede ejecutar JavaScript, pero otros crawlers no necesariamente lo hacen. El sitemap ya facilita descubrir las páginas individuales, cuyo contenido principal se renderiza desde Laravel; esta fase complementaría ese mecanismo con navegación HTML.

La mejora consiste en entregar resultados iniciales y enlaces de paginación desde el servidor, y utilizar Vue para mantener la interacción. También dejaría navegación básica disponible si JavaScript no carga. No garantiza mejoras de posición ni implica convertir toda la aplicación a SSR.

### Tareas

1. Implementar primero los listados generales de Objetivos y Seguimiento.
2. Consultar desde Laravel la primera página de resultados y las páginas solicitadas por URL, reutilizando filtros, orden y restricciones de visibilidad de la API.
3. Renderizar tarjetas y enlaces HTML de paginación, sin duplicar lógica de negocio entre backend y frontend.
4. Adaptar Vue para aprovechar los datos iniciales, evitando tarjetas duplicadas y una segunda consulta inicial innecesaria.
5. Conservar búsqueda, filtros, orden, navegación y aspecto visual actuales.
6. Definir URLs paginadas estables y canónica propia por página. Mantener búsquedas y combinaciones de filtros en `noindex` y fuera del sitemap.
7. En una segunda entrega, extender el enfoque a los reportes asociados a cada objetivo y meta.

### Superficies involucradas

- [ObjectiveController](../app/Http/Controllers/ObjectiveController.php) y [ReportController](../app/Http/Controllers/ReportController.php).
- [Listado de objetivos](../resources/views/portal/catalogs/objectives.blade.php) y [listado de reportes](../resources/views/portal/catalogs/reports.blade.php).
- [Búsqueda Vue de objetivos](../resources/js/components/portal/catalogs/objectives/Search.vue) y [búsqueda Vue de reportes](../resources/js/components/portal/catalogs/reports/Search.vue).
- Secciones de reportes en [objetivos](../resources/views/objective/view.blade.php) y [metas](../resources/views/objective/goal/view.blade.php), junto con sus controladores.
- [SeoMetadata](../app/Services/SeoMetadata.php), [SearchIndexing](../app/Services/SearchIndexing.php) y pruebas relacionadas.

### Criterios de aceptación

- Sin JavaScript hay resultados útiles y enlaces de paginación rastreables.
- Con JavaScript se mantienen filtros, búsqueda, orden y paginación, sin duplicados ni parpadeos innecesarios.
- No se expone contenido oculto ni se introducen consultas por cada elemento.
- Metadatos, canónicas y directivas de indexación son coherentes en cada variante.
- Pruebas de backend, validación en navegador con y sin JavaScript y build de frontend aprobados.

### Fuera de alcance

Migrar a Nuxt, reemplazar Vue, introducir SSR general, cambiar las URLs de entidades o rediseñar el portal.

## Fase 5 pendiente: datos estructurados

**Prioridad sugerida:** complementaria; menor que la mejora de los listados.

### Motivación e impacto

JSON-LD permite describir explícitamente el sitio, la organización, las páginas y su jerarquía para los buscadores. Complementa los metadatos actuales sin modificar la interfaz. Puede ayudar a interpretar el contenido y habilitar ciertas presentaciones enriquecidas, pero no garantiza su aparición ni una mejora de ranking y nunca anula `noindex`.

### Tareas

1. Identificar los datos reales de la organización responsable antes de emitir `Organization` y `WebSite`.
2. Agregar `WebPage` o `CollectionPage` según el contenido de cada página.
3. Agregar `BreadcrumbList` únicamente donde haya una jerarquía de navegación real y visible.
4. Evaluar `Article` solo para reportes que correspondan a contenido editorial con autoría y fechas. No aplicarlo indiscriminadamente ni inventar un tipo especializado para metas.
5. Reutilizar URLs canónicas, nombres, imágenes y fechas existentes. No inventar datos, bloques duplicados ni nuevas fechas de publicación.
6. Serializar JSON de forma segura para su inserción en HTML y respetar la visibilidad pública de las entidades.
7. Mantener la generación automática: no agregar campos SEO manuales por cada objetivo, meta o reporte sin una necesidad concreta.

### Superficies involucradas

- [SeoMetadata](../app/Services/SeoMetadata.php) y [parcial compartido de etiquetas](../resources/views/partials/seo-tags.blade.php).
- Controladores públicos que aportan contexto de entidades.
- Parciales Blade y nuevas pruebas de estructura y serialización JSON-LD.

### Criterios de aceptación

- JSON-LD válido, sin duplicados y coherente con el contenido visible.
- Nombres, tipos, autoría, imágenes, fechas y URLs verificables.
- Pruebas con tildes, comillas y contenido que pudiera cerrar una etiqueta `script`.
- Sin consultas adicionales por elemento ni cambios de interfaz.
- Validación externa solo con URLs públicas y autorización; nunca enviar datos privados a validadores.

## Verificaciones operativas antes de habilitar producción

Estas comprobaciones no dependen de completar las fases 4 y 5:

1. Confirmar el dominio HTTPS real y revisar canónicas y URLs del sitemap.
2. Aplicar las migraciones del despliegue y verificar los requisitos de PHP y Composer.
3. Retirar copias antiguas de robots o sitemap y ajustar servidor/CDN para que las rutas dinámicas lleguen a Laravel sin caché obsoleta.
4. Revisar robots, sitemap, meta robots y cabeceras con el selector apagado y encendido.
5. Mantener desactivada la indexación en development y staging accesibles desde Internet; una prueba temporal no debe quedar habilitada por accidente.
6. Habilitar indexación en producción únicamente por decisión del administrador.
7. Con acceso autorizado, verificar la propiedad en Search Console y registrar el sitemap. La indexación efectiva y sus tiempos dependen del buscador.

## Cómo retomar

Revisar el estado del código y las versiones de paquetes antes de empezar. Implementar una entrega acotada, agregar pruebas y validar que se conservan permisos y comportamiento existentes. No introducir dependencias nuevas sin aprobación.
