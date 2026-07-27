# Player source of truth

**SoT:** WordPress user meta `intersoccer_players` (array of player rows) with stable UUID `player_id`.

API: `includes/player-data.php` (`intersoccer_get_user_players`, `intersoccer_get_player_by_id`, save helpers).

**Not SoT:** SQL tables created by `InterSoccer_Player_Database` (`{prefix}intersoccer_players`, etc.). Those are a legacy/migration path. Writes via `create_player` / `update_player` are blocked unless `intersoccer_pm_allow_secondary_player_table_writes` is filtered to `true`.

## Overview KPI ownership

| KPI | Owner | Source |
|-----|-------|--------|
| `participants_total` | Player Management | usermeta batch |
| `parents_no_players` | Player Management | users without players |
| `never_booked_lifetime` | Player Management | usermeta + event count helper |
| `incomplete_profiles` | Player Management | DOB / medical on player row |
| Season bookings / fill | Reports/Rosters | rostes / Final Numbers — **not** PM Overview |

See Cursor skill **business-intelligence** → `kpi-catalog.md` and rule **data-model**.
