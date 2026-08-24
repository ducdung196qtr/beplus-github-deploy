# Architecture

## Overview

Beplus GitHub Deploy is a standard WordPress plugin bootstrapped from a single entry file. It uses a lightweight **PSR-4-style autoloader** (no Composer runtime dependency) and a small **service container** that wires modules together. The admin UI is a single-page vanilla JS app talking to the WP REST API.

```
plugins/beplus-github-deploy/
├── beplus-github-deploy.php        # Entry point: constants, autoloader, hooks
├── includes/
│   └── helpers.php                 # Global helper functions
├── admin/
│   ├── css/admin.css               # Admin stylesheet (Dashicons-friendly)
│   └── js/admin.js                 # Admin single-page app (vanilla JS)
├── src/                            # PSR-4: BeplusManager\<Namespace>\<Class>
│   ├── Core/
│   │   └── Plugin.php              # Service container + boot + admin wiring
│   ├── Storage/
│   │   └── PackageRepository.php   # Packages + settings persistence
│   ├── GitHub/
│   │   └── GitHubClient.php        # GitHub REST API client
│   ├── Deploy/
│   │   └── Deployer.php            # Deploy pipeline + progress + zip install
│   ├── Backup/
│   │   └── BackupManager.php       # Backup/restore/rollback + file utils
│   └── REST/
│       ├── SettingsController.php  # Admin REST endpoints
│       └── WebhookController.php   # GitHub webhook receiver
└── README.md, docs/                # Documentation
```

## Namespaces & responsibilities

| Namespace | Responsibility |
|---|---|
| `BeplusManager\Core` | Bootstrap, service container, admin page & asset enqueueing |
| `BeplusManager\Storage` | Persist settings + packages in a single WP option |
| `BeplusManager\GitHub` | All GitHub API calls (auth, repos, trees, webhooks, zipball) |
| `BeplusManager\Deploy` | Deploy pipeline, progress reporting, zip extraction |
| `BeplusManager\Backup` | Snapshot / restore / rollback / delete / import / download |
| `BeplusManager\REST` | REST route registration + request handling |

## Lifecycle

```
wp-load.php
  └─ plugins_loaded → beplus_manager_boot()
       └─ Plugin::instance()->boot()
            ├─ register_core_services()   # packages, github, backup, deploy
            ├─ admin_menu                 # menu page (dashicons-update)
            ├─ admin_enqueue_scripts      # css + js + bmApi localize
            └─ rest_api_init              # SettingsController + WebhookController
```

## Service container

`Plugin::instance()->get('deploy')` etc. — services are created once in `register_core_services()` and injected through constructors (constructor injection, no service locator in domain code).

```php
$this->services['deploy'] = new Deployer(
    $this->services['github'],
    $this->services['backup']
);
```

## Data model

Everything is stored in **one WP option**: `beplus_manager_settings`.

```php
[
  'github_token'     => 'ghp_...',          // stored, never exposed by the API
  'github_client_id' => '',
  'webhook_secret'   => 'random-32-chars',
  'repo_mode'        => 'auto'|'manual',    // how repositories are chosen
  'packages'         => [
      'my-plugin' => [
          'type'       => 'plugin'|'theme',
          'slug'       => 'my-plugin',
          'repository' => 'owner/repo',
          'branch'     => 'main',
          'subdirectory'=> '',              // optional sub-path inside the repo
          'webhook'    => true|false,       // auto-deploy enabled?
          'github_login'=> 'owner',         // whose token was used to add it
          'updated_at' => '2026-08-18 10:00:00',
      ],
  ],
]
```

The activity log lives in a separate option `beplus_manager_log` (last 100 entries).

## Deploy pipeline (Deployer::deploy)

1. **Backup** — if the target directory exists, snapshot it (rolling: previous backup for the same `type-slug` is replaced).
2. **Download** — `codeload.github.com/<repo>/zip/refs/heads/<branch>` with the stored token.
3. **Type check** — `GitHubClient::detect_package_info()` inspects the git tree:
   - `style.css` containing a `Theme Name:` header → `theme`
   - a root-level `.php` file containing `Plugin Name:` → `plugin`
   - mismatch with the declared type **aborts the deploy** with a clear message.
4. **Extract** — strip the `<owner>-<repo>-<sha>/` wrapper (and optional subdirectory), clean-install into `wp-content/<type>s/<slug>/`.
5. **Log & report** — progress transient (`beplus_manager_progress_<slug>`) drives the live progress bar.

## Backups

- Location: `wp-content/beplus-manager-backups/`
- Naming: `(plugin|theme)-<slug>-<YYYYmmddHHMMSS>` (14-digit timestamp).
- Rollback restores the latest backup then removes it; restoring a specific backup snapshots the live version first and keeps the chosen backup.

## Webhook flow

```
GitHub push ──► POST /wp-json/beplus-github-deploy/v1/webhook
                  │  X-Hub-Signature-256: sha256=HMAC(body, secret)
                  ▼
            WebhookController::handle
                  │  hash_equals() verify
                  │  find package matching repository + branch + webhook=true
                  ▼
            Deployer::deploy(package)  (async-safe, same pipeline as manual)
```

The sender side (`GitHubClient::ensure_webhook`) creates/updates the hook on the repo when AUTO is toggled on, and `delete_webhook` removes it when toggled off.

## Security model

- REST admin endpoints: `current_user_can('manage_options')` + WP nonce.
- Token is stored server-side only; the API returns `token: 'set'` (never the value).
- Webhook receiver: HMAC-SHA256 verified with `hash_equals()` (constant-time).
- Backup import: `ZipArchive` extraction with strict root-folder validation (no path traversal).
- All filenames sanitized (`sanitize_file_name`), all repo values sanitized (`sanitize_text_field`).
