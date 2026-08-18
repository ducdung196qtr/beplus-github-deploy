# Beplus Manager

Deploy WordPress **plugins** and **themes** directly from GitHub repositories (public **or** private) — with automatic one-shot backups, one-click rollback, and GitHub webhook auto-deploy.

Built by [Beplus](https://beplusthemes.com). All code is original.

---

## Features

- 🔑 **GitHub token auth** — connect your personal GitHub account (classic PAT, `repo` scope).
- 📦 **Packages** — define a *package* = `type (plugin|theme)` + `slug` + `repository (owner/repo)` + `branch` + optional `subdirectory`.
- 🚀 **One-click Deploy** — downloads the GitHub zipball, strips the wrapper folder, installs into `wp-content/plugins/<slug>` or `wp-content/themes/<slug>`.
- 🛡️ **Type verification** — before installing, the repo is inspected (via the Git Trees API) to confirm it really is a plugin or theme. A mismatch blocks the deploy with a clear error instead of dumping a theme into `plugins/`.
- 💾 **Rolling backup** — every deploy snapshots the current live version first (one backup per package).
- ↩️ **Rollback** — restore the previous version with one click.
- 🗄️ **Backups manager** — view all backups, restore a specific one, download as `.zip`, delete, or import a backup from another site.
- ⚡ **Webhook auto-deploy** — toggle AUTO per package; the plugin creates/updates the GitHub webhook for you (`admin:repo_hook` scope required) and deploys on every push to the configured branch.
- 📜 **Activity log** — last 100 events with timestamps, clearable.
- 🖥️ **Clean admin UI** — vanilla HTML/CSS/JS, no build step, WP Dashicons.

---

## Requirements

| Requirement | Minimum |
|---|---|
| WordPress | 6.0 |
| PHP | 7.4 (8.x supported) |
| PHP extension | `zip` (ZipArchive) |
| GitHub token | Classic PAT with `repo` scope (`admin:repo_hook` for webhooks) |

---

## Installation

1. Download this repository as a ZIP, or `git clone https://github.com/ducdung196qtr/beplus-manager.git`.
2. In WordPress admin: **Plugins → Add New → Upload Plugin** — select the ZIP and activate.
3. (Manual) Copy the `beplus-manager` folder into `wp-content/plugins/` and activate from **Plugins**.

---

## Quick Start

1. Open **Beplus Manager** in the admin sidebar.
2. Click **Obtain a GitHub token** — GitHub opens the token creation page with the right scopes pre-selected.
3. Paste the token and click **Save GitHub token**. You're connected.
4. Under **Add Package**:
   - Pick **Auto** (recommended) and click **Choose Repository** to pick from your repos — type/slug/branch are filled automatically.
   - Or pick **Manual** and type the repository (`owner/repo`), slug and branch yourself.
5. Tick **Enable auto-deploy via webhook** if you want deploys on every push.
6. Click **Add Package** → your package appears in the list → **Deploy**.

---

## Usage

### Packages table

| Column | Description |
|---|---|
| Type | `THEME` or `PLUGIN` badge |
| Slug | Directory name in `wp-content/<type>s/` |
| Repository | `owner/repo` on GitHub |
| Branch | Branch tracked for this package |
| Auto | `AUTO` when the webhook is enabled, `MANUAL` otherwise |
| Actions | **Deploy** (download + install), **Rollback** (restore previous backup), **Edit**, **Delete** |

### Deploy

Click **Deploy** (confirm) — the plugin:
1. Snapshots the current live version (rolling backup).
2. Downloads the repository zipball for the configured branch.
3. **Verifies the detected type** (theme vs plugin) — mismatch aborts with a clear error.
4. Extracts into the target directory (clean install).
5. Reports "Deploy complete!" — the progress bar shows the whole process live.

### Rollback

Enabled only when a backup exists. Restores the last snapshot and **removes that backup** (the next deploy creates a fresh one).

### Backups

The **Backups** card lists every snapshot with per-row actions:
- **Restore** — restore that exact backup (a snapshot of the current live version is taken first, so you can always come back).
- **Download** — download the backup as a `.zip` (usable on another site via **Import Backup**).
- **Delete** — remove the backup.
- **Import Backup** — upload a backup ZIP from another site (validated: exactly one root folder named `(plugin|theme)-<slug>-<timestamp>`).

### Auto-deploy (webhooks)

- Toggling **AUTO** on a package creates (or updates) a GitHub webhook on the repository pointing to `wp-json/beplus-manager/v1/webhook` — push events only.
- Toggling it off deletes the webhook.
- Requires the token to have `admin:repo_hook` scope.
- The receiver verifies the `X-Hub-Signature-256` HMAC against your stored secret before deploying.

### Deleting a package

Deleting a package **only unlinks it** — the entry is removed from the plugin, but **the deployed files stay on the site**. No files are deleted, the active theme is never switched.

---

## REST API

All endpoints under `/wp-json/beplus-manager/v1/`, protected by `manage_options` + nonce (`X-WP-Nonce: wp_rest`).

| Method | Route | Description |
|---|---|---|
| GET | `/settings` | Settings + connected GitHub login + repo mode |
| POST | `/settings` | Save token / clear token / set repo mode |
| POST | `/github/test` | Validate a token |
| GET | `/github/repos` | List the authenticated user's repositories |
| GET | `/github/detect?repository=&branch=` | Detect type/slug/subdirectory of a repo |
| POST | `/github/device/start`, GET `/github/device/poll` | OAuth Device Flow (reserved) |
| POST | `/packages` | Create / update a package |
| DELETE | `/packages/{slug}` | Unlink a package (files stay) |
| POST | `/deploy` `{slug}` | Deploy now |
| POST | `/rollback` `{slug, type}` | Rollback to previous backup |
| GET | `/backups` | List backups |
| POST | `/backups/import` | Import a backup ZIP (multipart) |
| POST | `/backups/{name}/restore` | Restore a specific backup |
| GET | `/backups/{name}/download` | Download a backup ZIP |
| DELETE | `/backups/{name}` | Delete a backup |
| GET | `/logs`, DELETE `/logs` | Read / clear the activity log |
| POST | `/webhook` | GitHub webhook receiver (public, HMAC-verified) |

---

## Development

```bash
npm install                # husky + lint-staged (+ installs git hooks)
npm run composer:install   # local Composer + vendor/ (no global Composer needed)
npm run lint:php:all       # PHPStan level 6 + PHP CS Fixer check
npm run js:check           # JS syntax check
```

Husky hooks run `lint-staged` (php-cs-fixer auto-fix + PHPStan) on commit and the full CI gate on push.

See:
- [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) — structure & architecture
- [docs/DEVELOPMENT.md](docs/DEVELOPMENT.md) — dev environment & workflow
- [docs/RULES.md](docs/RULES.md) — coding rules & conventions

---

## License

GPL-2.0-or-later — see [LICENSE](LICENSE).
