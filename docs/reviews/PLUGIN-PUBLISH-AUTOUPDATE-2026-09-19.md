# InterSoccer / Underdog Plugin Build, Publish, and Auto-Update Pipeline

**Investigation Date:** 2026-09-19  
**Status:** SOFT-OK LOCKED (Jeremy 2026-09-19)

---

## Jeremy Soft-OK Decisions (2026-09-19)

| Decision | Status | Notes |
|----------|--------|-------|
| **Host model** | ✅ KEEP | Single origin `https://plugins.underdogunlimited.com` with channel query param (`release` / `prerelease` / `dev`). Do NOT add `dev.underdogunlimited.com` as plugin update host (that domain is for Next site deploy). |
| **Tip A: Reusable publish workflow** | ✅ Soft-OK | Create in `intersoccer-ci`, wire caller workflows to all plugins |
| **Tip B: First-party updater** | ✅ Soft-OK | Publish existing `intersoccer-updates` to `legit-ninja` org |
| **Tip C: Update URI headers** | ✅ Soft-OK | Add to 3 missing plugins |
| **Tip D: Wire secrets/environments** | ✅ Soft-OK | Create GitHub Environments + secrets on all repos |
| **Third-party update managers** | ❌ Soft-NO | No Freemius, EDD Software Licensing, or similar |
| **Updater-shape widget** | ⏭️ Skipped | First-party `intersoccer-updates` already exists locally |

---

## Executive Summary

The InterSoccer plugin ecosystem currently has a partial publish pipeline in place for `player-management-plugin` only. Four additional plugins lack CI-driven publish workflows and rely on manual `deploy.sh` scripts. The client-side auto-update mechanism exists via a shared `intersoccer-updates` plugin (to be published to `legit-ninja` org). Update channel selection (`release` / `prerelease` / `dev`) is handled via query parameter to the single host `https://plugins.underdogunlimited.com`.

### P0 Gaps (Blockers) — ADDRESSED BY SOFT-OK

1. **Four plugins missing `publish-plugin.yml`** — Tip A (reusable workflow)
2. **`intersoccer-updates` plugin not in legit-ninja org** — Tip B (publish to org)
3. **Missing `Update URI` headers** — Tip C (add headers)

### P1 Gaps (Should-fix) — ADDRESSED BY SOFT-OK

1. **No reusable workflow** — Tip A
2. **Missing GitHub Environments/secrets** — Tip D

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
- **No** reusable publish workflow exists (Tip A addresses this)

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

**Base URL:** `https://plugins.underdogunlimited.com` (single origin for all environments)

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

**Shared plugin:** `intersoccer-updates` (to be published to `legit-ninja` org per Soft-OK)

**Host model (LOCKED):**
- Single origin: `https://plugins.underdogunlimited.com`
- Channel selection via query param: `?channel=release` / `?channel=prerelease` / `?channel=dev`
- **NO** dual-origin with `dev.underdogunlimited.com` (that domain is for Next site deploy)

**Per-plugin wiring:**
- `Update URI: https://plugins.underdogunlimited.com` in plugin header
- Site token option: `intersoccer_uu_site_token` (prefix `udpl_…`)
- Update channel option: `intersoccer_uu_update_channel` (`release`, `dev`)
- Per-plugin beta: `intersoccer_uu_beta_slugs` (via InterSoccer Updates helpers)
- Override constant: `INTERSOCCER_UU_SITE_TOKEN` in `wp-config.php`

**Current coverage:**
- `player-management-plugin`: Full wiring (Update URI + settings UI)
- `intersoccer-player-birthdays`: Has `Update URI` header, no settings UI
- Others: Missing `Update URI` header entirely (Tip C addresses this)

---

## 2. Soft-OK Tips (Implementation Ready)

### Tip A: Reusable Publish Workflow ✅ Soft-OK

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

### Tip B: Publish `intersoccer-updates` to legit-ninja ✅ Soft-OK

The first-party `intersoccer-updates` plugin already exists locally. Publish it to the `legit-ninja` org for visibility and maintenance.

**Deliverables:**
1. Create `legit-ninja/intersoccer-updates` repository
2. Push existing updater code
3. Document installation and configuration

**Third-party alternatives (Soft-NO):**
- ❌ Freemius
- ❌ EDD Software Licensing
- ❌ Any other third-party update-manager plugin

---

### Tip C: Add `Update URI` Headers ✅ Soft-OK

Add the following header to plugin bootstrap files:

```php
* Update URI: https://plugins.underdogunlimited.com
```

**Affected plugins:**
- `customer-referral-system/customer-referral-system.php`
- `intersoccer-product-variations/intersoccer-product-variations.php`
- `reports-rosters/intersoccer-reports-rosters.php`

---

### Tip D: Wire GitHub Environments and Secrets ✅ Soft-OK

Create GitHub Environments and configure secrets on all plugin repositories:

| Repository | Environments to Create | Secrets to Add |
|------------|----------------------|----------------|
| `customer-referral-system` | `production`, `staging` | `UU_PUBLISH_TOKEN` |
| `intersoccer-product-variations` | `production`, `staging` | `UU_PUBLISH_TOKEN` |
| `reports-rosters` | `production`, `staging` | `UU_PUBLISH_TOKEN` |
| `intersoccer-player-birthdays` | `production`, `staging` | `UU_PUBLISH_TOKEN` |

**Repository variables to add:**
- `UU_PLUGIN_SLUG`: Plugin slug (e.g., `customer-referral-system`)
- `UU_PUBLISH_URL`: `https://plugins.underdogunlimited.com` (same for all environments)

---

## 3. Update-Check API Endpoint Contract

**Endpoint (read):**

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

**Channel selection (LOCKED model):**
- `?channel=release` — stable releases (default)
- `?channel=prerelease` — beta/RC builds
- `?channel=dev` — development tip

---

## 4. Implementation Sequence

| Phase | Tip | Effort | Dependencies |
|-------|-----|--------|--------------|
| **Phase 1** | Tip A (reusable publish workflow) | Low | intersoccer-ci push access |
| **Phase 1** | Tip C (add `Update URI` headers) | Trivial | — |
| **Phase 1** | Tip D (wire GitHub Environments/secrets) | Low | Repo admin access |
| **Phase 2** | Tip B (publish `intersoccer-updates` to legit-ninja) | Medium | Access to existing updater source |

---

## 5. Soft-Glance Hooks

| Area | Owner | Notes |
|------|-------|-------|
| GitHub Actions | Pip | Reusable workflow in `intersoccer-ci`, caller workflows in each plugin |
| Hosting/API | Forge | `plugins.underdogunlimited.com` serves publish + update-check API |
| Auth/Tokens | Lock | `UU_PUBLISH_TOKEN` (CI write), `udpl_…` site tokens (read) |
| Plugin code | Jeremy | `Update URI` headers, `intersoccer-updates` publish |

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

**customer-referral-system.php (MISSING Update URI — Tip C):**
```php
/**
 * Plugin Name: InterSoccer Referral System
 * Version: 1.9.20
 * Update URI: https://plugins.underdogunlimited.com  // ← ADD THIS
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

| Repository | Environments Present | Action Required |
|------------|---------------------|-----------------|
| player-management-plugin | staging | Add `production` environment |
| customer-referral-system | none | Add `production`, `staging` |
| intersoccer-product-variations | unknown | Add `production`, `staging` |
| reports-rosters | unknown | Add `production`, `staging` |
| intersoccer-player-birthdays | unknown | Add `production`, `staging` |

**All repos:** Add `UU_PUBLISH_TOKEN` secret to both environments.
