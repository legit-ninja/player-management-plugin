# Privacy settings API (Player Management)

Medical-role access, date-of-birth visibility, and booking retention settings live in **Player Management** as plain WordPress options. Admin screens will save through the `intersoccer_privacy` settings group; this document is the stable getter contract for Reports/Rosters, Product Variations, and other plugins.

## Load conditions

`includes/privacy-settings.php` is listed in `player-management.php` `$core_files` **after** the WooCommerce active check. If WooCommerce is inactive, Player Management returns early and this file is **not** loaded. Callers must use `function_exists()` and fall back as described below.

## Options

| Constant | Option name | Type | When absent | Notes |
|----------|-------------|------|-------------|--------|
| `INTERSOCCER_OPT_MEDICAL_ROLES` | `intersoccer_medical_roles` | `string[]` role slugs | Not configured | Getter returns `['administrator']` for role lists. Access policy uses “configured” only when the stored value is an **array**. Filter hooks are a no-op for access until a valid array is saved. Registered **without** a `default` so `get_option(..., null) === null` means never saved. |
| `INTERSOCCER_OPT_RETENTION_MONTHS` | `intersoccer_retention_months` | `int` | `0` (off) | Clamped 0–120 after save. Stored `0` disables retention before filters run. |
| `INTERSOCCER_OPT_RETENTION_INACTIVE_ACCOUNT_MONTHS` | `intersoccer_retention_inactive_account_months` | `int` | `0` (off) | Inactive accounts with players but no recent booking. Stored `0` disables before filters run. |
| `INTERSOCCER_OPT_PRIVACY_SCHEMA_VERSION` | `intersoccer_privacy_settings_schema_version` | `int` | `1` | Internal schema marker; **not** in the `intersoccer_privacy` settings group. Written once via `add_option()` on `init` when missing. |

No values are seeded on existing sites until an administrator saves settings.

## Getter API

All functions are wrapped in `function_exists()` guards in `includes/privacy-settings.php`.

| Function | Returns | Behaviour |
|----------|---------|-----------|
| `intersoccer_get_medical_roles()` | `string[]` | Always includes `administrator`. Reads option, sanitizes, `apply_filters('intersoccer_medical_roles')`, sanitizes again. |
| `intersoccer_medical_roles_is_configured()` | `bool` | `true` when the stored medical roles option is an array (saved role list). |
| `intersoccer_user_has_medical_role( $user_id )` | `bool` | `false` for invalid or unknown users. Accepts non-numeric input without TypeError (normalized to int). |
| `intersoccer_user_can_view_medical( $user_id )` | `bool` | Strict bool; see access policy below. `$user_id` is normalized: `null`, non-numeric values, `0`, and negatives → `false` (deny). Numeric strings (e.g. `'5'`) are cast to int. |
| `intersoccer_user_can_view_dob( $user_id )` | `bool` | Same input rules and policy as medical; uses `intersoccer_can_view_dob`. |
| `intersoccer_get_retention_months()` | `int` | `0` = off. Stored `0` skips `intersoccer_retention_months` filter. |
| `intersoccer_is_retention_enabled()` | `bool` | `intersoccer_get_retention_months() > 0`. |
| `intersoccer_get_retention_inactive_account_months()` | `int` | Stored `0` skips filter. |
| `intersoccer_get_retention_cutoff_date( ?DateTimeImmutable $now = null )` | `?string` | `Y-m-d` in `wp_timezone()`, or `null` when retention is off. Subtracts whole months with calendar clamping (e.g. 31 Aug minus six months → last day of February, not March overflow). |
| `intersoccer_privacy_settings_schema_version()` | `int` | Current schema version (minimum 1). |
| `intersoccer_sanitize_medical_roles( $value )` | `string[]` | Settings API sanitizer. |
| `intersoccer_sanitize_retention_months( $value )` | `int` | Settings API sanitizer; 0–120. |
| `intersoccer_privacy_settings_defaults()` | `array` | Option name ⇒ default value map. |
| `intersoccer_filter_can_view_medical( $allowed, $user_id )` | `mixed` | Filter callback on `intersoccer_can_view_medical` (priority 10). |
| `intersoccer_filter_can_view_dob( $allowed, $user_id )` | `mixed` | Same logic on `intersoccer_can_view_dob`. |

Getters do not write options.

## Access policy (medical and DOB)

The same rules apply to `intersoccer_user_can_view_medical`, `intersoccer_user_can_view_dob`, and their filter callbacks:

1. **Administrator** — users with the `administrator` role are always allowed (`true`).
2. **Unconfigured** — no valid saved role array yet: return the caller’s `$allowed` value unchanged (type preserved in filters) so existing staff (including shop managers) keep legacy access after upgrade until settings are saved.
3. **Configured** — once a valid array is saved, access is a strict allow-list: `administrator` plus the saved roles only (`true` / `false` only from filters).
4. **Corrupt storage** — non-array stored value counts as unconfigured for access (pass-through), not allow-all.

A dismissible **admin notice** warns users with `manage_options` (filterable via `intersoccer_privacy_medical_notice_capability`) while access is unconfigured. The “Configure medical access” link targets the Players → Settings screen (`page=intersoccer-players-settings`, `tab=general`) via `intersoccer_privacy_medical_settings_admin_url()`.

Dismissal is stored per user in usermeta `intersoccer_privacy_medical_roles_notice_dismissed` (`INTERSOCCER_PRIVACY_MEDICAL_NOTICE_DISMISS_META`) — the only usermeta exception to the options-only storage rule. Clicking the notice dismiss control POSTs to `admin-ajax.php` with action `intersoccer_dismiss_privacy_medical_notice` (`INTERSOCCER_PRIVACY_MEDICAL_NOTICE_AJAX_ACTION`), nonce action `intersoccer_dismiss_privacy_medical_notice`, capability check, then sets the meta. Script: `assets/js/privacy-medical-notice-dismiss.js` (enqueued only while the notice is shown).

Parent/guardian access to their own children’s medical notes does **not** use these filters.

## Filters

Callers that compute a baseline `$allowed` (for example `manage_options` or `manage_woocommerce`) should use:

```php
$filtered = apply_filters( 'intersoccer_can_view_medical', $allowed, $user_id );
$filtered = apply_filters( 'intersoccer_can_view_dob', $allowed, $user_id );
```

Prefer the public helpers when Player Management is loaded:

```php
if ( ! function_exists( 'intersoccer_user_can_view_medical' ) ) {
	return false;
}
$can_view = intersoccer_user_can_view_medical( $user_id );
```

## Cross-plugin contract

When Player Management is missing, inactive, or WooCommerce is inactive (privacy file not loaded):

| Data | Fallback |
|------|----------|
| Medical notes and DOB | **Deny everyone** — strict `false`, including administrators. Use `function_exists()` on `intersoccer_user_can_view_medical` / `intersoccer_user_can_view_dob` (or equivalent) and return `false` when absent. |
| Retention | Off — `0` months, cutoff `null`. |

Do not `require` PM files from other plugins. Do not type-hint PM classes.

**Reports/Rosters and Product Variations call sites are not in this PR.** Each plugin must add a test with PM unloaded that asserts medical and DOB access is denied for an administrator.

## Settings registration

On `init`, user-facing options register under group `intersoccer_privacy` with `show_in_rest => false`. Use `settings_fields( 'intersoccer_privacy' )` on admin screens.
