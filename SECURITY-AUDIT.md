# Security Vulnerability Audit Report

**Plugin:** InterSoccer Player Management
**Date:** 2026-02-20
**Last updated:** 2026-07-02
**Auditor:** Claude (automated security review)
**Scope:** Full codebase static analysis

---

## Remediation Status (2026-07-02)

| Item | Status |
|------|--------|
| CDN scripts (Flatpickr, CodeMirror) | **Resolved** — self-hosted under `assets/vendor/` |
| `intersoccer_preview_cleanup()` capability guard | **Resolved** — `manage_options` check added |
| PII in debug logs (`intersoccer_get_player_event_count`, AJAX add) | **Resolved** — summary-only logging |
| Validator `ABSPATH` guard | **Resolved** |
| `wp_delete_user()` without reassignment | **Accepted/documented** — intentional GDPR erasure (see `data-deletion.php`) |

---

## Summary

| Severity | Count |
|----------|-------|
| Critical | 1     |
| High     | 2     |
| Medium   | 5     |
| Low      | 3     |

---

## Vulnerability Details

### [CRITICAL] Stored XSS via Unescaped JavaScript Template Literals

**File:** `js/player-management-actions.js:134–149`
**CWE:** CWE-79 – Improper Neutralization of Input During Web Page Generation (XSS)

The `updateTable()` function builds HTML using template literals and injects server-supplied player data directly into the DOM without escaping:

```javascript
const html = `
    <tr data-player-index="${player.player_index}"
        data-first-name="${player.first_name || 'N/A'}"
        ...>
        <td class="display-name">${name}</td>
        ...
        <a href="#" class="edit-player" aria-label="Edit ${player.first_name || ''}">Edit</a>
    </tr>
`;
$table.append(html);            // parsed as raw HTML
$table.find(...).replaceWith(html);
```

**Root Cause:** `sanitize_text_field()` strips HTML tags via `strip_tags()` but does **not** HTML-encode `"`, `'`, or `&`. A first or last name containing `"` breaks out of HTML attribute context. For example, submitting:

```
player_first_name: John" onmouseover="alert(document.cookie)
```

produces the following in the rendered DOM:

```html
data-first-name="John" onmouseover="alert(document.cookie)"
aria-label="Edit John" onmouseover="alert(document.cookie)"
```

Because any authenticated customer can save their own players (the `current_user_can('edit_user', $user_id)` check passes for the user's own ID), a malicious user can persist an XSS payload that executes for any admin who views the All Players dashboard.

**Fix:** Replace template-literal HTML construction with jQuery's safe DOM-building methods (`.text()`, `.attr()`), or use a helper to HTML-encode all values before interpolation:

```javascript
function escHtml(s) {
    return String(s)
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}
```

---

### [HIGH] No File-Type Validation on CSV Upload

**File:** `includes/admin-advanced.php:321–339` (camp terms import) and `:341–387` (users/players import)
**CWE:** CWE-434 – Unrestricted Upload of File with Dangerous Type

```php
if (!empty($_FILES['csv_file']['tmp_name'])) {
    $csv_data = file_get_contents($_FILES['csv_file']['tmp_name']);
```

The server only accepts the `accept=".csv"` constraint from the HTML form, which is a client-side control and trivially bypassed. No MIME type or extension check is performed on `$_FILES['csv_file']`.

While exploiting this requires `manage_options`, it violates defence-in-depth. An attacker who gains temporary admin access, or exploits another vulnerability to reach this code path, can upload arbitrary content.

**Fix:** Validate file extension and MIME type before processing:

```php
$allowed_types = ['text/csv', 'text/plain', 'application/csv'];
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($_FILES['csv_file']['tmp_name']);
$ext   = strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION));

if ($ext !== 'csv' || !in_array($mime, $allowed_types, true)) {
    $message .= __('Invalid file type. Only CSV files are allowed.', 'player-management');
} else {
    // proceed
}
```

---

### [HIGH] Call to Undefined Function `add_user_to_role()`

**File:** `includes/admin-advanced.php:364`
**CWE:** CWE-391 – Unchecked Error Condition / logic error

```php
add_user_to_role($user_id, 'customer');
```

`add_user_to_role()` does not exist in WordPress core. This produces a PHP fatal error (`Call to undefined function add_user_to_role()`) during any CSV user import, silently aborting the import without error feedback and potentially leaving partially-imported users with no role assigned.

**Fix:** Use the correct WordPress API:

```php
$user_obj = new WP_User($user_id);
$user_obj->set_role('customer');
```

---

### [MEDIUM] Undefined Variable `$age` in CSV Import Logic

**File:** `includes/admin-advanced.php:378`
**CWE:** CWE-457 – Use of Uninitialized Variable

```php
'age_group' => $player_dob
    ? ($age <= 5 ? 'Mini Soccer' : ($age <= 13 ? 'Fun Footy' : 'Soccer League'))
    : 'N/A'
```

`$age` is never calculated from `$player_dob`. PHP will raise an `E_NOTICE` and treat `$age` as `0`, always yielding `'Mini Soccer'` for any player with a valid DOB.

**Fix:** Calculate age before use:

```php
$age = $player_dob
    ? (int)((time() - strtotime($player_dob)) / 31536000)
    : 0;
```

---

### [MEDIUM] CSV Formula Injection (CSV Injection) in Export

**File:** `includes/admin-advanced.php:389–414`
**CWE:** CWE-1236 – Improper Neutralization of Formula Elements in a CSV File

User-supplied data (email, first/last name, region, player name) is written directly to the CSV export via `fputcsv()` without sanitizing formula-injection prefixes (`=`, `+`, `-`, `@`):

```php
fputcsv($output, [
    $user->user_email,
    $first_name,
    $last_name,
    $region,
    $player['name'] ?? '',
    ...
]);
```

If any field begins with `=`, spreadsheet applications (Excel, Google Sheets) may interpret it as a formula, potentially executing arbitrary code when the exported file is opened.

**Fix:** Prefix any cell value starting with `=`, `+`, `-`, or `@` with a tab or single quote, or wrap it in a safe prefix:

```php
function sanitize_csv_cell(string $value): string {
    if (in_array(substr($value, 0, 1), ['=', '+', '-', '@'], true)) {
        return "\t" . $value;
    }
    return $value;
}
```

---

### [MEDIUM] External Scripts Loaded Without Subresource Integrity (SRI) — **Resolved 2026-07-02: self-hosted**

**Files:**
- `includes/admin-advanced.php` (CodeMirror)
- `includes/user-profile-players.php` (Flatpickr)
- `player-management.php` (Flatpickr)

**CWE:** CWE-829 – Inclusion of Functionality from Untrusted Control Sphere

Scripts and styles were previously loaded from third-party CDNs without `integrity` (SRI) attributes. As of 2026-07-02, Flatpickr (v4.6.13) and CodeMirror (v5.65.7) are bundled locally under `assets/vendor/` and enqueued via `PLAYER_MANAGEMENT_URL` with `PLAYER_MANAGEMENT_VERSION` for cache busting.

**Status:** Resolved — CDN supply-chain risk eliminated by self-hosting.

---

### [MEDIUM] Sensitive PII Written to Debug Log — **Resolved 2026-07-02**

**Files:**
- `includes/ajax-handlers.php`
- `includes/player-management.php` (`intersoccer_get_player_event_count`)
- `includes/user-profile-players.php`

**CWE:** CWE-532 – Insertion of Sensitive Information into Log File

Full player records (including medical conditions and dates of birth) were previously serialized and written to the PHP error log when `WP_DEBUG` is enabled.

**Fix applied:** AJAX add handler logs user ID and player count only. `intersoccer_get_player_event_count()` logs a single summary line (`user_id`, `player_index`, `count`) with no names or medical data.

---

### [LOW] `debug_backtrace()` Called on Every `the_title` Filter

**File:** `player-management.php:289–299`
**CWE:** CWE-400 – Uncontrolled Resource Consumption

```php
$backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
foreach ($backtrace as $trace) { ... }
```

This is called inside the `the_title` filter, which fires on every post/page title rendered on any WordPress page. `debug_backtrace()` is expensive and triggers memory allocation proportional to the call stack depth. Under load this can significantly increase memory usage and slow page rendering.

**Fix:** Replace the backtrace-based context check with a proper WordPress conditional or a flag set via a hook that only runs on the relevant page.

---

### [LOW] Log Injection via Unsanitized `$_SERVER['REQUEST_URI']`

**File:** `player-management.php:149–151`
**CWE:** CWE-117 – Improper Output Neutralization for Logs

```php
error_log(sprintf(
    'InterSoccer Player Management: Endpoint content called | ... | Current URL: %s',
    $_SERVER['REQUEST_URI'] ?? 'unknown'
));
```

`$_SERVER['REQUEST_URI']` is user-controlled. A crafted request URI containing newlines could inject arbitrary entries into the PHP error log, confusing log parsers or hiding attack traces.

**Fix:** Sanitize before logging:

```php
$uri = sanitize_text_field($_SERVER['REQUEST_URI'] ?? 'unknown');
error_log("InterSoccer: Endpoint content called | ... | Current URL: {$uri}");
```

---

### [LOW] `wp_delete_user()` Called Without Content Reassignment — **Accepted/documented**

**Files:**
- `includes/data-deletion.php`
- `includes/admin-advanced.php`

**CWE:** CWE-20 – Improper Input Validation (data integrity)

```php
wp_delete_user($user_id, 0);
```

Without a reassignment user ID, WordPress permanently deletes all posts authored by the user. For GDPR data-erasure requests this is intentional: customer registration data should be removed, and WooCommerce orders are preserved separately.

**Status:** Accepted and documented in `includes/data-deletion.php` and `includes/admin-advanced.php`.

---

## Additional Notes

- **`intersoccer_preview_cleanup()` capability check** — **Resolved 2026-07-02.** Function now returns `[]` unless `current_user_can('manage_options')`.
- **Version number inconsistency** across files (`2.1.26` in root, `1.3.130` / `1.3.96` in includes) could cause issues with WordPress update and cache-busting logic.
- **`current_user_can('edit_user', $user_id)`** (used in `ajax-handlers.php:30, 131, 235, 285`) correctly restricts AJAX operations to the user's own players when called by non-admins. This is the correct pattern.

---

## Risk Priority Order

1. **Fix the XSS in `updateTable()` immediately** — it is exploitable by any authenticated user and affects admin sessions.
2. **Fix `add_user_to_role()`** — it is a hard crash in the import path.
3. **Add file upload validation** — defence-in-depth for the CSV import.
4. **Sanitize CSV export cells** — prevents formula injection for anyone who opens the export.
5. **Add SRI hashes** — mitigates CDN supply-chain risk.
6. **Reduce PII in debug logs** — GDPR and data-hygiene concern.
7. **Remove `debug_backtrace()` from `the_title` filter** — performance and reliability.
8. **Sanitize `REQUEST_URI` before logging** — low effort, removes log injection risk.
