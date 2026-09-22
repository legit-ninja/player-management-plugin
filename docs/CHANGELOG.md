# Changelog

## 2.7.32 — 2026-09-22

### Added
- **Settings → General**: Per-plugin **Enable beta updates** checkbox for Player Management, wired to InterSoccer Updates (`intersoccer_uu_beta_slugs`). Checkbox is disabled with a notice when Updates is inactive (#26).

### Changed
- Version bump to 2.7.32 (customer release after 2.7.31; includes #26).
- Update Stream: site-wide Beta radio removed; Release and Dev remain. One-time migration from channel `prerelease` enables beta for slug `player-management` and resets the channel to `release` (#26).

## 2.7.31 — 2026-09-20

### Added
- **Admin Overview / Player List**: Search by player name and parent name/email; sortable columns for name and DOB; View/Edit player modal with inline form; parent name display in list table.
- **My Account (Parent UI)**: Delete confirmation dialog; mobile-usable forms with 44px tap targets; clear inline validation errors; sticky CTA on mobile forms.
- **Checkout Player Assignment**: Per-line player dropdown for products requiring attendee; persistent order meta (Assigned Attendee, `intersoccer_player_index`); block place order when required assignment missing; AJAX update for player selection changes.

### Confirmed
- CSV-only exports verified (no Excel in this plugin).

## 2.7.26.0-rc1 — 2026-07-26

### Changed
- Version bump for Underdog host RC publish (`v2.7.26.0-rc1`).
- WordPress updates now deferred to shared `intersoccer-updates` client (removed in-plugin updater class).
- Publish workflow accepts four-segment calendar versions with prerelease suffixes.

## 2.7.25 — 2026-07-25

### Added
- Overview nurture filters surfaced on All Players, with canonical player name fixes in admin lists.
- Underdog Unlimited plugin update client and GitHub publish workflow (`includes/class-underdog-updater.php`, `.github/workflows/publish-plugin.yml`).

### Changed
- Overview metrics/template wiring for nurture filter deep-links.

