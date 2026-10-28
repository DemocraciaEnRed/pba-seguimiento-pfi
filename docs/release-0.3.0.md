# Versión 0.3.0 — Notas de despliegue

Pasos para actualizar un entorno existente (staging o producción) a la versión 0.3.0.

## Qué incluye

- **Iniciativas:** nuevo módulo en **Administración → Portal → Iniciativas**. Las iniciativas se muestran en el carrusel **Iniciativas destacadas** del inicio y en la sección de cada eje en **El Plan**. Ver [manual de usuario, capítulo 10](./manual-usuario/10-iniciativas.md).
- **Descripción de los ejes:** campo nuevo en el alta y la edición de ejes, visible en **El Plan**.
- **Rediseño de El Plan:** una sección por eje con estadísticas, objetivos y metas, últimos reportes e iniciativas vinculadas.

## Pasos de actualización

Después de desplegar el código:

```bash
# 1. Crear las tablas nuevas (initiatives, category_initiative) y la columna categories.description
php artisan migrate --force

# 2. Poblar las iniciativas del Plan y las descripciones de los ejes
php artisan db:seed --class=InitiativesSeeder --force

# 3. Asegurar el enlace público de storage (para imágenes de fondo y logos)
php artisan storage:link

# 4. Limpiar cachés de configuración, vistas y del portal
php artisan optimize:clear
```

El orden importa: el seeder necesita que las migraciones ya estén aplicadas y que los ejes existan (se identifican por su ícono, por ejemplo `observatorio-integridad`). Si un eje no existe, sus iniciativas se cargan igual pero sin ese vínculo.

`storage:link` solo es necesario si el enlace `public/storage` todavía no existe en el servidor.

## Qué hace el seeder

`InitiativesSeeder` carga 61 iniciativas (8 de ellas destacadas en el inicio) y las descripciones de los 7 ejes.

- Es **idempotente**: se puede volver a correr sin duplicar datos. Busca cada iniciativa por su **título**.
- **Sobrescribe** el subtítulo, enlace, orden, destacado y ejes vinculados de las iniciativas que encuentra por título, y la **descripción** de los ejes. Si después de la primera carga se editan esos datos desde la administración, volver a correrlo los pisa.
- No borra iniciativas creadas desde la administración ni toca imágenes ni logos.

En una instalación nueva, `php artisan db:seed` ya lo incluye (corre después de `BaseDataAppSeeder`).

## Configuración opcional

Los documentos del PFI y el PEI que se muestran en **El Plan** (título, enlace al PDF y portada) están en `config/plan.php` y se pueden sobrescribir con variables de entorno. Si se cambian, correr `php artisan config:clear`.
