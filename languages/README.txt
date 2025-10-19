# Translation Files for Alles3D Product Customizer

## Available Translations
- Dutch (nl_NL)

## How to Use Translations
The plugin will automatically load the appropriate translation based on your WordPress language setting.

## Adding New Translations
1. Copy the .pot file from this directory
2. Use a tool like Poedit to create a new translation
3. Save the .po and .mo files with the appropriate language code (e.g., fr_FR.po and fr_FR.mo for French)
4. Place the files in this directory

## Updating Translations
1. Open the .po file for the language you want to update in Poedit
2. Update the translations as needed
3. Save the file, which will automatically update the .mo file

## Translation Tips
- Always test your translations in a development environment
- Pay special attention to placeholders (%s, %d) in strings
- HTML tags should be preserved in translations

## File Structure
- alles3d-customizer.pot - Template file for translations
- nl_NL.po - Dutch translation source file
- nl_NL.mo - Dutch translation compiled file

## Generating POT File
If you need to update the POT file after adding new strings to the plugin, use:
```
wp i18n make-pot . languages/alles3d-customizer.pot
```
(requires WP-CLI with the i18n command)