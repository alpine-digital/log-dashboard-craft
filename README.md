# Log Dashboard

In-project log viewer for **local development**, built as a Craft CMS plugin.
Install it into a Craft project and open its control panel section to browse
that project's own `storage/logs` — no SSH, no separate app. It runs inside
the host project and reads the log files straight off the local disk.

## Requirements

- PHP 8.2+
- Craft CMS 5
- A logged-in control panel user with the "Access the log dashboard" permission
- Admin changes allowed in the environment (see **Security**)
- The project runs in DDEV (the examples use `ddev`; plain Composer works too)

## Install (private GitLab repo)

This is a **private** repo, so Composer needs access to GitLab — and because the
app runs in DDEV, the **container** needs that access, not just your host.

**1. Add the repository** as a **git** repository:

```bash
ddev composer config repositories.log-dashboard-craft git git@gitlab.com:alpinedigital/log-dashboard-craft.git
```

> Note the type is **`git`**, not `vcs`. With `vcs` on a `gitlab.com` URL Composer
> uses its GitLab driver, which calls the GitLab API (`api/v4/...`) first and
> returns `404` on a private repo — even though your SSH key works. `type: git`
> uses the plain git driver: it clones over SSH, no API and no token involved.
> (`no-api: true` does *not* help here — that option only applies to GitHub.)

<details>
<summary>Or add it by hand in <code>composer.json</code></summary>

Same result, next to the other top-level keys:

```json
"repositories": [
    {
        "type": "git",
        "url": "git@gitlab.com:alpinedigital/log-dashboard-craft.git"
    }
]
```

Use this if you prefer editing the file — but the command above is easier and
avoids the JSON-quoting issues `ddev composer config --json …` hits on
Windows/PowerShell.

</details>

**2. Give the container your SSH key, install, and enable the plugin:**

```bash
ddev auth ssh
ddev composer require --dev alpinedigital/log-dashboard-craft:@dev
ddev craft plugin/install log-dashboard
```

`ddev auth ssh` forwards your host SSH agent into the container (once per `ddev`
session). You need an SSH key registered on your GitLab account — verify with
`ssh -T git@gitlab.com` on the host. Update later with
`ddev composer update alpinedigital/log-dashboard-craft`.

<details>
<summary>Alternative: HTTPS + deploy token (no SSH)</summary>

1. In GitLab: **Settings → Repository → Deploy tokens**, create one with
   `read_repository` scope.
2. Hand the token to Composer **inside the container**, then install:

   ```bash
   ddev composer config --global gitlab-token.gitlab.com <token-username> <token>
   ddev composer config repositories.log-dashboard-craft vcs https://gitlab.com/alpinedigital/log-dashboard-craft.git
   ddev composer require --dev alpinedigital/log-dashboard-craft:@dev
   ddev craft plugin/install log-dashboard
   ```

</details>

**3. Grant the permission.** In the control panel, go to a user's or user
group's permissions and check **"Access the log dashboard"** under the
**Log Dashboard** heading (admins have it implicitly).

## Usage

With the project served locally and logged into the control panel, open:

```
https://<project>.ddev.site/admin/log-dashboard
```

(Replace `admin` with your project's `cpTrigger` if you've customized it.) You
land on the log-file list; click a file to view its entries (level filter,
search, live refresh). Or open it anytime with:

```bash
ddev launch admin/log-dashboard
```

### Auto-open on `ddev start` (optional)

Copy the bundled stub into your project's `.ddev/` to open the dashboard in a
browser tab automatically after every `ddev start`:

```bash
cp vendor/alpinedigital/log-dashboard-craft/stubs/config.logdashboard.yaml .ddev/
ddev restart
```

DDEV merges every `.ddev/config.*.yaml`, so this only adds a `post-start` hook.

## Configuration

Copy `vendor/alpinedigital/log-dashboard-craft/config/log-dashboard.php` to your
project's `config/log-dashboard.php` to override defaults:

| Key       | Default                                          | Env                     |
| --------- | ------------------------------------------------- | ----------------------- |
| `enabled` | follows Craft's `devMode` general config setting  | `LOG_DASHBOARD_ENABLED` |
| `path`    | Craft's own `storage/logs` directory              | `LOG_DASHBOARD_PATH`    |

Point `path` elsewhere via `LOG_DASHBOARD_PATH`.

## Security

The dashboard exposes log contents through the control panel, so:

- Install it as `--dev` only.
- Access requires being logged in **and** holding the plugin's "Access the log
  dashboard" permission — grant it only to trusted developers.
- The plugin's control panel section and routes mount only when `enabled`
  (which follows `devMode` by default), and the plugin **hard-blocks whenever
  `allowAdminChanges` is disabled** — the standard Craft signal that an
  environment is locked down — regardless of the `enabled` setting.

**Never enable this on a shared production environment.**

## Notes

- The compiled dashboard UI ships pre-built under `resources/dist`. Its Angular
  source lives in the main Log-dashboard project; this repo carries the built
  assets so consumers never run a frontend build.
- The log parser auto-detects both Laravel's bracketed log format and Craft's
  own Monolog line format (`%datetime% [%channel%.%level%] [%category%]
  %message%`), so it reads Craft's `storage/logs/*.log` files out of the box.
