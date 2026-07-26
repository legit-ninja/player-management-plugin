# Underdog plugin publish & updates

Publish Player Management ZIPs to the Underdog Unlimited plugin host. WordPress admin updates are handled by the shared **InterSoccer Updates** plugin (`intersoccer-updates`), not by this repo.

## Tag conventions

| Intent | Git tag | Version sent to API | GitHub Environment |
|--------|---------|---------------------|--------------------|
| Customer release | `v2.7.26` | `2.7.26` | `production` |
| Staging / RC | `v2.7.26.0-rc1` | `2.7.26.0-rc1` | `staging` |
| Dev smoke | `v2.7.26-dev.1` | `2.7.26-dev.1` | `staging` (prerelease or `workflow_dispatch`) |

- Strip the leading `v` before upload; keep the plugin header `Version` / `PLAYER_MANAGEMENT_VERSION` equal to the tag version in that commit.
- Production tags only from `main` as **non-prerelease** GitHub Releases.
- Staging uses **prerelease** releases (or Actions → Publish plugin → Run workflow).
- Do **not** republish the same version string to the same host; bump `-rcN` / `-dev.N` instead.
- Both environments currently publish to `https://plugins.underdogunlimited.com`. The host marks each successful publish as **latest** for slug `player-management` — publish RC, smoke on staging WP, then publish stable promptly. Do not leave an RC as latest while customer sites poll this host.

## GitHub secrets & variables

Create Environments **`production`** and **`staging`**.

| Name | Type | Where | Purpose |
|------|------|-------|---------|
| `UU_PUBLISH_TOKEN` | secret | each environment | Same as server `PLUGIN_PUBLISH_TOKEN` (write-only). Never use a site token (`udpl_…`) for publish. |
| `UU_PLUGIN_SLUG` | variable | repository | Must be `player-management` |
| `UU_PUBLISH_URL` | variable | each environment | `https://plugins.underdogunlimited.com` |

Workflow: [`.github/workflows/publish-plugin.yml`](../.github/workflows/publish-plugin.yml).

Hosting CI reference: Underdog Unlimited `docs/ci-plugin-publish.md`.

## Manual host prerequisites

1. Register the plugin once under Underdog Admin → Plugins with slug `player-management`.
2. Set `PLUGIN_PUBLISH_TOKEN` on the Underdog host and redeploy.
3. Create a staging WP site token (Account → Plugins, prefix `udpl_`).
4. Configure the GitHub secrets/variables above.

## WordPress updater (shared client)

- Activate **InterSoccer Updates** (`intersoccer-updates`) on the site.
- **Update URI** (this plugin header): `https://plugins.underdogunlimited.com`
- **Settings:** Players → **Settings**
  - **General** — Update Stream (`release` / Beta=`prerelease` / `dev`); option `intersoccer_uu_update_channel`
  - **License** — site token (`udpl_…`); option `intersoccer_uu_site_token` (same as shared client)
- Optional override: `define('INTERSOCCER_UU_SITE_TOKEN', 'udpl_…');` in `wp-config.php` (wins over the database).
- Installs into versioned folders (`player-management-plugin-{version}/`) and switches activation.

## Smoke test (staging WP)

1. Install Player Management at version **N** (older than the build you will publish) and activate **InterSoccer Updates**.
2. Players → Settings → **License** → paste site token (`udpl_…`) → Save token.
3. Players → Settings → **General** → set Update Stream to **Beta** for RC smoke (or **Release** for stable).
4. Publish `vN+1-rc…` as a GitHub **prerelease** (or `workflow_dispatch`).
5. Dashboard → Updates → confirm the new version → Update now → confirm versioned folder + plugin loads.
6. Only then: merge to `main`, tag `vN+1` as a **non-prerelease** release for production.

## Optional curl smoke

```bash
# Publish (CI token — write)
curl -X POST "https://plugins.underdogunlimited.com/api/plugins/v1/player-management/publish" \
  -H "Authorization: Bearer $PLUGIN_PUBLISH_TOKEN" \
  -F "version=2.7.26.0-rc1" \
  -F "changelog=RC smoke" \
  -F "file=@player-management-2.7.26.0-rc1.zip;type=application/zip"

# Metadata (site token — read; channel=prerelease for Beta stream)
curl -sS "https://plugins.underdogunlimited.com/api/plugins/v1/player-management?channel=prerelease" \
  -H "Authorization: Bearer $SITE_TOKEN" | jq .
```

## Out of scope

Underdog host application code, selling/entitlements, Google OAuth, and the shared update client implementation (lives in `intersoccer-updates/`).
