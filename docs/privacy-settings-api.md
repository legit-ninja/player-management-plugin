# Privacy settings API (Player Management)

Medical-role access and booking retention settings live in **Player Management** as plain WordPress options. Finn’s admin screens will save through the `intersoccer_privacy` settings group; this document is the stable getter contract for Reports/Rosters, Product Variations, and other plugins.

## Load conditions

`includes/privacy-settings.php` is listed in `player-management.php` `$core_files` **after** the WooCommerce active check. If WooCommerce is inactive, Player Management returns early and this file is **not** loaded. Callers must use `function_exists()` and fall back as described below.

## Options

| Constant | Option name | Type | When absent | Notes |
|----------|-------------|------|-------------|--------|
| `INTERSOCCER_OPT_MEDICAL_ROLES` | `intersoccer_medical_roles` | `string[]` role slugs | Not configured | Getter returns `['administrator']`. Filter `intersoccer_can_view_medical` is a no-op until the option is saved at least once. Registered **without** a `default` so `get_option(..., null) === null` means unconfigured. |
| `INTERSOCCER_OPT_RETENTION_MONTHS` | `intersoccer_retention_months` | `int` | `0` (off) | Clamped 0–120 after save. |
| `INTERSOCCER_OPT_RETENTION_INACTIVE_ACCOUNT_MONTHS` | `intersoccer_retention_inactive_account_months` | `int` | `0` (off) | Accounts with players but no booking for N months (Bay A.5). |
| `INTERSOCCER_OPT_PRIVACY_SCHEMA_VERSION` | `intersoccer_privacy_settings_schema_version` | `int` | `1` | Written once via `add_option()` on `init` when missing. Never decreased. |

No values are seeded on existing sites until an administrator saves settings.

## Getter API

All functions are wrapped in `function_exists()` guards in `includes/privacy-settings.php`.

| Function | Returns | Behaviour |
|----------|---------|-----------|
| `intersoccer_get_medical_roles()` | `string[]` | Always includes `administrator`. Reads option, sanitizes, `apply_filters('intersoccer_medical_roles')`, sanitizes again. |
| `intersoccer_medical_roles_is_configured()` | `bool` | `true` when the medical roles option exists in the database. |
| `intersoccer_user_has_medical_role( int $user_id )` | `bool` | `false` for invalid or unknown users. |
| `intersoccer_get_retention_months()` | `int` | `0` = off. Filter: `intersoccer_retention_months`. |
| `intersoccer_is_retention_enabled()` | `bool` | `intersoccer_get_retention_months() > 0`. |
| `intersoccer_get_retention_inactive_account_months()` | `int` | Filter: `intersoccer_retention_inactive_account_months`. |
| `intersoccer_get_retention_cutoff_date( ?DateTimeImmutable $now = null )` | `?string` | `Y-m-d` in `wp_timezone()`, or `null` when retention is off. Cutoff is “today minus N months” for criterion 101 (last roster `end_date` before cutoff ⇒ due). |
| `intersoccer_privacy_settings_schema_version()` | `int` | Current schema version (minimum 1). |
| `intersoccer_sanitize_medical_roles( $value )` | `string[]` | Settings API sanitizer; unknown roles dropped when `wp_roles()` is available; `administrator` always kept. |
| `intersoccer_sanitize_retention_months( $value )` | `int` | Settings API sanitizer; 0–120. |
| `intersoccer_privacy_settings_defaults()` | `array` | Option name ⇒ default value map. |
| `intersoccer_filter_can_view_medical( $allowed, $user_id )` | `mixed` | Registered on `intersoccer_can_view_medical` at priority 10. |

Getters do not write options.

## `intersoccer_can_view_medical` filter

Reports/Rosters (draft medical access helper) computes a base allowance (`manage_options` or `manage_woocommerce`), then:

```php
$filtered = apply_filters( 'intersoccer_can_view_medical', $allowed, $user_id );
```

Player Management registers `intersoccer_filter_can_view_medical`:

1. **Not configured** — return `$allowed` unchanged (type preserved). Existing sites keep admins and shop managers until medical roles are saved.
2. **Configured** — strict `true` if `intersoccer_user_has_medical_role( $user_id )`, else strict `false`. Saving only `administrator` removes shop managers unless their role is ticked.
3. Invalid or missing user — `false` when configured; pass-through when not configured.
4. When configured, never return a non-boolean.

Parent/guardian access to their own children’s medical notes does **not** use this filter.

## Cross-plugin contract

Call helpers only behind `function_exists()`:

```php
$roles = function_exists( 'intersoccer_get_medical_roles' )
    ? intersoccer_get_medical_roles()
    : [ 'administrator' ];
```

| PM / Woo state | Medical access fallback | Retention fallback |
|----------------|-------------------------|-------------------|
| PM inactive or Woo inactive (file not loaded) | Administrator only (`manage_options` / `administrator` role) at the **caller** | `0` months, cutoff `null` (nothing purged) |

Do not `require` PM files from other plugins. Do not type-hint PM classes.

When PM is inactive, **nothing** hooks `intersoccer_can_view_medical`, so Reports/Rosters’ own base (admins and shop managers) still applies until RR changes its call site to administrator-only. That call-site change is **not** part of the PM privacy settings PR.

## Settings registration

On `init`, options register under group `intersoccer_privacy` with `show_in_rest => false`. Finn should use `settings_fields( 'intersoccer_privacy' )` on the medical and retention screens.
