# WPML Text Domain Cleanup Guide

## Problem

After migrating from `intersoccer-player-management` to `player-management`, WPML still shows the old text domain because it cached the strings during previous scans.

**Current State in WPML:**
- ❌ `intersoccer-player-management` (old, should be removed)
- ✅ `player-management` (correct, should be kept)
- ℹ️  `default` (WordPress core, ignore this)

## Solution: Clean Up WPML String Registration

### Method 1: WPML Admin Interface (Recommended)

#### Step 1: Delete Old Text Domain Strings

1. **Go to WPML → String Translation**
2. **Filter by text domain:**
   - In the "Filter" dropdown, select `intersoccer-player-management`
3. **Select all strings:**
   - Check the "Select all" checkbox at the top
4. **Delete the strings:**
   - From the "Bulk actions" dropdown, select "Delete"
   - Click "Apply"
   - Confirm deletion

#### Step 2: Re-scan Plugin with New Text Domain

1. **Go to WPML → Theme and plugins localization**
2. **Find "Player Management"** in the plugins list
3. **Select the plugin** (check the checkbox)
4. **Click "Scan selected plugins for strings"**
5. **Wait for scan to complete**

#### Step 3: Verify String Registration

1. **Go to WPML → String Translation**
2. **Filter by text domain:** Select `player-management`
3. **Verify strings appear** with the correct text domain
4. **Translate any strings** that need translation

---

### Method 2: SQL Database Cleanup (Advanced)

If Method 1 doesn't work or you prefer direct database access:

#### ⚠️ WARNING: Backup Database First!

```bash
# SSH into server and backup database
mysqldump -u username -p database_name > backup_$(date +%Y%m%d).sql
```

#### Step 1: Check Existing Strings

```sql
-- View all registered text domains
SELECT DISTINCT context 
FROM wp_icl_strings 
WHERE context LIKE '%player-management%' 
OR context LIKE '%intersoccer-player%'
ORDER BY context;
```

#### Step 2: Delete Old Text Domain Strings

```sql
-- Delete strings with old text domain
DELETE FROM wp_icl_strings 
WHERE context = 'intersoccer-player-management';

-- Verify deletion
SELECT COUNT(*) as remaining_old_strings 
FROM wp_icl_strings 
WHERE context = 'intersoccer-player-management';
-- Should return 0
```

#### Step 3: Delete Related String Translations

```sql
-- Delete translations for old strings (orphaned translations)
DELETE FROM wp_icl_string_translations 
WHERE string_id NOT IN (SELECT id FROM wp_icl_strings);

-- Optional: Clean up string pages (if exists)
DELETE FROM wp_icl_string_pages 
WHERE string_id NOT IN (SELECT id FROM wp_icl_strings);
```

#### Step 4: Verify New Text Domain

```sql
-- Check new text domain strings
SELECT id, context, name, value 
FROM wp_icl_strings 
WHERE context = 'player-management' 
ORDER BY name 
LIMIT 20;
```

---

### Method 3: WordPress CLI (If Available)

```bash
# SSH into server
cd /path/to/wordpress

# Delete old text domain strings
wp eval "
global \$wpdb;
\$deleted = \$wpdb->delete(
    \$wpdb->prefix . 'icl_strings',
    array('context' => 'intersoccer-player-management'),
    array('%s')
);
echo \"Deleted \$deleted strings\\n\";
"

# Re-scan plugin
wp wpml string scan player-management
```

---

## Verification Steps

After cleanup, verify the changes:

### 1. Check WPML String Translation

1. Go to **WPML → String Translation**
2. Filter by: `player-management`
3. You should see strings like:
   - "Manage Players"
   - "Manage Your Attendees"
   - "Coach"
   - "Organizer"
   - etc.

### 2. Check Text Domain Filter

1. Go to **WPML → String Translation**
2. Look at the "Text domain" filter dropdown
3. You should see:
   - ✅ `player-management`
   - ℹ️  `default` (normal)
   - ❌ `intersoccer-player-management` should NOT appear

### 3. Test Translation

1. Switch to **French** (`fr_CH`)
2. Visit: `https://intersoccer.legit.ninja/fr/mon-compte/`
3. Menu should show: **"Gérer les participants"**
4. Visit the page
5. Title should show: **"Gérer vos participants"**

### 4. Test German

1. Switch to **German** (`de_CH`)
2. Visit: `https://intersoccer.legit.ninja/de/mein-konto/`
3. Menu should show: **"Teilnehmer verwalten"**
4. Visit the page
5. Title should show: **"Verwalten Sie Ihre Teilnehmer"**

---

## Troubleshooting

### Issue: Old text domain still appears after deletion

**Possible Causes:**
1. WPML cache not cleared
2. Object cache not cleared
3. Strings re-registered on page load

**Solutions:**

#### Clear All Caches
```bash
# SSH into server
cd /path/to/wordpress

# Clear WPML cache
wp cache flush

# Clear object cache
wp cache flush --type=object

# Clear opcache
# Method 1: Restart PHP-FPM
sudo systemctl reload php-fpm

# Method 2: Via WordPress admin
# Visit any WPML page to trigger cache regeneration
```

#### Deactivate and Reactivate Plugin
1. Go to **Plugins**
2. **Deactivate** "Player Management"
3. **Activate** "Player Management"
4. Go to **WPML → Theme and plugins localization**
5. **Scan** the plugin again

---

### Issue: Strings registered with both text domains

**This can happen if:**
- The plugin was scanned before cleanup
- Some files still reference old text domain

**Solution:**

1. **Verify all files updated:**
   ```bash
   cd /path/to/player-management
   grep -r "intersoccer-player-management" --include="*.php" | \
     grep -E "__\(|_e\(|esc_html|Text Domain"
   ```
   Should return no results (except CSS classes/script handles)

2. **Delete ALL strings and re-scan:**
   - Delete `intersoccer-player-management` strings (Method 1 or 2 above)
   - Delete `player-management` strings too
   - Re-scan plugin from WPML
   - This forces a fresh registration

---

### Issue: Translations not appearing

**Check:**

1. **`.mo` files exist:**
   ```bash
   ls -la languages/
   # Should see:
   # player-management-de_CH.mo
   # player-management-en_CH.mo
   # player-management-fr_CH.mo
   ```

2. **Compile `.mo` files if missing:**
   ```bash
   cd languages/
   msgfmt -o player-management-de_CH.mo player-management-de_CH.po
   msgfmt -o player-management-en_CH.mo player-management-en_CH.po
   msgfmt -o player-management-fr_CH.mo player-management-fr_CH.po
   ```

3. **Check file permissions:**
   ```bash
   chmod 644 languages/*.mo
   ```

4. **Clear WordPress translation cache:**
   ```bash
   wp cache flush
   wp transient delete --all
   ```

---

## Prevention: Avoid Duplicate Text Domains in Future

### Best Practices:

1. **Always use one text domain** throughout the plugin
2. **Match the plugin slug:** Use `player-management` (matches directory name)
3. **Be consistent:** Use the same text domain in all `__()`, `_e()`, etc.
4. **Update WPML immediately** after text domain changes
5. **Test in all languages** after any localization changes

### Code Review Checklist:

Before committing changes with translation strings:

- [ ] All `__()` calls use `player-management`
- [ ] All `_e()` calls use `player-management`
- [ ] All `esc_html__()` calls use `player-management`
- [ ] Plugin header has `Text Domain: player-management`
- [ ] `load_plugin_textdomain()` uses `player-management`
- [ ] Translation files named `player-management-*.po`

---

## Quick Reference

### WPML String Translation URL
```
https://intersoccer.legit.ninja/wp-admin/admin.php?page=wpml-string-translation/menu/string-translation.php
```

### WPML Theme/Plugin Localization URL
```
https://intersoccer.legit.ninja/wp-admin/admin.php?page=sitepress-multilingual-cms/menu/theme-localization.php
```

### Database Tables Used by WPML
- `wp_icl_strings` - Registered strings
- `wp_icl_string_translations` - String translations
- `wp_icl_string_pages` - String-to-page relationships (optional)

---

**Last Updated:** November 3, 2025  
**Plugin Version:** 1.10.9+  
**Correct Text Domain:** `player-management`

