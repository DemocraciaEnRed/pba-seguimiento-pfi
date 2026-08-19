# Phase 2 Readiness: Laravel 9 To 10

Date: 2026-08-19
Branch: `upgrade/laravel-13.x`

## Readiness Decision

Ready to start Phase 2.

Laravel 9 installation and baseline validation are complete:

- Framework running at `9.52.22`.
- Dependency graph resolved and installed on PHP 8.4.
- App boot, route registration, migration status, and existing tests pass.

## Preconditions Verified

- PHP runtime for Phase 2 is available: `php8.4` (Laravel 10 requires >= 8.1).
- Composer metadata valid after Phase 1 changes.
- ZIP extension enabled for PHP 8.4, so PhpSpreadsheet / Excel dependencies resolve.

## Known Risk Carry-Over

- Test coverage remains minimal (2 placeholder tests).
- Manual product-level smoke (auth, admin CRUD, exports, media, map behavior) is still required.
- Composer audit reports vulnerabilities on `laravel/framework` in the current line; expected to be addressed by progressing through major upgrades.
- PHP 8.4 deprecation noise from dev tooling packages (Collision and Ignition) is expected on Laravel 9 and should be reevaluated after Phase 2 package upgrades.

## Phase 2 Scope

Follow `migration-guides/upgrade-guide-9.x-to-10.md` and execute in this order:

1. Code compatibility prep:
   - Replace model `$dates` usage with `$casts` in:
     - `app/ActionLog.php`
     - `app/Event.php`
     - `app/Milestone.php`
     - `app/Report.php`
2. Composer constraint updates for Laravel 10:
   - `laravel/framework:^10.0`
   - `laravel/ui:^4.0`
   - `spatie/laravel-ignition:^2.0`
   - `nunomaduro/collision:^7.0`
   - Evaluate `minimum-stability` normalization to stable.
3. Run dependency dry run, then apply.
4. Validation gate:
   - `composer validate`
   - `php8.4 artisan about`
   - `php8.4 artisan config:clear`
   - `php8.4 artisan route:list`
   - `php8.4 artisan test`
   - `npm run prod`

## Proposed Checkpoint Commit Layout

Keep this order for clean history:

1. Commit A: Laravel 9 upgrade implementation and baseline report updates.
2. Commit B: Optional generated frontend asset refresh (if intentionally tracked).
3. Commit C onward: Phase 2 implementation slices.
