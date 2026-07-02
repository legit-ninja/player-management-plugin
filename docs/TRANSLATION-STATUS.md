# Translation Status - Player Management Plugin

## Overview

After text domain consolidation to `player-management`, translations have been partially completed for French and German.

## Current Status (November 3, 2025)

### French (`fr_CH`)
- **Translated:** 14 strings
- **Untranslated:** 323 strings  
- **Completion:** ~4%

**Key Translations Complete:**
- ✅ "Manage Players" → "Gérer les participants"
- ✅ "Manage Your Attendees" → "Gérer vos participants"
- ✅ "Coach" → "Entraîneur"
- ✅ "Organizer" → "Organisateur"
- ✅ "Settings" → "Paramètres"
- ✅ "Players Overview Dashboard" → "Tableau de bord des participants"
- ✅ "Refresh Data" → "Actualiser les données"
- ✅ Dashboard status labels (Error, Warning, Method, etc.)
- ✅ "Total Players" → "Total des participants"
- ✅ "Gender Breakdown" → "Répartition par genre"
- And more...

### German (`de_CH`)
- **Translated:** 2 strings (core strings)
- **Untranslated:** 335 strings
- **Completion:** ~0.6%

**Key Translations Complete:**
- ✅ "Manage Players" → "Teilnehmer verwalten"
- ✅ "Manage Your Attendees" → "Verwalten Sie Ihre Teilnehmer"

### English (`en_CH`)
- **Translated:** Fully complete (source language)
- Used as the base for all translations

## Priority Strings for Translation

These are the most visible strings that should be translated first:

### High Priority (Frontend - User Facing)
1. **Menu & Navigation:**
   - "Manage Players"
   - "Manage Your Attendees"
   - "Dashboard"
   - "Settings"

2. **Player Form:**
   - "First Name"
   - "Last Name"
   - "Date of Birth"
   - "Gender" 
   - "Male", "Female", "Other"
   - "Medical Conditions"
   - "Dietary Requirements"

3. **Actions:**
   - "Add Player"
   - "Edit Player"
   - "Delete Player"
   - "Save"
   - "Cancel"
   - "Confirm"

4. **Messages:**
   - "Player saved successfully."
   - "Player deleted successfully."
   - "Error saving player."
   - "Please fill in all required fields."
   - "No players found."
   - "Loading..."

### Medium Priority (Admin Dashboard)
1. **Dashboard:**
   - "Players Overview Dashboard"
   - "Refresh Data"
   - "Total Players"
   - "Users Without Players"
   - "Gender Breakdown"
   - "Players by Canton"

2. **Status Messages:**
   - "Data Status:"
   - "Generated:"
   - "Users Processed:"
   - "Method:"
   - "Error:"
   - "Warning:"

### Low Priority (Advanced Features)
1. **Data Deletion:**
   - "Request Data Deletion"
   - "Reason for Deletion (optional):"
   - "Submit Request"

2. **Debug Info:**
   - "Debug Information"
   - "Memory Peak:"
   - "Processing Method:"
   - "Cache Key:"

## How to Complete Translations

### Method 1: WPML String Translation (Recommended)

1. **Go to WPML → String Translation**
2. **Filter by:** `player-management` text domain
3. **Sort by:** "Strings without translation" first
4. **Translate inline** using the WPML interface
5. **Focus on High Priority strings** first

**Advantages:**
- Visual interface
- Context provided
- Immediate deployment
- No file compilation needed

### Method 2: Edit .po Files Directly

1. **Open .po file** in Poedit or text editor
2. **Find empty** `msgstr ""` entries
3. **Add translations:**
   ```
   msgid "Add Player"
   msgstr "Ajouter un participant"  # French
   msgstr "Teilnehmer hinzufügen"  # German
   ```
4. **Save** and compile to .mo
5. **Upload** both .po and .mo files

**Tools:**
- [Poedit](https://poedit.net/) - Free desktop app
- Text editor (for bulk find/replace)
- `msgfmt` command line tool

### Method 3: Professional Translation Service

For bulk translation:
1. **Export** untranslated strings from WPML
2. **Send to translator** with context
3. **Import** completed translations
4. **Review** and adjust as needed

## Deployment

After adding translations:

### Compile .mo Files
```bash
cd languages/
msgfmt -o player-management-fr_CH.mo player-management-fr_CH.po
msgfmt -o player-management-de_CH.mo player-management-de_CH.po
```

### Upload to Server
```bash
cd /home/jeremy-lee/projects/underdog/intersoccer/player-management
./deploy.sh --clear-cache
```

### Or Upload via WPML
WPML automatically compiles .mo files when you save translations in the admin interface.

## Testing Translations

### French
1. Switch language to **French (fr_CH)**
2. Visit: `https://intersoccer.legit.ninja/fr/mon-compte/gerer-participants/`
3. Verify menu shows: "Gérer les participants"
4. Verify page title shows: "Gérer vos participants"

### German
1. Switch language to **German (de_CH)**
2. Visit: `https://intersoccer.legit.ninja/de/mein-konto/teilnehmer-verwalten/`
3. Verify menu shows: "Teilnehmer verwalten"
4. Verify page title shows: "Verwalten Sie Ihre Teilnehmer"

## Common French Translations Reference

| English | French |
|---------|--------|
| Player | Participant |
| Players | Participants |
| Attendee | Participant |
| Manage | Gérer |
| Add | Ajouter |
| Edit | Modifier |
| Delete | Supprimer |
| Save | Enregistrer |
| Cancel | Annuler |
| Settings | Paramètres |
| Dashboard | Tableau de bord |
| Loading | Chargement |
| Error | Erreur |
| Warning | Avertissement |
| Success | Succès |
| First Name | Prénom |
| Last Name | Nom |
| Date of Birth | Date de naissance |
| Gender | Genre |
| Male | Garçon |
| Female | Fille |
| Required | Obligatoire |

## Common German Translations Reference

| English | German |
|---------|--------|
| Player | Teilnehmer |
| Players | Teilnehmer |
| Attendee | Teilnehmer |
| Manage | Verwalten |
| Add | Hinzufügen |
| Edit | Bearbeiten |
| Delete | Löschen |
| Save | Speichern |
| Cancel | Abbrechen |
| Settings | Einstellungen |
| Dashboard | Dashboard |
| Loading | Laden |
| Error | Fehler |
| Warning | Warnung |
| Success | Erfolg |
| First Name | Vorname |
| Last Name | Nachname |
| Date of Birth | Geburtsdatum |
| Gender | Geschlecht |
| Male | Junge |
| Female | Mädchen |
| Required | Erforderlich |

## Notes

- The plugin uses `player-management` text domain consistently
- Old `intersoccer-player-management` strings have been cleaned from WPML
- `default` text domain contains WordPress core strings (ignore these)
- Translations are stored in `languages/` directory
- Both `.po` (source) and `.mo` (compiled) files are needed

## Next Steps

1. ✅ Text domain consolidated to `player-management`
2. ✅ WPML cleaned up (old strings removed)
3. ✅ Core navigation strings translated (FR & DE)
4. ⏳ **High priority strings** - Complete via WPML String Translation
5. ⏳ **Medium priority strings** - Add as needed
6. ⏳ **Low priority strings** - Add for completeness

---

**Files:**
- `player-management-fr_CH.po` - French source
- `player-management-fr_CH.mo` - French compiled
- `player-management-de_CH.po` - German source
- `player-management-de_CH.mo` - German compiled
- `player-management-en_CH.po` - English source (reference)
- `player-management-en_CH.mo` - English compiled

**Last Updated:** November 3, 2025  
**Plugin Version:** 1.10.9+

