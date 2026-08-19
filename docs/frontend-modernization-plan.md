# Frontend Modernization Plan (Post Laravel 13)

Date: 2026-08-19
Context: Laravel backend is upgraded to 13.x; frontend remains Vue 2.7 + Laravel Mix 5 + Webpack.

## Goal

Modernize the frontend workflow with minimum risk:

1. Move build pipeline from Laravel Mix/Webpack to Vite.
2. Keep Vue 2 components working first (bridge strategy).
3. Defer Vue 3 component migration to a separate phase.

This avoids mixing two high-risk changes at once.

## Current Frontend Baseline

- Build tool: `laravel-mix` 5 in `webpack.mix.js`.
- Vue runtime: `vue` 2.7 + `vue-template-compiler`.
- Blade asset loading via `mix()` in:
  - `resources/views/layouts/app.blade.php`
  - `resources/views/layouts/admin.blade.php`
  - `resources/views/start.blade.php`
- JS entry points:
  - `resources/js/app.js`
  - `resources/js/admin-app.js`
- CSS build entry:
  - `resources/sass/app.scss`

## Recommendation

Use a two-step approach:

1. Build migration only (Mix -> Vite) with Vue 2 bridge.
2. Vue framework migration (Vue 2 -> Vue 3) in a later dedicated track.

## Why This Is Best

- Backend already changed significantly (Laravel 8 -> 13).
- Current Vue ecosystem packages include several Vue 2-era dependencies.
- Vite can be introduced without rewriting all components immediately.
- You get faster builds/HMR now, and reduce migration blast radius.

## Phase A: Prepare (No Functional Changes)

### Tasks

1. Create a dedicated branch for frontend modernization work.
2. Freeze broad UI refactors during build migration.
3. Inventory Vue 2-locked packages and mark replacement candidates.
4. Confirm Node runtime from `.nvmrc` and lock npm install path.

### Commands

```bash
nvm use
npm ci
npm run prod
```

### Exit Criteria

- Baseline build and app pages still work before migration starts.

## Phase B: Migrate Build Tooling to Vite (Keep Vue 2)

### Tasks

1. Install Vite stack for Laravel:
   - `vite`
   - `laravel-vite-plugin`
2. Add Vue 2-compatible Vite integration.
3. Create `vite.config.js` with multiple entry points:
   - `resources/js/app.js`
   - `resources/js/admin-app.js`
   - `resources/sass/app.scss` (or import CSS/SCSS from JS)
4. Update Blade templates from `mix()` to `@vite(...)`:
   - `resources/views/layouts/app.blade.php`
   - `resources/views/layouts/admin.blade.php`
   - `resources/views/start.blade.php`
5. Add/adjust npm scripts (`dev`, `build`) for Vite.
6. Keep Mix scripts temporarily for rollback during verification.

### Laravel 13 Conventions To Follow

- Use `@vite()` directive in Blade for script/style loading.
- Use Laravel Vite plugin input array for multi-entry setup.
- Prefer importing CSS in JS where practical.

### Exit Criteria

- `npm run build` passes with Vite.
- Main pages render assets correctly in browser.
- HMR works during local development.

## Phase C: Stabilize Vite Migration

### Tasks

1. Validate admin and public bundles independently.
2. Verify static assets referenced in Blade (images/fonts).
3. Decide if `public/js` and `public/css` compiled artifacts stay tracked.
4. Remove Mix-only artifacts/config only after parity is confirmed.

### Validation Checklist

- Public home and catalog pages.
- Admin panel pages that mount Vue components.
- Report creation/edit flows with map/upload widgets.
- Notifications/toasts and scroll plugins.

### Exit Criteria

- Vite build output parity with old Mix output.
- No runtime console errors on critical pages.

## Phase D: Optional Vue 3 Migration (Separate Project)

Do this after Vite is stable.

### Known Package Risk Areas

Likely replacements/upgrades required for Vue 3 compatibility:

- `mapbox-gl-vue`
- `vue-awesome-swiper`
- `tiptap` / `tiptap-extensions` (v1)
- `vue-chartjs` (v3)
- `vue-toasted`
- `hooper`

### Strategy

1. Replace one package family at a time.
2. Migrate entry files to Vue 3 app boot pattern.
3. Move from global registration to modular component registration.

## Rollback Plan

If Vite migration fails during Phase B/C:

1. Keep Mix scripts and `mix()` Blade version in a rollback commit.
2. Revert Blade asset directives to Mix.
3. Restore previous npm scripts and `webpack.mix.js` as primary pipeline.
4. Ship no frontend toolchain change until parity is re-established.

## Suggested Commit Slices

1. Add Vite dependencies + config only.
2. Switch Blade templates to `@vite`.
3. Script updates + CI/build updates.
4. Remove Mix path after verification.

## Session Handoff Prompt (For Separate Session)

Use this as the first message in the next session:

"Implement Phase B and C from `docs/frontend-modernization-plan.md`.
Start with a Vite migration that keeps Vue 2 working, update the three Blade layout files from `mix()` to `@vite`, keep rollback safety, and stop before Vue 3 migration. Run build/dev validation and report parity gaps."

## Success Definition

- Laravel 13 app builds frontend via Vite.
- Vue 2 component behavior remains stable.
- Mix is optional/deprecated but can still be used as fallback until final cleanup.
- Clear path documented for later Vue 3 migration.
