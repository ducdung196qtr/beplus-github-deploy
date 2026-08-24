=== Beplus GitHub Deploy ===
Contributors: ducdung196qtr
Tags: github, deploy, backup, rollback, webhook
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Deploy plugins and themes straight from GitHub — public or private — with one-click backups, rollback, and webhook auto-deploy.

== Description ==

Beplus GitHub Deploy lets you deploy WordPress plugins and themes straight from any GitHub repository. It works with both public and private repos and handles the busy work for you:

* **Deploy from GitHub** — pull a plugin or theme straight from a GitHub repo (public or private) with one click.
* **Type detection** — automatically detects whether a repo is a theme or a plugin and refuses to install into the wrong location.
* **One-click backups** — every deploy snapshots the current version first, so you can always roll back.
* **Rollback** — restore any previous backup in the admin, no file uploads needed.
* **Import backups** — bring an exported backup back from a ZIP file.
* **Webhook auto-deploy** — optional GitHub webhook redeploys your repo automatically on every push.
* **Per-user GitHub tokens** — connect your own account; you only see the packages you own.
* **Private & public repos** — works with both classic PAT tokens (repo scope) and public repos with no token.

= Use cases =

* Agency workflows — keep client plugin/theme repos deployable from GitHub.
* DevOps automation — wire GitHub → WordPress deploy.
* Testing — deploy a feature branch to a staging site, roll back instantly.

== Installation ==

1. Upload the `beplus-github-deploy` folder to `/wp-content/plugins/`, or install the ZIP via *Plugins → Add New → Upload Plugin*.
2. Activate the plugin through the *Plugins* menu.
3. Go to **Beplus GitHub Deploy** in the admin menu.
4. In **GitHub Connection**, add a GitHub personal access token (classic, `repo` scope) or use repo-mode *Manual* for public repos.
5. Add a package, choose its repository and branch, then **Deploy**.

== Frequently Asked Questions ==

= Do I need a GitHub token? =

Not for public repositories — you can enter the repo manually. A token is required for private repos and for the webhook auto-deploy feature.

= What token permissions are needed? =

A classic PAT with the `repo` scope for repository access. Enable webhook auto-deploy with the `admin:repo_hook` scope.

= What happens when I deploy? =

Beplus GitHub Deploy downloads the repo branch, detects whether it is a plugin or a theme, backs up the current version, and installs the new files in the correct location.

= How do I roll back? =

Open the **Backups** card, pick the backup you want, and click **Restore**. No file upload needed.

== Changelog ==

= 1.0.0 =
* Initial release.