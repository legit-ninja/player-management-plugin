# WPML Setup for Player Management Plugin

This document explains how to configure WPML translations for the Player Management plugin to support multilingual endpoints, menus, and page titles.

## Changes Made to Code

### 1. **Endpoint Slug Translation Support** ✅
- The endpoint slug `manage-players` is now registered with WPML
- Code automatically uses translated slug based on current language
- Falls back to default `manage-players` for compatibility

### 2. **Menu Item Translation** ✅
- Menu label "Manage Players" uses WordPress translation functions
- French: "Gérer les participants"
- German: "Teilnehmer verwalten"

### 3. **Page Title Translation** ✅
- Page title "Manage Your Attendees" now translates properly
- French: "Gérer vos participants"
- German: "Verwalten Sie Ihre Teilnehmer"

## WPML Admin Configuration

### Step 1: Translate the Endpoint Slug

1. **Go to WPML → String Translation**
2. **Search for:** `URL manage-players slug`
3. **You should see:**
   - Domain: `WordPress`
   - String: `manage-players`
   - Context: `URL manage-players slug`

4. **Add translations:**
   - **French (fr_CH):** `gerer-participants`
   - **German (de_CH):** `teilnehmer-verwalten`

5. **Click "Save" for each translation**

### Step 2: Flush Permalinks

After adding the translations, flush permalinks:

1. **Go to Settings → Permalinks**
2. **Click "Save Changes"** (no need to change anything)

This ensures WordPress regenerates rewrite rules with the translated slugs.

### Step 3: Verify Translations

#### **Check French (fr_CH):**
- URL should be: `https://intersoccer.legit.ninja/fr/mon-compte/gerer-participants/`
- Menu should show: "Gérer les participants"
- Page title should show: "Gérer vos participants"

#### **Check German (de_CH):**
- URL should be: `https://intersoccer.legit.ninja/de/mein-konto/teilnehmer-verwalten/`
- Menu should show: "Teilnehmer verwalten"
- Page title should show: "Verwalten Sie Ihre Teilnehmer"

#### **Check English (en_CH):**
- URL should be: `https://intersoccer.legit.ninja/en/my-account/manage-players/`
- Menu should show: "Manage Players"
- Page title should show: "Manage Your Attendees"

## Translation Files Updated

The following `.po` files have been updated with French and German translations:

### French (`intersoccer-player-management-fr_CH.po`):
- "Manage Players" → "Gérer les participants"
- "Manage Your Attendees" → "Gérer vos participants"

### German (`intersoccer-player-management-de_CH.po`):
- "Manage Players" → "Teilnehmer verwalten"
- "Manage Your Attendees" → "Verwalten Sie Ihre Teilnehmer"

## Compiling Translation Files

After updating `.po` files, they need to be compiled to `.mo` files:

### Option 1: Using Poedit (Recommended)
1. Open the `.po` file in [Poedit](https://poedit.net/)
2. Click "Save" or "Compile"
3. Poedit automatically generates the `.mo` file

### Option 2: Using msgfmt (Command Line)
```bash
# French
msgfmt -o languages/intersoccer-player-management-fr_CH.mo languages/intersoccer-player-management-fr_CH.po

# German
msgfmt -o languages/intersoccer-player-management-de_CH.mo languages/intersoccer-player-management-de_CH.po
```

### Option 3: Using WP-CLI
```bash
wp i18n make-mo languages/
```

## Testing Checklist

- [ ] English URL works: `/my-account/manage-players/`
- [ ] French URL works: `/mon-compte/gerer-participants/`
- [ ] German URL works: `/mein-konto/teilnehmer-verwalten/`
- [ ] Menu items display in correct language
- [ ] Page titles display in correct language
- [ ] Scripts and styles load correctly on translated pages
- [ ] Player data displays correctly on translated pages

## Troubleshooting

### Issue: Translated URL returns 404

**Solution:**
1. Go to **WPML → String Translation**
2. Verify the slug translation is saved
3. Go to **Settings → Permalinks** and click "Save Changes"
4. Clear all caches (browser, server, WordPress)
5. Try accessing the URL again

### Issue: Menu still shows English

**Solution:**
1. Go to **WPML → Theme and plugins localization**
2. Find "Player Management" plugin
3. Click "Scan" to rescan strings
4. Go to **WPML → String Translation**
5. Search for "Manage Players"
6. Add translations if not present

### Issue: Page title not translating

**Solution:**
1. Verify `.mo` files are compiled and uploaded
2. Clear WordPress object cache
3. Check that the correct language is active
4. Review the code in `player-management.php` line 146-165

### Issue: Both English and translated slugs work

**Note:** This is intentional for backwards compatibility. Old bookmarks and links using `manage-players` will continue to work even on translated sites.

## Code Reference

### Files Modified:
1. `player-management.php` (lines 91-106, 125-165, 177-190, 274-287)
2. `languages/intersoccer-player-management-fr_CH.po`
3. `languages/intersoccer-player-management-de_CH.po`

### Key Functions:
- `apply_filters('wpml_translate_single_string', ...)` - Gets translated slug
- `icl_register_string()` - Registers string with WPML
- `__()` - WordPress translation function for menu labels and titles

## Future Enhancements

### Additional Languages
To add more languages (e.g., Italian, Spanish):

1. Copy `.po` template: `cp languages/intersoccer-player-management.pot languages/intersoccer-player-management-it_CH.po`
2. Translate strings in the new `.po` file
3. Compile to `.mo` file
4. Add slug translation in WPML → String Translation
5. Flush permalinks

### Alternative Slug Suggestions

| Language | Current Slug | Alternative Options |
|----------|--------------|---------------------|
| French | `gerer-participants` | `mes-participants`, `participants` |
| German | `teilnehmer-verwalten` | `meine-teilnehmer`, `teilnehmer` |
| Italian | - | `gestisci-partecipanti`, `partecipanti` |
| Spanish | - | `gestionar-participantes`, `participantes` |

## Support

If you encounter issues:
1. Check [WPML documentation](https://wpml.org/documentation/)
2. Review WordPress debug log
3. Verify WPML is properly configured for your site
4. Ensure all caches are cleared

---

**Last Updated:** November 3, 2025  
**Plugin Version:** 1.10.9+

