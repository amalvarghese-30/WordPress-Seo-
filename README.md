# StillWorks HTML & PHP Sitemap Sync

StillWorks HTML & PHP Sitemap Sync is a WordPress Must-Use (MU) plugin that automatically discovers standalone static **HTML and PHP location pages** (including folder-based `index.php` clean URLs) from any custom directory on your server (such as `/location/`, `/locations/`, `/test-locations/`, `/xyz/`, etc.) and seamlessly integrates them directly into Rank Math SEO as a dedicated **`/html-sitemap.xml`** child sitemap.

It includes **full administrator access controls** directly inside the WordPress admin dashboard (**Tools > HTML Sitemap Sync**), allowing administrators to toggle individual pages, exclude folders, or exclude specific filenames with zero coding.

---

## Table of Contents

- [A. Project Overview](#a-project-overview)
- [B. Features](#b-features)
- [C. Project Structure](#c-project-structure)
- [D. Requirements](#d-requirements)
- [E. Installation Guide](#e-installation-guide)
- [F. Administrator Access Controls](#f-administrator-access-controls)
- [G. Adding Location Pages (HTML, PHP & Clean URLs)](#g-adding-location-pages-html-php--clean-urls)
- [H. How to Run and Synchronize](#h-how-to-run-and-synchronize)
- [I. Sitemap URLs and Verification](#i-sitemap-urls-and-verification)
- [J. Optional Locations Hub Companion Plugin](#j-optional-locations-hub-companion-plugin)
- [K. Troubleshooting](#k-troubleshooting)
- [L. Security and Best Practices](#l-security-and-best-practices)
- [M. Uninstallation](#m-uninstallation)

---

## A. Project Overview

### The Problem It Solves
WordPress and SEO plugins (including Rank Math) generate XML sitemaps exclusively by querying database records (`wp_posts`, `wp_terms`). If you deploy standalone landing pages—either as static `.html` files or as lightweight `.php` scripts in directories like `/location/yavatmal/index.php`—they bypass the database for high performance and low server overhead. However, standard SEO plugins are completely unaware of these files, leaving them out of your XML sitemaps.

### How StillWorks Bridges the Gap
1. **Multi-Directory & PHP Discovery:** Automatically scans custom directories under your WordPress root for `.html` and `.php` files.
2. **Clean URL Directory Index Handling:** Automatically detects folder-based index files (`/location/yavatmal/index.php`) and outputs the clean trailing-slash directory URL (`https://example.com/location/yavatmal/`).
3. **Dedicated Child Sitemap (`/html-sitemap.xml`):** Integrates with Rank Math via the `rank_math/sitemap/providers` filter to expose `/html-sitemap.xml`, automatically linked from your primary `/sitemap_index.xml`.
4. **Interactive Admin Controls:** Provides a dashboard under **Tools > HTML Sitemap Sync** with per-page inclusion checkboxes and folder/file rule lists.
5. **Change Detection & Targeted Invalidation:** Whenever files or settings are updated, it flushes only the root sitemap and HTML sitemap cache files, keeping standard post and page caches intact.

---

## B. Features

- **HTML & PHP File Support:** Detects both `.html` and `.php` standalone files across any folder.
- **Clean Folder URLs:** Maps `folder/index.php` and `folder/index.html` to clean directory URLs (`https://example.com/folder/`).
- **WordPress Core Protection:** Skips system directories (`wp-admin`, `wp-includes`, `wp-content`, `backups`, `.git`, `node_modules`, `vendor`) and ignores root-level `.php` files (e.g. `wp-login.php`, `wp-config.php`) so WordPress core files are never indexed.
- **Admin Dashboard Controls:** Visual checkboxes to include or exclude any discovered page directly from the WordPress admin (**Tools > HTML Sitemap Sync**).
- **Rule-Based Exclusion Lists:** Admin settings for custom excluded folders and excluded filenames.
- **Dedicated Child Sitemap:** Exposed as `/html-sitemap.xml` inside `/sitemap_index.xml`.
- **Path Traversal Protection:** Validates canonical filesystem paths (`realpath`) and ignores symlinks to prevent path traversal outside WordPress root.
- **Default Utility Filtering:** Automatically excludes non-landing files (`404.html`, `404.php`, `thank-you.html`, `thank-you.php`, `readme.html`, `license.txt`).
- **Filesystem Timestamps:** Formats `filemtime` into ISO-8601 UTC dates (`Y-m-d H:i:s`).
- **Clean URL Encoding:** Safely encodes nested path segments using `rawurlencode`.
- **Targeted Cache Invalidation:** Flushes only affected sitemaps upon detected change or admin settings save.
- **WP-Cron & Manual Sync:** Automatic 15-minute cron schedule and admin dashboard **Sync Now** / **Save Settings & Sync** actions.
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
│   └── html-sitemap-provider.php    # Primary WordPress Must-Use plugin (with Admin UI)
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
| **Web Server** | Nginx or Apache | Configured to serve static `.html` and `.php` files |
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
If you want to display your location pages in a frontend grid via shortcode:
1. Copy `companion/stillworks-locations-hub.php` into `wp-content/plugins/` (or `wp-content/mu-plugins/`):
   ```bash
   cp companion/stillworks-locations-hub.php wp-content/plugins/
   ```
2. Activate **StillWorks Locations Hub** in **WordPress Admin > Plugins**.

### Step 3: Verify Rank Math Setup
- Go to **Rank Math > Dashboard** and ensure the **Sitemap** module is enabled.
- Verify plugin loading in **WordPress Admin > Plugins > Must-Use**.

---

## F. Administrator Access Controls

Administrators have complete visual control over what appears in `/html-sitemap.xml`:

1. Navigate to **WordPress Admin > Tools > HTML Sitemap Sync**.
2. **Per-Page Checkboxes:**
   - Every detected `.html` and `.php` file across your custom folders is listed with its file path, public URL, and last modified date.
   - Simply **check** the pages you want in the sitemap, and **uncheck** any pages you want excluded.
3. **Custom Excluded Folders:**
   - Add folder names (one per line) in the "Custom Excluded Folders" box to ignore entire directories (e.g. `private`, `drafts`, `temp`).
4. **Excluded Filenames:**
   - Add filenames (one per line) in the "Excluded Filenames" box to ignore specific files anywhere on the site.
5. Click **Save Settings & Sync Sitemap**.
   - Your preferences are saved, the Rank Math cache is immediately purged, and `/html-sitemap.xml` is updated live.

---

## G. Adding Location Pages (HTML, PHP & Clean URLs)

The plugin supports all common folder structures:

### 1. Folder-Based Clean URLs (Recommended for PHP)
Create a directory with an `index.php` or `index.html` file inside:
- `location/yavatmal/index.php` &rarr; `https://example.com/location/yavatmal/`
- `location/yavatmal/car-scrapping/index.php` &rarr; `https://example.com/location/yavatmal/car-scrapping/`

### 2. Direct File URLs
Place standalone `.html` or `.php` files inside any folder:
- `locations/delhi.html` &rarr; `https://example.com/locations/delhi.html`
- `location/pune.php` &rarr; `https://example.com/location/pune.php`

---

## H. How to Run and Synchronize

- **Automated Sync (WP-Cron):** Runs every 15 minutes via the hook `stillworks_html_sitemap_cron_sync`.
- **Request-Based Detection:** Evaluates changes on WordPress `init` (priority 99).
- **Manual Trigger:** Go to **Tools > HTML Sitemap Sync** in the WordPress admin and click **Sync Now** or **Save Settings & Sync Sitemap**.
- **System Cron Recommendation:** For sites with low traffic, disable default WP-Cron (`define('DISABLE_WP_CRON', true);`) and run `php /var/www/html/wp-cron.php` via Linux crontab every 15 minutes.

---

## I. Sitemap URLs and Verification

- **Root Sitemap Index:**
  ```text
  https://example.com/sitemap_index.xml
  ```
- **Child Sitemap:**
  ```text
  https://example.com/html-sitemap.xml
  ```

---

## J. Optional Locations Hub Companion Plugin

The repository includes a companion plugin at [`companion/stillworks-locations-hub.php`](file:///c:/Users/Amal%20Varghese/Desktop/WordPress-HTML-Sitemap-Demo/companion/stillworks-locations-hub.php).

### Features:
- Shortcode: `[stillworks_locations_hub]`
- Bounded read: Safely reads at most 2048 bytes of the file header to extract `<title>` without executing or loading the page.
- Clean Title Fallback: For `index.php` files, automatically formats the parent folder name into a human-readable title (e.g. `car-scrapping` &rarr; "Car Scrapping").
- Responsive card grid displaying page titles, last-modified dates, and direct links.

---

## K. Troubleshooting

| Issue | Cause | Solution |
|---|---|---|
| **PHP location page not appearing** | File extension or naming. | Make sure the file has `.php` or `.html` extension. If it is `index.php`, ensure it is inside a subfolder (e.g. `location/yavatmal/index.php`), not in the root directory. |
| **`/html-sitemap.xml` returns 404** | Rewrite rules cached. | Go to **Settings > Permalinks** and click **Save Changes**. Verify Rank Math Sitemap module is active. |
| **Page marked "Excluded" in Admin** | Unchecked in dashboard or matches filename rule. | Go to **Tools > HTML Sitemap Sync**, check the box next to the page, and click **Save Settings & Sync Sitemap**. |
| **New folder on Docker host not appearing** | Folder not mounted in container. | Add the folder to `volumes:` in `docker-compose.yml`, or place subfolders inside `locations/`. |

---

## L. Security and Best Practices

1. **Safe Permissions:** Keep directories at `755` and files at `644`. Never use `777`.
2. **WordPress Core PHP Isolation:** The plugin explicitly ignores root `.php` files to ensure sensitive core files (like `wp-config.php`, `wp-login.php`, `wp-cron.php`) are never scanned or indexed.
3. **Access Control:** Settings saving and manual synchronization require the `manage_options` capability and are protected with WordPress security nonces.
4. **Credential Isolation:** Never commit `.env` files or database dumps.
