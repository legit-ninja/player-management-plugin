# InterSoccer / Underdog Plugin Build, Publish, and Auto-Update Pipeline

**Investigation Date:** 2026-09-19  
**Status:** DESIGN DOC ONLY — No production Soft-OK merge until Jeremy greenlights

## Executive Summary

The InterSoccer plugin ecosystem currently has a partial publish pipeline in place for `player-management-plugin` only. Four additional plugins lack CI-driven publish workflows and rely on manual `deploy.sh` scripts. The client-side auto-update mechanism exists via a shared `intersoccer-updates` plugin (not visible in `legit-ninja` org), but not all plugins are wired to use it. A dev-mode override for `dev.underdogunlimited.com` does not yet exist.

### P0 Gaps (Blockers)

1. **Four plugins missing `publish-plugin.yml`** — cannot CI-publish to Underdog host
2. **No dev-mode URL override** — cannot direct update checks to `dev.underdogunlimited.com`
3. **`intersoccer-updates` plugin location unclear** — shared updater not in visible repos

### P1 Gaps (Should-fix)

1. **Inconsistent `Update URI` headers** — `reports-rosters`, `intersoccer-product-variations` lack the header
2. **No reusable workflow** — `publish-plugin.yml` is copy-pasted, not centralized
3. **No update-check endpoint contract documented** — API assumed but not spec'd

---

## 1. Current State Map

### 1.1 Repository Inventory

| Repository | Default Branch | Has `publish-plugin.yml` | Has `deploy.sh` | Has `Update URI` | Version |
|------------|---------------|-------------------------|-----------------|------------------|---------|
| `player-management-plugin` | `main` | ✅ | ✅ | ✅ `https://plugins.underdogunlimited.com` | 2.7.30 |
| `customer-referral-system` | `main` | ❌ | ✅ | ❌ | 1.9.20 |
| `intersoccer-product-variations` | `master` | ❌ | ❌ (implicit rsync in docs) | ❌ | 2.9.2 |
| `reports-rosters` | `master` | ❌ | ❌ (`scripts/create-zip.sh` manual) | ❌ | 2.8.21 |
| `intersoccer-player-birthdays` | `main` | ❌ | ❌ | ✅ `https://plugins.underdogunlimited.com` | 1.8.32 |

### 1.2 Shared CI Infrastructure

- **`legit-ninja/intersoccer-ci`** provides reusable workflow `php-plugin-ci.yml` for lint + PHPUnit
- All five plugins call this via `uses: legit-ninja/intersoccer-ci/.github/workflows/php-plugin-ci.yml@main`
- **No** reusable publish workflow exists

### 1.3 Publish API Endpoint

From `player-management-plugin/.github/workflows/publish-plugin.yml`:

```
POST ${UU_PUBLISH_URL}/api/plugins/v1/${SLUG}/publish
Authorization: Bearer ${UU_PUBLISH_TOKEN}
Content-Type: multipart/form-data
  - version: string
  - changelog: text file
  - file: ZIP (application/zip)
```

**Base URL (prod):** `https://plugins.underdogunlimited.com`  
**Base URL (staging):** Same host, via GitHub Environment `staging`

### 1.4 GitHub Secrets/Variables (player-management-plugin)

| Name | Scope | Purpose |
|------|-------|---------|
| `UU_PUBLISH_TOKEN` | Environment secret (production, staging) | Bearer token for `/api/plugins/v1/…/publish` (write-only, never `udpl_…` tokens) |
| `UU_PLUGIN_SLUG` | Repository variable | Plugin slug, e.g. `player-management` |
| `UU_PUBLISH_URL` | Environment variable | `https://plugins.underdogunlimited.com` |

### 1.5 ZIP Build Excludes (aligned with deploy.sh)

```
.git, .github, .gitignore, dist, node_modules, vendor, tests, cypress, docs,
coverage, .phpunit.result.cache, .taskfiles, wp-admin, composer.json,
composer.lock, package.json, package-lock.json, phpunit.xml, Taskfile.yaml,
*.log, debug.log, *.sh, *.md (except README.md), *.list, run-*.php, .DS_Store, *.swp, *~
```

### 1.6 WordPress Updater (Client-Side)

**Shared plugin:** `intersoccer-updates` (not in `legit-ninja` org — location unconfirmed)

**Per-plugin wiring:**
- `Update URI: https://plugins.underdogunlimited.com` in plugin header
- Site token option: `intersoccer_uu_site_token` (prefix `udpl_…`)
- Update channel option: `intersoccer_uu_update_channel` (`release`, `prerelease`, `dev`)
- Override constant: `INTERSOCCER_UU_SITE_TOKEN` in `wp-config.php`

**Current coverage:**
- `player-management-plugin`: Full wiring (Update URI + settings UI)
- `intersoccer-player-birthdays`: Has `Update URI` header, no settings UI
- Others: Missing `Update URI` header entirely

---

## 2. Gap Analysis

### 2.1 P0: Critical Blockers

| Gap | Impact | Affected Plugins |
|-----|--------|-----------------|
| **No `publish-plugin.yml`** | Cannot CI-publish ZIPs to Underdog on release | customer-referral-system, intersoccer-product-variations, reports-rosters, intersoccer-player-birthdays |
| **No dev-mode URL override** | Cannot test updates against `dev.underdogunlimited.com` or staging host | All |
| **`intersoccer-updates` not visible** | Cannot audit or extend shared updater | All |

### 2.2 P1: Should-Fix

| Gap | Impact | Affected Plugins |
|-----|--------|-----------------|
| **Missing `Update URI` header** | WordPress won't offer updates even if published | customer-referral-system, intersoccer-product-variations, reports-rosters |
| **No reusable publish workflow** | Copy-paste maintenance burden | All future plugins |
| **Update-check endpoint not spec'd** | No contract for version check / download URL responses | — |
| **Staging vs. `dev.underdogunlimited.com` naming** | GitHub Environment is `staging`; Jeremy wants `dev.*` subdomain | Naming alignment |

### 2.3 Soft-Glance (Future)

| Area | Note |
|------|------|
| Paid-support gate | Jeremy wants paid support + auto-updates; no entitlement check in current flow |
| Changelog rendering | Markdown changelog passed to API; rendering/storage on host side unknown |
| Versioned folder installs | `intersoccer-updates` installs into `plugin-name-{version}/`; symlink/activation switching unaudited |

---

## 3. Recommended Soft-OK Tips

### Tip A: Reusable Publish Workflow (P0)

**Create** `legit-ninja/intersoccer-ci/.github/workflows/publish-plugin.yml` as a **reusable workflow**.

```yaml
# .github/workflows/publish-plugin.yml (in intersoccer-ci repo)
name: Publish Plugin to Underdog

on:
  workflow_call:
    inputs:
      plugin_slug:
        required: true
        type: string
      main_php_file:
        required: true
        type: string
        description: "e.g. player-management.php"
    secrets:
      UU_PUBLISH_TOKEN:
        required: true

jobs:
  publish:
    runs-on: ubuntu-latest
    environment: ${{ github.event_name == 'workflow_dispatch' && 'staging' || (github.event.release.prerelease && 'staging' || 'production') }}
    steps:
      # ... (same build + POST logic as current player-management workflow)
```

**Then** each plugin's `publish-plugin.yml` becomes:

```yaml
name: Publish plugin to Underdog
on:
  release:
    types: [published]
  workflow_dispatch:
    inputs:
      version:
        required: true
        type: string

jobs:
  publish:
    uses: legit-ninja/intersoccer-ci/.github/workflows/publish-plugin.yml@main
    with:
      plugin_slug: customer-referral-system
      main_php_file: customer-referral-system.php
    secrets:
      UU_PUBLISH_TOKEN: ${{ secrets.UU_PUBLISH_TOKEN }}
```

**Deliverables:**
1. Add `publish-plugin.yml` reusable workflow to `intersoccer-ci`
2. Add caller workflows to all 4 missing plugins
3. Create GitHub Environments (`production`, `staging`) + secrets on each repo

---

### Tip B: First-Party Lightweight Updater Class (P0/P1)

If `intersoccer-updates` shared plugin is unavailable or needs replacement, implement a minimal updater **in each plugin** (or as a Composer package `legit-ninja/intersoccer-updater-core`).

**Key hooks:**
- `pre_set_site_transient_update_plugins` — inject update info
- `plugins_api` — return plugin info on modal request

**Skeleton:**

```php
<?php
/**
 * InterSoccer_Plugin_Updater
 * Lightweight first-party updater for plugins.underdogunlimited.com
 */
class InterSoccer_Plugin_Updater {
    private string $plugin_file;
    private string $slug;
    private string $version;
    private string $update_uri;
    
    public function __construct(string $plugin_file, string $slug, string $version) {
        $this->plugin_file = $plugin_file;
        $this->slug = $slug;
        $this->version = $version;
        $this->update_uri = $this->get_update_base_url();
        
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_update']);
        add_filter('plugins_api', [$this, 'plugin_info'], 10, 3);
    }
    
    private function get_update_base_url(): string {
        // Dev mode override
        if (defined('INTERSOCCER_DEV_MODE') && INTERSOCCER_DEV_MODE) {
            return 'https://dev.underdogunlimited.com';
        }
        $option = get_option('intersoccer_uu_dev_mode', false);
        if ($option) {
            return 'https://dev.underdogunlimited.com';
        }
        return 'https://plugins.underdogunlimited.com';
    }
    
    public function check_update($transient) {
        // GET /api/plugins/v1/{slug}?channel={release|prerelease|dev}
        // Compare remote version to $this->version
        // If newer, add to $transient->response
    }
    
    public function plugin_info($result, $action, $args) {
        // Return plugin details for WP modal
    }
}
```

**Initialization (in plugin bootstrap):**

```php
add_action('plugins_loaded', function() {
    if (!class_exists('InterSoccer_Plugin_Updater')) {
        require_once __DIR__ . '/includes/class-plugin-updater.php';
    }
    new InterSoccer_Plugin_Updater(
        __FILE__,
        'customer-referral-system',
        INTERSOCCER_REFERRAL_VERSION
    );
}, 5);
```

**Deliverables:**
1. Audit existing `intersoccer-updates` implementation (request access or create fresh)
2. Extract to `legit-ninja/intersoccer-updater-core` Composer package or inline class
3. Wire into each plugin

---

### Tip C: Update-Check API Endpoint Contract (P0)

**Proposed endpoint (read):**

```
GET /api/plugins/v1/{slug}
Authorization: Bearer {site_token}  # udpl_… prefix
Query params:
  - channel: release | prerelease | dev (default: release)

Response (200 OK):
{
  "slug": "player-management",
  "version": "2.7.30",
  "download_url": "https://plugins.underdogunlimited.com/downloads/player-management/2.7.30/player-management-2.7.30.zip",
  "changelog": "## 2.7.30\n- Bug fixes\n- ...",
  "requires_php": "7.4",
  "tested_up_to": "6.6",
  "last_updated": "2026-09-19T12:00:00Z"
}
```

**If not authorized or no valid entitlement:**
```
HTTP 401 Unauthorized
{
  "error": "invalid_token",
  "message": "Site token is invalid or expired"
}
```

**If version check passes (site token valid), but no newer version:**
```
HTTP 200 OK
{
  "slug": "player-management",
  "version": "2.7.30",
  "current": true,
  "message": "You are running the latest version"
}
```

**Deliverables:**
1. Confirm this contract with Underdog host (Forge/Lock)
2. Document in `docs/api/update-check.md`

---

### Tip D: Dev-Mode URL Override (P0)

**Option 1: Constant in `wp-config.php`**
```php
define('INTERSOCCER_DEV_MODE', true);
// or
define('INTERSOCCER_UU_UPDATE_URL', 'https://dev.underdogunlimited.com');
```

**Option 2: Site option (via Players → Settings)**
```php
add_option('intersoccer_uu_dev_mode', false);
```

**Priority:**
1. `INTERSOCCER_UU_UPDATE_URL` constant (exact URL)
2. `INTERSOCCER_DEV_MODE` constant → `https://dev.underdogunlimited.com`
3. `intersoccer_uu_dev_mode` option → `https://dev.underdogunlimited.com`
4. Default → `https://plugins.underdogunlimited.com`

**Naming note:** Jeremy mentioned `dev.underdogunlimited.com`. GitHub Environment is named `staging`. Recommend:
- Keep GitHub Environment as `staging` (controls prerelease publish)
- Add `dev.underdogunlimited.com` as the client-side dev host
- Document the difference: `staging` = CI publish destination; `dev.*` = WP site update source

**Deliverables:**
1. Add dev-mode constant/option support to updater class
2. Optionally add UI toggle in Players → Settings → General
3. Verify `dev.underdogunlimited.com` DNS/hosting exists (ask Jeremy/Forge)

---

## 4. Soft-Glance Hooks

| Area | Owner | Notes |
|------|-------|-------|
| GitHub Actions | Pip | Reusable workflow in `intersoccer-ci`, caller workflows in each plugin |
| Hosting/API | Forge | `plugins.underdogunlimited.com` already serves publish API; add/confirm update-check endpoint |
| Auth/Tokens | Lock | `UU_PUBLISH_TOKEN` (CI write), `udpl_…` site tokens (read); paid-support entitlement check if required |
| Plugin code | Jeremy | `Update URI` headers, updater class, dev-mode override |

---

## 5. Implementation Sequence

| Phase | Tip | Effort | Dependencies |
|-------|-----|--------|--------------|
| **Phase 1** | Tip A (reusable publish workflow) | Low | intersoccer-ci push access |
| **Phase 1** | Add `Update URI` to 3 missing plugins | Trivial | — |
| **Phase 2** | Tip C (confirm update-check API contract) | Requires Forge/host confirm | — |
| **Phase 2** | Tip D (dev-mode override) | Low | — |
| **Phase 3** | Tip B (first-party updater class) | Medium | Requires audit of existing `intersoccer-updates` or fresh build |
| **Future** | Paid-support entitlement gate | TBD | Business decision + host-side logic |

---

## 6. Open Questions for Jeremy

1. **Where is `intersoccer-updates` source?** Is it in a private repo, or vendored into WP installs?
2. **Is `dev.underdogunlimited.com` already provisioned?** Or should we use `staging.underdogunlimited.com`?
3. **Paid-support gate:** Is there a requirement for entitlement verification before serving update ZIPs?
4. **Calendar versioning:** Current plugins use `MAJOR.MINOR.PATCH(.N)?(-prerelease)?`. Any change planned?
5. **Priority plugins:** Should all 5 plugins get publish workflows, or start with a subset?

---

## Appendix A: Publish Workflow (player-management-plugin reference)

```yaml
# .github/workflows/publish-plugin.yml (current)
name: Publish plugin to Underdog
on:
  release:
    types: [published]
  workflow_dispatch:
    inputs:
      version:
        required: true
        type: string

permissions:
  contents: read

jobs:
  publish:
    runs-on: ubuntu-latest
    environment: ${{ ... }}
    steps:
      - uses: actions/checkout@v4
      - name: Guard production publishes
        ...
      - name: Resolve version
        ...
      - name: Build plugin ZIP
        run: |
          rsync -a --exclude='.git' ... ./ "dist/${SLUG}/"
          zip -r "${SLUG}-${VERSION}.zip" "$SLUG"
      - name: Publish to Underdog Unlimited
        run: |
          curl -fsS -X POST "${PUBLISH_BASE}/api/plugins/v1/${SLUG}/publish" \
            -H "Authorization: Bearer ${UU_PUBLISH_TOKEN}" \
            -F "version=${VERSION}" \
            -F "changelog=</tmp/changelog.txt" \
            -F "file=@${ZIP};type=application/zip"
```

---

## Appendix B: Plugin Header Comparison

**player-management.php (has Update URI):**
```php
/**
 * Plugin Name: Player Management
 * Version: 2.7.30
 * Update URI: https://plugins.underdogunlimited.com
 */
```

**customer-referral-system.php (MISSING Update URI):**
```php
/**
 * Plugin Name: InterSoccer Referral System
 * Version: 1.9.20
 */
```

**intersoccer-player-birthdays.php (has Update URI):**
```php
/**
 * Plugin Name: InterSoccer Player Birthdays
 * Version: 1.8.32
 * Update URI: https://plugins.underdogunlimited.com
 */
```

---

## Appendix C: GitHub Environments Audit

| Repository | Environments Present | Secrets Configured |
|------------|---------------------|-------------------|
| player-management-plugin | staging | Unknown (API 403) |
| customer-referral-system | none | — |
| intersoccer-product-variations | unknown | — |
| reports-rosters | unknown | — |
| intersoccer-player-birthdays | unknown | — |

**Action:** Create `production` and `staging` environments with `UU_PUBLISH_TOKEN` secret on all 4 missing repos.
