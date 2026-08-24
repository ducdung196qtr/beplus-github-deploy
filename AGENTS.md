# Beplus GitHub Deploy — agent briefing

Use this file when changing code under `wp-content/plugins/beplus-github-deploy/`. Architecture and naming standards live in [`docs/ARCHITECTURE.md`](./docs/ARCHITECTURE.md).

## What this plugin is

- **WordPress plugin:** Deploy plugins/themes from GitHub (public or private) with backups, rollback and webhook auto-deploy.
- **Architecture:** Service-container boot via `BeplusManager\Core\Plugin`; modules are wired in `Plugin::boot()` with constructor injection.
- **Stack:** PHP 7.4+ (8.x supported), PSR-4 autoload under `src/`, vanilla JS admin UI (no build step), WP Dashicons.
- **Target:** WordPress 6.0+.

## Naming and constants

| Item | Value |
|------|-------|
| Bootstrap file | `beplus-github-deploy.php` |
| Text domain | `beplus-github-deploy` |
| PHP namespace | `BeplusManager\` → `src/` |
| Global functions | `beplus_manager_*` |
| Constants | `BEPLUS_MANAGER_*` |
| REST namespace | `beplus-github-deploy/v1` |
| Admin root id | `#beplus-github-deploy-root` |
| CSS prefix | `bm-` |

## Files you usually touch

| Area | Edit (source) | Do not edit as source |
|------|----------------|------------------------|
| Bootstrap / activation | `beplus-github-deploy.php` | — |
| Core / domain PHP | `src/**/*.php` | — |
| Global helpers | `includes/helpers.php` | — |
| Admin UI | `admin/js/admin.js`, `admin/css/admin.css` | — |
| Backups | `src/Backup/BackupManager.php` | — |
| REST API | `src/REST/*Controller.php` | — |

## Dev tools

- **PHP quality gate:** `npm run lint:php:all` (PHPStan level 6 + PHP CS Fixer check).
- **Auto-fix:** `npm run lint:php:fix` (PHP CS Fixer).
- **Install tooling:** `npm install && npm run composer:install` (local Composer via `scripts/composer.mjs`, no global Composer needed).
- **Hooks:** Husky `pre-commit` (lint-staged + full PHP gate) and `pre-push` (CI).
- **JS syntax check:** `npm run js:check` (`node --check admin/js/admin.js`).

See [`docs/DEVELOPMENT.md`](./docs/DEVELOPMENT.md) for the full workflow and [`docs/RULES.md`](./docs/RULES.md) for coding rules.
