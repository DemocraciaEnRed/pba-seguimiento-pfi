# Phase 0 Baseline Report

Date: 2026-08-19
Branch: `upgrade/laravel-13.x`

## Result

Phase 0 and initial Phase 1 execution are complete. The project now runs on Laravel 9 with dependency and middleware migrations applied, and baseline validation commands pass.

## Environment

- PHP 7.4: `PHP 7.4.33` via `php7.4`.
- Newer PHP available: `PHP 8.4` via `php8.4` / system Composer runtime.
- Composer: `2.8.11`.
- Laravel: baseline `8.83.27`, upgraded to `9.52.22`.
- Node tooling: existing production build completed successfully after `nvm use`.
- Maria database container: running and reachable through the configured application connection.

## Checks Completed

| Check | Result |
| --- | --- |
| `composer validate --no-check-publish` | Passed. |
| `php7.4 artisan --version` | Passed: Laravel Framework 8.83.27. |
| `php7.4 artisan config:clear` | Passed. |
| `php7.4 artisan route:list` | Passed: 691 output lines. |
| `php7.4 artisan test` | Passed: 2 tests. |
| `php7.4 artisan migrate:status` | Passed: all 5 application migrations applied. |
| `npm run prod` after `nvm use` | Passed: Mix production assets compiled. |
| `php7.4 artisan about` | Not available in Laravel 8; expected, not a failure. |

## Test Coverage Limitation

The current suite contains only the default unit and feature example tests. It does not verify the application workflows that are highest risk during the upgrade:

- Authentication.
- Objectives, goals, reports, comments, and admin flows.
- Excel exports.
- Image upload and processing.
- Mapbox report georeference behavior.
- Mail notifications and Redis queues.

A clean disposable-database migration plus seeded-data smoke run also remains to be performed. The existing database connection and migration state were verified, but production-like behavior has not yet been exercised.

## Worktree Notes

The worktree already contained a modification to `.env.example`. The frontend build also regenerated tracked files under `public/css`, `public/js`, and `public/mix-manifest.json`; these generated changes should be reviewed before committing and kept separate from backend upgrade changes if they are not intentional.

## Phase 0 Gate

**Decision: proceed to Phase 1 dependency dry run, with known test and smoke-coverage limitations.**

The application metadata validates, Laravel boots, routes register, tests pass, the database is reachable, and assets compile. Before any real dependency update, run a Laravel 8 to 9 Composer dry run using PHP 8.4. Do not modify `composer.json` or `composer.lock` until the conflict set is reviewed.

## Phase 1 Dry-Run Result

The Laravel 8 to 9 target dependency graph was resolved successfully in an isolated temporary copy of `composer.json` and `composer.lock` using PHP 8.4. The repository manifests were not changed.

The first resolver run was blocked by the missing PHP 8.4 `ext-zip` extension, required by PhpSpreadsheet through `maatwebsite/excel`. A second isolated run with only `ext-zip` ignored resolved the graph without additional package conflicts.

The dry run selected or confirmed:

- Laravel Framework `v9.52.22`.
- Collision `v6.4.0`.
- Spatie Laravel Ignition `v1.7.2`, replacing Facade Ignition.
- Flysystem 3.
- Symfony Mailer.
- Maatwebsite Excel `v3.1.70`.
- Removal of `fideloper/proxy` and `fruitcake/laravel-cors` from the root requirements.

`ext-zip` has since been enabled for PHP 8.4. The real Laravel 9 dependency update completed successfully, including lockfile regeneration and package discovery.

The first Laravel 9 test run exposed two expected middleware references to removed packages. These were fixed in:

- `app/Http/Middleware/TrustProxies.php`: switched from Fideloper to Laravel's framework middleware and updated forwarded-header flags.
- `app/Http/Kernel.php`: switched from Fruitcake CORS middleware to Laravel's framework `HandleCors` middleware.

After those fixes, the existing test suite passes: 2 tests passed.

## Laravel 9 Smoke And Audit

CLI smoke checks completed on Laravel 9:

- `php8.4 artisan --version` passed (`Laravel Framework 9.52.22`).
- `php8.4 artisan config:clear` passed.
- `php8.4 artisan route:list` succeeded.
- `php8.4 artisan migrate:status` passed.
- `php8.4 artisan test` passed (2 tests).
- Local HTTP smoke with `php8.4 artisan serve`:
	- `/` returned 200.
	- `/login` returned 200.
	- `/api/` returned 404 (no root API route expected).
- `php8.4 artisan queue:failed` returned no failed jobs.
- `php8.4 artisan schedule:list` reported no scheduled tasks defined.

Security audit summary (`composer audit`):

- 4 advisories reported for `laravel/framework` (medium/high severities).
- Advisory IDs: `PKSA-m5cs-t1y6-qpcs`, `PKSA-3r5d-mb8f-1qw9`, `CVE-2026-48019`, `CVE-2025-27515`.
- These are addressed in later framework lines; practical mitigation is to continue the planned upgrade path to Laravel 10+ and eventually 12/13, not to pin in Laravel 9.

Runtime note:

- PHP 8.4 triggers deprecation warnings from some Laravel 9 era dev packages (`nunomaduro/collision` and `spatie/laravel-ignition`). This is expected on a newer runtime and should improve as we move to Laravel 10+ compatible package versions.

**Phase 1 status: complete for dependency migration and baseline validation.**

## Next Command Group

Start Phase 2 (Laravel 9 to 10) in this branch:

1. Convert Eloquent `$dates` to `$casts` in the identified models.
2. Prepare `composer.json` for Laravel 10 constraints (`laravel/framework:^10.0`, `laravel/ui:^4.0`, `spatie/laravel-ignition:^2.0`, `nunomaduro/collision:^7.0`).
3. Keep PHP runtime on `php8.4` (satisfies Laravel 10 minimum 8.1).
4. Run dependency dry run first, then apply and validate the same gate commands.
