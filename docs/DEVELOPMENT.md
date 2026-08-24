# Development Guide

How to set up a local development environment, make changes, and test Beplus GitHub Deploy the way we do.

## 1. Prerequisites

- **Node.js 24** (see `.nvmrc`) — `nvm use` or install via [nvm](https://github.com/nvm-sh/nvm).
- **PHP 7.4+** on PATH (or set `PHP_BIN`).

```bash
npm install          # husky + lint-staged (+ installs git hooks via "prepare")
npm run composer:install   # downloads composer.phar locally + installs vendor/ (no global Composer needed)
```

## 2. The development loop

```bash
# 1. Edit source in the repo.
# 2. Lint PHP (all files):
npm run lint:php:all

# 3. Auto-fix style issues:
npm run lint:php:fix

# 4. JS syntax check:
npm run js:check

# 5. Sync to the running site (Docker example):
cp -r . /opt/wordpress/wp-content/plugins/beplus-github-deploy/
docker exec wp-app chown -R www-data:www-data /var/www/html/wp-content/plugins/beplus-github-deploy/
docker exec wp-app apache2ctl -k graceful

# 6. Open the admin page and test.
```

## 3. Testing checklist

| Area | What to verify |
|---|---|
| Token | Save → connected badge shows `@login`; Test Token works; Disconnect clears |
| Repo mode | Auto shows **Choose Repository**; Manual hides it |
| Add package | Auto-detect fills type/slug/subdir; manual entry accepted |
| Deploy (plugin) | Progress bar 2→100%, "Deploy complete!", backup created, files in `wp-content/plugins/<slug>` |
| Deploy (theme) | Same, into `wp-content/themes/<slug>` |
| **Type mismatch** | Deploy a theme while declared as plugin → **blocked** with clear popup, no files written |
| Rollback | Enabled only with backup; restores previous version; backup removed after |
| Backups | Restore specific / Download ZIP / Delete / Import (validates zip layout) |
| Webhook AUTO | Hook created on GitHub; push → auto-deploy; OFF → hook removed |
| Delete package | Entry removed, **files stay**, active theme untouched |

## 4. Testing the REST API

From the browser console (logged-in admin):

```js
// Deploy a package
await fetch(bmApi.restUrl + 'deploy', {
  method: 'POST',
  headers: { 'X-WP-Nonce': bmApi.nonce, 'Content-Type': 'application/json' },
  body: JSON.stringify({ slug: 'my-plugin' })
}).then(r => r.json());
```

From the terminal (Docker):

```bash
docker exec wp-app php -r '
  define("ABSPATH", "/var/www/html/");
  require "/var/www/html/wp-load.php";
  $s = get_option("beplus_manager_settings");
  var_export($s["packages"]);
'
```

## 5. Creating a release

1. Bump `Version:` in `beplus-github-deploy.php` **and** the `BEPLUS_MANAGER_VERSION` constant.
2. Run the testing checklist + `npm run ci`.
3. Tag the repo:

```bash
git add -A && git commit -m "Release v1.0.0"
git tag v1.0.0 && git push origin main --tags
```

4. Optional: `zip -r beplus-github-deploy.zip beplus-github-deploy/` (exclude `.git`, `node_modules`, `vendor` and `docs`) for manual uploads.

## 6. Quality gates (Husky)

Husky hooks run automatically:

| Hook | Runs |
|---|---|
| `pre-commit` | `lint-staged` (php-cs-fixer auto-fix on staged PHP + PHPStan) then `lint:php:all` |
| `pre-push` | `ensure:composer` then `ci` (js:check + lint:php:all) |

To bypass (emergency only): `git commit --no-verify` / `git push --no-verify`.

## 7. Troubleshooting

| Symptom | Cause / fix |
|---|---|
| "Invalid signature" from webhook | `webhook_secret` changed since the hook was created → re-save the token (or edit the package with AUTO on) so `ensure_webhook` updates the secret. |
| Webhook not created | Token lacks `admin:repo_hook` scope. |
| Restore fails "Could not copy" | File permissions — the live dir must be writable by the web user (`chown -R www-data:www-data wp-content/<type>s/<slug>`). |
| Deploy says "This repository is a theme, not a plugin" | The package type is wrong — edit the package and change its type. |
| GitHub API "404" on download | Wrong branch, private repo without token access, or wrong owner/repo. |
