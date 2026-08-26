# Changelog

### v3.0 (2026-08-19)

* Participes has been upgraded from Laravel 8 to Laravel 13, one major version at a time (9 → 10 → 11 → 12 → 13).
* It now requires PHP 8.2 or higher (PHP 8.4 recommended).
* Upgraded `laravel/tinker` to ^3.0 and `phpunit/phpunit` to ^12.0.
* Removed `barryvdh/laravel-debugbar` as it is not compatible with Laravel 13 (its config and service provider were removed too).
* The frontend build moved from Laravel Mix to Vite (with a Vue 2.7 bridge via `@vitejs/plugin-vue2`). Use `npm run dev` (with hot reload) and `npm run build` instead of `npm run watch` / `development` / `production`.
* The Vue component entry points (`app.js`, `admin-app.js`, `bootstrap.js`) were converted from CommonJS `require()` to ES module `import`s, styles are now imported from JS, the SCSS drops the webpack `~` import prefixes, and the Blade layouts load assets via `@vite(...)` instead of `mix(...)`. The old Laravel Mix config and compiled `public/js` / `public/css` artifacts were removed.
* Vue is now aliased to the compiler-included build so components using in-DOM / `x-template` templates keep working under Vite.
* A Vue 2 → Vue 3 component migration is intentionally deferred to a later release; this version keeps the existing Vue 2.7 components running on the new Vite pipeline.
* Fixed avatar upload by explicitly using the GD driver for Intervention Image.
* Added a `composer run dev` script that starts the PHP server, the queue worker (`mailer,default`) and the Vite dev server together, plus a `composer run test` script.
* Added a `deprecations` log channel to help surface deprecated APIs ahead of future upgrades.
* Added step-by-step upgrade guides (Laravel 8.x → 13.x) under `migration-guides/`, plus the upgrade plan, package compatibility matrix and phase readiness reports under `docs/`.
* Added Laravel best practices documentation.
* **Action required on update:** some environment variables were renamed to match Laravel 13. Update your `.env` (keep the same values):
  * `CACHE_DRIVER` → `CACHE_STORE`
  * `FILESYSTEM_DRIVER` → `FILESYSTEM_DISK`
  * `BROADCAST_DRIVER` → `BROADCAST_CONNECTION`
  * `DATABASE_URL` → `DB_URL`
* As always, we recommend you to make a backup of your database before running the migrations.
* The changelog was moved from `README.md` to this dedicated `CHANGELOG.md` file.

### v2.3.3 (2023-04-13)

* Fixed delete a FAQ question, it was not working (never fully implemented!)
* Now the Report form doesn't show the "milestones" tab when there are no milestones in the goal!
* Small visual tweak

### v2.3.2 (2023-03-16)
* Fixed paginator not using Bootstrap 5 classes.
* Now the tags of a objective, if the list is empty, it wont show the "Tags" title neither a "No hay tags" message.

### v2.3.1 (2023-02-03)
* Fixed a bug in "Objetivos" view.

### (2023-02-03)
* No new version. Just a small fix in the README.md file.
* Added DEPLOY.md file with instructions to deploy the project in a LAMP server.

### v2.3 (2023-02-01)

* There is a new migration with this version, make sure to run it. You can do this by running `php artisan migrate` in the root directory of the project. In production you should run `php artisan migrate --force` to avoid any errors.
* As always, we recommend you to make a backup of your database before running the migrations.
* Fixed some bugs with the admin panel for maps (nothing critical)
* New "homepage" admin panel. Now you have in one place all the customizations you can do to the homepage. Inside we included a few ones:
  * You can show/hide the latest published reports
  * You can show/hide the graph of reports published in the last 15 days
  * You can "move" the latest published reports after the "latest objectives updated"
  * You can show/hide the categories selector.
  * Moved the "subtitle" of the homepage to the admin panel
* New "SEO & Analytics" admin panel. Nothing new, but all the cusotmizations you can do to the SEO and Analytics are now in one place.
* Fixed "Limpiar cache" button in the admin panel. Now it works as expected.
* Fixed some "boolean" casts in the Settings model.
* New component "Category selector" which is a carrousel component of the categories of the system. When you click in a category, it takes you to the catalog of objectives.
* Some changes in some views:
  * In the objective view, if the following attributes are empty, they wont be shown: "Miembros del equipo", "Organizciones", "Metas"
  * In the goal view, if the following attributes are empty, they wont be shown: "Hitos"
* Some secondary fixes (Demo data had a bug when creating generic organizations)


### v2.2 (2023-02-01)

* No migrations in this version
* Major update in mapbox GL JS from 1.11.1 to 2.4.1, with this update we are able to use the new mapbox styles and the new mapbox studio.
* Updated mapbox-gl-draw plugin from 1.0.9 to 1.4.0.
* NOTE: You should use the Style URL mapbox://styles/mapbox/light-v11 instead of the old style url mapbox://styles/mapbox/light-v10
* Fixed some maps not getting the Mapbox API Key

### v2.1 (2023-02-01)

* There is a new migration with this version, make sure to run it. You can do this by running `php artisan migrate` in the root directory of the project. In production you should run `php artisan migrate --force` to avoid any errors.
* As always, we recommend you to make a backup of your database before running the migrations.
* Added Map & Georeference admin to the admin panel. Now instead of setting the map and georeference in the .env file, you can do it in the admin panel.
* The env vars `MAPBOX_API_KEY` & `MAPBOX_MAP_STYLE` are no longer required in the .env file. If you are planning to update to this version, make sure that after the update you set the api key and map style in the admin panel.
* You can also hide the map from the homepage
* If maps & georeference are disabled, the map will not be shown in the homepage and reports wont have a map. The report panel also wont show the "Map" option in the menu.

#### v2.0 (2023-01-18)

* New migrations are being added for the new Laravel version. If you installed Participes before 2023, you should run the new migrations. You can do this by running `php artisan migrate` in the root directory of the project. In production you should run `php artisan migrate --force` to avoid any errors.
* As always, we recommend you to make a backup of your database before running the migrations.
* Participes has been updated to Laravel 8. It requires PHP 7.4 or higher.
* The env vars `ANALYTICS_PROVIDER`, `ANALYTICS_PROVIDER`, `ANALYTICS_TRACKING_ID` are no longer required in the .env file. From now on you can use the admin panel to set up Google Analytics 4 by inserting the tracking ID.
* Removed fzaninotto/faker dependency for the sake of Laravel 8. Faker 
