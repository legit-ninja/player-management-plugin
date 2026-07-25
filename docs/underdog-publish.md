# Underdog plugin publish & updates

Publish Player Management ZIPs to the Underdog Unlimited plugin host and install updates from WordPress admin.

## Tag conventions

| Intent | Git tag | Version sent to API | GitHub Environment |
|--------|---------|---------------------|--------------------|
| Customer release | `v2.7.20` | `2.7.20` | `production` |
| Staging / RC | `v2.7.20-rc3` | `2.7.20-rc3` | `staging` |
| Dev smoke | `v2.7.20-dev.1` | `2.7.20-dev.1` | `staging` (prerelease or `workflow_dispatch`) |

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

## Manual host prerequisites

1. Register the plugin once under Underdog Admin → Plugins with slug `player-management`.
2. Set `PLUGIN_PUBLISH_TOKEN` on the Underdog host and redeploy.
3. Create a staging WP site token (Account → Plugins, prefix `udpl_`).
4. Configure the GitHub secrets/variables above.

## WordPress updater

- **Update URI** (plugin header): `https://plugins.underdogunlimited.com/api/plugins/v1/player-management`
- **Base URL**: `PLAYER_MANAGEMENT_UPDATE_BASE` or filter `player_management_update_base_url`
- **Settings**: WP Admin → Players → Updates — store or revoke the site token
- Metadata and package download send `Authorization: Bearer <site token>`

## Smoke test (staging WP)

1. Install Player Management at version **N** (older than the build you will publish).
2. Players → Updates → paste site token → Save.
3. Publish `vN+1-rc…` as a GitHub **prerelease** (or `workflow_dispatch`).
4. Dashboard → Updates → confirm the new version → Update now → confirm the plugin loads.
5. Only then: merge to `main`, tag `vN+1` as a **non-prerelease** release for production.

## Optional curl smoke

```bash
# Publish (CI token — write)
curl -X POST "https://plugins.underdogunlimited.com/api/plugins/v1/player-management/publish" \
  -H "Authorization: Bearer $PLUGIN_PUBLISH_TOKEN" \
  -F "version=2.7.20-rc3" \
  -F "changelog=RC smoke" \
  -F "file=@player-management-2.7.20-rc3.zip;type=application/zip"

# Metadata (site token — read)
curl -sS "https://plugins.underdogunlimited.com/api/plugins/v1/player-management" \
  -H "Authorization: Bearer $SITE_TOKEN" | jq .
```

## Out of scope

Underdog host application code, selling/entitlements, Google OAuth, and publishing other InterSoccer plugins (copy this pattern per repo when ready).
