# Development Guide

How to set up a local development environment, make changes, and test Beplus Manager the way we do.

## 1. Local environment

The simplest setup is a local WordPress install (or a Docker container like the one we use):

```
wp-app  →  /var/www/html  (Apache + PHP 7.4+)
          ├── wp-content/plugins/beplus-manager   ← symlink or copy of this repo
          └── wp-content/themes/
```

Recommended: symlink the repo into `wp-content/plugins/` so edits are live:

```bash
ln -s /path/to/beplus-manager /var/www/html/wp-content/plugins/beplus-manager
```

## 2. The development loop

```bash
# 1. Edit source in the repo.
# 2. Lint PHP (all files):
find src includes -name '*.php' -exec php -l {} \;

# 3. Lint JS:
node --check admin/js/admin.js

# 4. Sync to the running site (Docker example):
cp -r . /opt/wordpress/wp-content/plugins/beplus-manager/
docker exec wp-app chown -R www-data:www-data /var/www/html/wp-content/plugins/beplus-manager/
docker exec wp-app apache2ctl -k graceful

# 5. Open the admin page and test.
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

1. Bump `Version:` in `beplus-manager.php` **and** the `BEPLUS_MANAGER_VERSION` constant.
2. Run the testing checklist.
3. Tag the repo:

```bash
git add -A && git commit -m "Release v1.0.0"
git tag v1.0.0 && git push origin main --tags
```

4. Optional: `zip -r beplus-manager.zip beplus-manager/` (exclude `.git` and `docs`) for manual uploads.

## 6. Troubleshooting

| Symptom | Cause / fix |
|---|---|
| "Invalid signature" from webhook | `webhook_secret` changed since the hook was created → re-save the token (or edit the package with AUTO on) so `ensure_webhook` updates the secret. |
| Webhook not created | Token lacks `admin:repo_hook` scope. |
| Restore fails "Could not copy" | File permissions — the live dir must be writable by the web user (`chown -R www-data:www-data wp-content/<type>s/<slug>`). |
| Deploy says "This repository is a theme, not a plugin" | The package type is wrong — edit the package and change its type. |
| GitHub API "404" on download | Wrong branch, private repo without token access, or wrong owner/repo. |
