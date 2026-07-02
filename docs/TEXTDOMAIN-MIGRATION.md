# Text Domain Migration

## Overview

The Player Management plugin has been consolidated from using two text domains to a single unified text domain.

### Previous State ❌
- Used **two text domains:**
  1. `intersoccer-player-management` (primary)
  2. `player-management` (inconsistent)

### Current State ✅
- Uses **one text domain:**
  - `player-management` (consistent throughout)

## Changes Made

### 1. Plugin Header
**File:** `player-management.php`
- Line 11: Changed `Text Domain: intersoccer-player-management` → `Text Domain: player-management`

### 2. Translation Loading
**File:** `player-management.php`
- Line 32: Changed `load_plugin_textdomain('intersoccer-player-management', ...)` → `load_plugin_textdomain('player-management', ...)`

### 3. Translation Function Calls
All translation functions updated across all PHP files:
- `__('String', 'intersoccer-player-management')` → `__('String', 'player-management')`
- `_e('String', 'intersoccer-player-management')` → `_e('String', 'player-management')`
- `esc_html__()`, `esc_html_e()`, `esc_attr__()`, `esc_attr_e()` - all updated

**Files Updated:**
- `player-management.php`
- `includes/player-management.php`
- `includes/ajax-handlers.php`
- `includes/admin-players.php`
- `includes/admin-advanced.php`
- `includes/user-profile-players.php`
- `includes/data-deletion.php`
- `includes/class-player-list.php`
- `includes/class-player-overview.php`
- `includes/templates/overview-template.php`
- `tests/bootstrap.php`
- `tests/test-sample.php`

### 4. Translation Files Renamed

| Old Name | New Name |
|----------|----------|
| `intersoccer-player-management-de_CH.po` | `player-management-de_CH.po` |
| `intersoccer-player-management-en_CH.po` | `player-management-en_CH.po` |
| `intersoccer-player-management-fr_CH.po` | `player-management-fr_CH.po` |
| `intersoccer-player-management.pot` | `player-management.pot` |

### 5. Translation File Contents
All `.po` and `.pot` files updated internally to reference `player-management` text domain.

## What Was NOT Changed

The following were intentionally **not changed** as they are identifiers, not text domains:

### CSS Class Names ✅ Kept As-Is
- `.intersoccer-player-management` (CSS class)
- Used in HTML for styling - changing would break existing CSS

### JavaScript/CSS Handle IDs ✅ Kept As-Is
- `'intersoccer-player-management'` (wp_enqueue_style handle)
- `'intersoccer-player-management-core'` (wp_enqueue_script handle)
- `'intersoccer-player-management-actions'` (wp_enqueue_script handle)
- Used by WordPress to track registered assets - changing could break dependencies

### Script Localization Handle ✅ Kept As-Is
- `'intersoccerPlayer'` (JavaScript global variable)
- Used in JavaScript code - changing would require updating all JS files

## Benefits of This Change

1. **Simpler Translation Workflow**
   - Only one `.pot` file to maintain
   - Only one text domain to configure in translation tools
   - Easier for translators to find strings

2. **WordPress Best Practices**
   - WordPress recommends one text domain per plugin
   - Matches the plugin slug convention
   - Improves compatibility with translation tools

3. **Better WPML Integration**
   - Single text domain simplifies WPML string registration
   - Easier to manage string translations in WPML admin
   - Reduces confusion when scanning for strings

4. **Easier Maintenance**
   - Developers only need to remember one text domain
   - Reduces chance of typos or inconsistencies
   - Clearer codebase for new contributors

## Compiling Translations

After deployment, compile the renamed `.po` files to `.mo` files:

### Using Poedit (Recommended)
1. Open each `.po` file in Poedit
2. Click "Save" or "Compile"
3. `.mo` files automatically generated

### Using msgfmt (Command Line)
```bash
cd languages/
msgfmt -o player-management-de_CH.mo player-management-de_CH.po
msgfmt -o player-management-en_CH.mo player-management-en_CH.po
msgfmt -o player-management-fr_CH.mo player-management-fr_CH.po
```

### Using WP-CLI
```bash
wp i18n make-mo languages/
```

## Testing Checklist

After deployment, verify:

- [ ] French site loads without errors
- [ ] German site loads without errors
- [ ] English site loads without errors
- [ ] All translated strings appear correctly
- [ ] Menu items display in correct language
- [ ] Page titles display in correct language
- [ ] Admin pages show translations
- [ ] No PHP warnings about missing text domain

## Deployment Notes

1. **Upload Updated Files**
   - All PHP files with updated text domains
   - All renamed `.po` files
   - Renamed `.pot` file

2. **Compile .mo Files** (on server or locally then upload)
   ```bash
   cd wp-content/plugins/player-management/languages/
   msgfmt -o player-management-fr_CH.mo player-management-fr_CH.po
   msgfmt -o player-management-de_CH.mo player-management-de_CH.po
   msgfmt -o player-management-en_CH.mo player-management-en_CH.po
   ```

3. **Clear Caches**
   - Clear WordPress object cache
   - Clear PHP opcache
   - Clear browser cache
   - Flush permalinks (Settings → Permalinks → Save)

4. **Verify WPML**
   - Go to WPML → Theme and plugins localization
   - Find "Player Management"
   - Click "Scan" if needed
   - Verify strings are registered

## Backwards Compatibility

### Will Old Translations Still Work?
**No.** The old `.mo` files (`intersoccer-player-management-*.mo`) will no longer be loaded.

### Migration Path
WordPress looks for translation files based on the text domain:
- **Before:** Looked for `intersoccer-player-management-{locale}.mo`
- **After:** Looks for `player-management-{locale}.mo`

The renamed files ensure continuity. No translation data is lost, just filenames changed.

## Troubleshooting

### Issue: Strings not translating after update

**Solution:**
1. Verify `.mo` files exist with new names
2. Clear WordPress object cache
3. Reload page

### Issue: Some strings still in English

**Solution:**
1. Check if translation exists in `.po` file
2. Recompile `.po` to `.mo`
3. Clear all caches

### Issue: WPML doesn't show new strings

**Solution:**
1. Go to WPML → Theme and plugins localization
2. Find "Player Management"
3. Click "Scan selected plugins for strings"
4. Go to WPML → String Translation
5. Verify strings appear

---

**Migration Completed:** November 3, 2025  
**Plugin Version:** 1.10.9+  
**Text Domain:** `player-management`

