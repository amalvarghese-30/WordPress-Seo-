# StillWorks HTML Sitemap Sync

StillWorks HTML Sitemap Sync is a WordPress Must-Use (MU) plugin that automatically discovers standalone static HTML pages from any custom directory on your server (such as `/locations/`, `/xyz/`, `/landing/`, or root-level `.html` files) and seamlessly integrates them into Rank Math SEO's XML sitemap hierarchy.

---

## Table of Contents

- [A. Project Overview](#a-project-overview)
- [B. Features](#b-features)
- [C. Project Structure](#c-project-structure)
- [D. Requirements](#d-requirements)
- [E. Installation Guide](#e-installation-guide)
- [F. Adding HTML Pages in Any Folder](#f-adding-html-pages-in-any-folder)
- [G. Controlling Inclusions and Exclusions](#g-controlling-inclusions-and-exclusions)
- [H. How to Run and Synchronize](#h-how-to-run-and-synchronize)
- [I. Sitemap URLs and Verification](#i-sitemap-urls-and-verification)
- [J. Optional Locations Hub Companion Plugin](#j-optional-locations-hub-companion-plugin)
- [K. Troubleshooting](#k-troubleshooting)
- [L. Security and Best Practices](#l-security-and-best-practices)
- [M. Uninstallation](#m-uninstallation)
- [N. Testing and Validation](#n-testing-and-validation)

---

## A. Project Overview

### The Problem It Solves
WordPress and SEO plugins (including Rank Math) generate XML sitemaps exclusively by querying database records (`wp_posts`, `wp_terms`). If you deploy standalone static HTML landing pages—whether in `/locations/`, `/xyz/`, `/landing/`, or custom subfolders—they bypass the database for high performance and low server overhead. However, standard SEO plugins are completely unaware of these files, leaving them out of your XML sitemaps.

### How StillWorks Bridges the Gap
1. **Multi-Folder Discovery:** Automatically scans any custom directory under your WordPress root as well as standalone root HTML files, safely skipping WordPress core folders (`wp-admin`, `wp-includes`, `wp-content`).
2. **Rank Math Custom Provider:** Integrates with Rank Math via the `rank_math/sitemap/providers` filter to generate a dedicated child sitemap (`/locations-sitemap.xml`) linked from your primary `/sitemap_index.xml`.
3. **Change Detection:** Computes a SHA-256 fingerprint from discovered file paths, URLs, and modification timestamps.
4. **Targeted Invalidation:** Whenever files are added, modified, or deleted, it flushes only the root sitemap and locations sitemap cache files, keeping standard post and page caches intact.

---

## B. Features

- **Multi-Directory & New Folder Auto-Discovery:** Discovers `.html` files in `/locations/`, `/xyz/`, `/landing/`, any newly created folder, and root-level static pages.
- **WordPress Core Protection:** Skips system directories (`wp-admin`, `wp-includes`, `wp-content`, `backups`, `.git`, `node_modules`, `vendor`) so internal files never leak into sitemaps.
- **Inclusion & Exclusion Control:** Full control via WordPress filters (`excluded_dirs`, `excluded_files`, `allowed_dirs`, and `include_file`).
- **Path Traversal Protection:** Validates canonical filesystem paths (`realpath`) and ignores symlinks to prevent path traversal outside WordPress root.
- **File Filtering:** Excludes non-landing utility files by default (`index.html`, `404.html`, `thank-you.html`).
- **Filesystem Timestamps:** Formats `filemtime` into ISO-8601 UTC dates (`Y-m-d H:i:s`).
- **Clean URL Encoding:** Safely encodes nested path segments using `rawurlencode`.
- **Rank Math Integration:** Native child sitemap with automatic multi-page pagination.
- **Targeted Cache Invalidation:** Flushes only affected sitemaps upon detected change.
- **WP-Cron & Manual Sync:** Automatic 15-minute cron schedule and admin dashboard **Sync Now** button.
- **Optional Locations Hub Companion:** Separate companion plugin providing the `[stillworks_locations_hub]` shortcode for frontend card displays.

---

## C. Project Structure

```text
.
├── companion/
│   └── stillworks-locations-hub.php # Optional companion plugin with shortcode
├── locations/                       # Sample static HTML location landing pages
│   ├── bangalore.html
│   ├── delhi.html
│   ├── dubai.html
│   ├── mumbai.html
│   └── pune.html
├── mu-plugins/
│   └── html-sitemap-provider.php    # Primary WordPress Must-Use plugin
├── .env.example                     # Example Docker environment configuration
├── .gitignore                       # Excludes credentials, database dumps, and backups
├── docker-compose.yml               # Local MariaDB + WordPress environment
└── README.md                        # Complete technical and operational documentation
```

---

## D. Requirements

| Component | Supported Version | Notes |
|---|---|---|
| **WordPress** | 6.0 - 6.7+ | Must-Use plugins supported natively |
| **PHP** | 7.4 - 8.4+ | Tested on PHP 8.3 & PHP 8.4 |
| **Rank Math SEO** | Free or Pro | **XML Sitemap** module must be enabled |
| **Web Server** | Nginx or Apache | Configured to serve static `.html` files directly |
| **Permissions** | `755` (dirs) / `644` (files) | Read permission for web server user |

---

## E. Installation Guide

### Step 1: Install the MU-Plugin (Required)
1. Ensure the `wp-content/mu-plugins/` directory exists:
   ```bash
   mkdir -p wp-content/mu-plugins
   ```
2. Copy `html-sitemap-provider.php` into `wp-content/mu-plugins/`:
   ```bash
   cp mu-plugins/html-sitemap-provider.php wp-content/mu-plugins/
   ```

### Step 2: Install the Locations Hub Companion (Optional)
If you want to display your static pages in a frontend grid via shortcode:
1. Copy `companion/stillworks-locations-hub.php` into `wp-content/plugins/` (or `wp-content/mu-plugins/`):
   ```bash
   cp companion/stillworks-locations-hub.php wp-content/plugins/
   ```
2. Activate **StillWorks Locations Hub** in **WordPress Admin > Plugins**.

### Step 3: Verify Rank Math Setup
- Go to **Rank Math > Dashboard** and ensure the **Sitemap** module is enabled.
- Verify plugin loading in **WordPress Admin > Plugins > Must-Use**.

---

## F. Adding HTML Pages in Any Folder

You can add standalone `.html` files in **any custom folder** or the WordPress root:

### Examples:
- `/locations/delhi.html` &rarr; `https://example.com/locations/delhi.html`
- `/xyz/sample.html` &rarr; `https://example.com/xyz/sample.html`
- `/landing/summer-sale.html` &rarr; `https://example.com/landing/summer-sale.html`
- `/custom-page.html` &rarr; `https://example.com/custom-page.html`

Whenever a new folder is created on the server (outside of WordPress core directories), its `.html` files are discovered automatically on the next synchronization.

---

## G. Controlling Inclusions and Exclusions

You have full control over what is included in the sitemap without editing the core plugin, using WordPress filter hooks in your child theme's `functions.php` or a code snippet plugin.

### 1. Exclude Specific Folders
```php
add_filter( 'stillworks_html_sitemap_excluded_dirs', function( $dirs ) {
    $dirs[] = 'private-folder';
    $dirs[] = 'temp';
    return $dirs;
});
```

### 2. Exclude Specific Files
```php
add_filter( 'stillworks_html_sitemap_excluded_files', function( $files ) {
    $files[] = 'draft.html';
    $files[] = 'test-page.html';
    return $files;
});
```

### 3. Whitelist Mode (Only Scan Specific Folders)
If you want to restrict scanning to specific designated folders:
```php
add_filter( 'stillworks_html_sitemap_allowed_dirs', function( $allowed ) {
    return [ 'locations', 'xyz', 'landing' ];
});
```

### 4. Granular Per-File Logic
```php
add_filter( 'stillworks_html_sitemap_include_file', function( $include, $relative_path, $real_path ) {
    // Example: skip any file containing the word "preview"
    if ( strpos( $relative_path, 'preview' ) !== false ) {
        return false;
    }
    return $include;
}, 10, 3 );
```

---

## H. How to Run and Synchronize

- **Automated Sync (WP-Cron):** Runs every 15 minutes via the hook `stillworks_html_sitemap_cron_sync`.
- **Request-Based Detection:** Evaluates changes on WordPress `init` (priority 99).
- **Manual Trigger:** Go to **Tools > HTML Sitemap Sync** in the WordPress admin and click **Sync Now**.
- **System Cron Recommendation:** For sites with low traffic, disable default WP-Cron (`define('DISABLE_WP_CRON', true);`) and run `php /var/www/html/wp-cron.php` via Linux crontab every 15 minutes.

---

## I. Sitemap URLs and Verification

- **Root Sitemap Index:** `https://example.com/sitemap_index.xml`
- **Child Sitemap:** `https://example.com/locations-sitemap.xml`

### Verification Steps:
1. Open `https://example.com/locations-sitemap.xml` to view all discovered HTML pages.
2. Verify that your root index `https://example.com/sitemap_index.xml` contains the `<sitemap>` entry for `/locations-sitemap.xml`.
3. Submit `sitemap_index.xml` in [Google Search Console](https://search.google.com/search-console) under **Sitemaps**.

---

## J. Optional Locations Hub Companion Plugin

The repository includes a companion plugin at [`companion/stillworks-locations-hub.php`](file:///c:/Users/Amal%20Varghese/Desktop/WordPress-HTML-Sitemap-Demo/companion/stillworks-locations-hub.php).

### Features:
- Provides the shortcode: `[stillworks_locations_hub]`
- Bounded `<title>` extraction: Safely reads at most 2048 bytes of the HTML header to extract `<title>` without executing or loading the page.
- Responsive card grid displaying page titles, last-modified dates, and direct links.

### How to Use:
1. Install and activate `companion/stillworks-locations-hub.php`.
2. In WordPress Admin, create a new page (e.g. "Our Locations" or "Directory").
3. Add the shortcode block:
   ```text
   [stillworks_locations_hub]
   ```
4. Publish the page. It will automatically list all discovered HTML pages in a card grid.

---

## K. Troubleshooting

| Issue | Cause | Solution |
|---|---|---|
| **MU-plugin not loaded** | Placed in subfolder. | Ensure `html-sitemap-provider.php` is directly in `wp-content/mu-plugins/`. |
| **`/locations-sitemap.xml` returns 404** | Rewrite rules cached. | Go to **Settings > Permalinks** and click **Save Changes**. Verify Rank Math Sitemap module is active. |
| **HTML file in custom folder not appearing** | File extension or exclusion. | Confirm the file ends with `.html` and is not named `index.html`, `404.html`, or `thank-you.html`. Click **Sync Now** under **Tools > HTML Sitemap Sync**. |
| **Core folders scanned unexpectedly** | Custom directory name matches system folder. | Do not place public landing pages inside `wp-admin`, `wp-includes`, or `wp-content`. |
| **Cache invalidation fails** | Permissions on cache directory. | Ensure the web server user has write permissions to `wp-content/uploads/rank-math/`. |

---

## L. Security and Best Practices

1. **Safe Permissions:** Keep directories at `755` and files at `644`. Never use `777`.
2. **Path Traversal Mitigation:** The plugin uses `realpath()` and validates that all files reside inside the canonical root, rejecting symbolic links.
3. **Upload Security:** Treat static HTML files as executable code; allow only trusted developers or administrators to upload `.html` files.
4. **Credential Isolation:** Never commit `.env` files or database dumps.

---

## M. Uninstallation

1. Remove the MU-plugin:
   ```bash
   rm /path/to/wordpress/wp-content/mu-plugins/html-sitemap-provider.php
   ```
2. Remove the companion plugin (if installed):
   ```bash
   rm /path/to/wordpress/wp-content/plugins/stillworks-locations-hub.php
   ```
3. (Optional) Remove the database option:
   ```bash
   wp option delete stillworks_html_sitemap_signature
   ```
4. Static HTML files on your server remain completely unaffected.

---

## N. Testing and Validation

### PHP Syntax Check
```bash
php -l mu-plugins/html-sitemap-provider.php
php -l companion/stillworks-locations-hub.php
```

### Check Sitemap Generation via curl
```bash
# Verify child sitemap presence in index:
curl -s https://example.com/sitemap_index.xml | grep "locations-sitemap.xml"

# Verify all custom folder pages appear:
curl -s https://example.com/locations-sitemap.xml | grep "<loc>"
```
