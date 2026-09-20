=== FSWEB Update Checker ===
Contributors: flowsyncweb
License: GPL-2.0+
License URI: https://www.gnu.org/licenses/gpl-2.0.txt

Self-contained update checker for WordPress plugins that use a custom update
server (e.g. a Laravel app). Ships with your plugin — no external dependency.

== Description ==

Drop-in library that enables automatic plugin updates from your own update
server. Designed to be bundled inside each plugin. Multiple plugins can include
this same library without conflicts.

Each plugin that bundles this library uses its own class name and namespace
derived from the plugin name (e.g. FSWEB_Update_Checker_AiAutomatedContact_1_0 and
FSWEB\UpdateChecker\AiAutomatedContact\V1). Plugins never share class definitions.

== How to use ==

1. Copy the entire `fsweb-update-checker/` folder into your plugin.

2. In your main plugin file, add:

    require_once plugin_dir_path( __FILE__ ) . 'fsweb-update-checker/index.php';
    new FSWEB_Update_Checker_AiAutomatedContact_1_0(
        'https://your-server.com/api/updates/{slug}.json',
        __FILE__
    );

   Optionally you can pass a custom slug and check period:

    new FSWEB_Update_Checker_AiAutomatedContact_1_0(
        'https://your-server.com/api/updates/my-plugin.json',
        __FILE__,
        'my-custom-slug',
        6   // Check every 6 hours (default: 12)
    );

== JSON Metadata Format ==

Your update server should return JSON with at least these fields:

{
    "version": "1.1.0",
    "download_url": "https://your-server.com/downloads/my-plugin-v1.1.0.zip",
    "last_updated": "2025-01-15",
    "requires": "5.0",
    "tested": "6.7",
    "requires_php": "7.4",
    "sections": {
        "description": "<p>What this plugin does</p>",
        "changelog": "<p>Version 1.1.0 - Fixed bugs</p>"
    },
    "icons": {
        "1x": "https://your-server.com/icons/icon-128x128.png",
        "2x": "https://your-server.com/icons/icon-256x256.png"
    }
}

* `version` and `download_url` are **required**.
* All other fields are optional but improve the update experience.
* For the **Previous Versions** rollback feature, include a `versions` array.
  Each entry: `{"version": "...", "download_url": "...", "date": "...", "notes": "..."}`

== Previous Versions (Version Rollback) ==

The plugin row gains a **"Previous Versions"** action link that opens a
thickbox modal. The user can select any older version and install it.

**How it works:**

1. The metadata JSON must include a `versions` array (see format above).
2. The modal shows all versions sorted newest-first, with the installed
   version highlighted.
3. Selecting a non-current version enables the **"Install Selected Version"**
   button.
4. Clicking it triggers an AJAX request that:
   - Deactivates the plugin (if active)
   - Deletes the current plugin files
   - Downloads and installs the selected version ZIP
   - Reactivates the plugin (if it was active before)
5. The plugins page reloads to reflect the version change.

**Requirements:**

* The user must have `update_plugins` capability (admin by default).
* The server's ZIP files must be valid WordPress plugin packages.
* The server must be reachable for the download.

== Conflict Prevention ==

This plugin's copy of the library uses its own class name and namespace
(`FSWEB_Update_Checker_AiAutomatedContact_1_0` / `FSWEB\UpdateChecker\AiAutomatedContact\V1`).
Each plugin that bundles the library gets its own identifiers, so multiple
plugins can be active at the same time without sharing class definitions.

1. **Per-plugin classes → safe.** Each plugin's facade class and namespace are
   unique, so editing one plugin's copy never affects another.

2. **Each instance is independent** — `new FSWEB_Update_Checker_AiAutomatedContact_1_0(...)`
   creates a separate object with its own slug, hooks, cache option, and
   cron schedule.

3. **Slug uniqueness is enforced** — if two plugins accidentally use the same
   slug, a clear error is shown immediately.

4. **Hook names include the slug** — filters like `fsweb_slug_in_use_{slug}`
   and cron hooks like `fsweb_check_updates_{slug}` are namespaced per slug,
   preventing collisions.

== Changelog ==

= 1.0.0 =
* Initial release.
