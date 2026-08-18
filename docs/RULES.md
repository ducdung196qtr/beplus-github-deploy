# Coding Rules & Conventions

These rules keep the codebase consistent, safe and reviewable. Follow them for every change.

## PHP

### 1. Namespaces & autoloading
- Every class lives under the `BeplusManager\` prefix in `src/`, path mirrors the namespace: `BeplusManager\Deploy\Deployer` → `src/Deploy/Deployer.php`.
- One class per file. No Composer runtime dependency — the PSR-4 fallback in `beplus-manager.php` handles loading.

### 2. Style (WordPress coding standards)
- Tabs for indentation, line length ≤ 100 chars where reasonable.
- `snake_case` for functions/methods, `$snake_case` for variables, `ClassName` for classes.
- Space after control keywords: `if ( ... ) {`, `foreach ( ... )`.
- Yoda conditions when comparing to a literal: `if ( ! $pkg )`, `if ( 'plugin' === $type )`.
- Always brace single-line blocks.

### 3. Type declarations
- PHP 7.4 minimum: use scalar type hints (`string`, `int`, `bool`, `array`) and `: void` / return types on **new** methods.
- `array{...}` shape annotations in docblocks for array returns (PHPStan style).

### 4. WordPress APIs only
- Use `wp_remote_*` for HTTP, `get_option`/`update_option` for storage, `set_transient` for progress.
- Never call `curl_*`, `file_get_contents(http://...)` or raw `mysql_*` directly.
- Escape output (`esc_html`, `esc_url`, `esc_attr`) — all dynamic values in HTML/JS.
- Sanitize input (`sanitize_text_field`, `sanitize_file_name`, `sanitize_key`).

### 5. Errors
- Return `WP_Error` from internal helpers for expected failures.
- REST handlers return `WP_REST_Response` with a proper HTTP status (`404`, `500`, `403`) and a human-readable `message`.
- Never `die()`/`exit` inside reusable code (except the backup downloader which streams a file — documented).

### 6. Security
- Admin REST routes: `permission_callback` → `current_user_can('manage_options')` (via `check_admin`).
- Never echo the stored GitHub token — the API returns `token: 'set'` only.
- HMAC verification uses `hash_equals()` (constant-time).
- ZIP extraction must validate paths (see `extract_backup_zip` / `install_from_zip`) — no path traversal.

## JavaScript (`admin/js/admin.js`)

- Vanilla ES5-style JS (no build step, no framework) — IIFE, `var`, no arrow functions.
- DOM building through small helpers: `el(tag, attrs)`, `icon('dashicon-name')`, `icBtn(classes, icon, label)`.
- All REST calls go through `api(path, opts)` — it injects the nonce, JSON-encodes bodies and throws `Error(message)` on non-2xx.
- Errors from deploy/rollback show a **modal popup** (`showErrorModal`) with a "please check the package information" hint — never silently swallow failures.
- Use **WP Dashicons** (`<span class="dashicons dashicons-...">`) for all icons. No emoji, no inline SVG.
- UI text in English.

## CSS (`admin/css/admin.css`)

- Class prefix `bm-` for everything (`.bm-card`, `.bm-btn`, `.bm-table`, ...).
- Mobile-friendly: `flex-wrap`, `min-width` on form fields.
- Use WP design tokens (`#2271b1` primary, `#d63638` danger, `#f0f6fc` info tint).

## Docs

- Every user-facing change that alters behaviour must update `README.md` **or** `docs/` (ARCHITECTURE / DEVELOPMENT / RULES).
- Docs in English, short sections, tables over prose where possible.

## Git

- Conventional-ish commit messages: `Add ...`, `Fix ...`, `Refactor ...`, `Bump version to X`.
- One logical change per commit. No generated files (`.gitignore` covers `*.zip`, `node_modules/`, `vendor/`, `.DS_Store`, etc.).
- Releases are tagged `v<semver>`.
- **Husky hooks are mandatory** — `pre-commit` (lint-staged: php-cs-fixer auto-fix + PHPStan) and `pre-push` (CI: js:check + lint:php:all) run automatically. Do not push with `--no-verify` unless truly necessary.

## Tooling (Node.js)

- Node 24 (`.nvmrc`), npm scripts in `package.json`:
  - `npm run lint:php` — PHPStan only
  - `npm run lint:php:all` — PHPStan + php-cs-fixer check
  - `npm run lint:php:fix` — php-cs-fixer auto-fix
  - `npm run js:check` — `node --check admin/js/admin.js`
  - `npm run composer:install` — local Composer + vendor/ (no global Composer)
  - `npm run ci` — full gate (js:check + lint:php:all)
- PHPStan **level 6**, `phpstan.neon` + `phpstan-bootstrap.php` (WP stubs).
- PHP CS Fixer config: `.php-cs-fixer.dist.php` (WP-style: tabs, long arrays, single quotes, ordered imports).
