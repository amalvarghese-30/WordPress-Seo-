# StillWorks HTML Sitemap Sync

StillWorks HTML Sitemap Sync is a WordPress Must-Use (MU) plugin that automatically discovers standalone static HTML pages from any custom directory on your server (such as `/locations/`, `/test-locations/`, `/xyz/`, or root-level `.html` files) and integrates them directly into Rank Math SEO as a dedicated **`/html-sitemap.xml`** child sitemap.

It includes **full administrator access controls** directly inside the WordPress admin dashboard (**Tools > HTML Sitemap Sync**), allowing administrators to toggle individual pages, exclude folders, or exclude specific filenames with zero coding.

---

## Table of Contents

- [A. Project Overview](#a-project-overview)
- [B. Features](#b-features)
- [C. Project Structure](#c-project-structure)
- [D. Requirements](#d-requirements)
- [E. Installation Guide](#e-installation-guide)
- [F. Administrator Access Controls](#f-administrator-access-controls)
- [G. Adding HTML Pages in Any Folder](#g-adding-html-pages-in-any-folder)
- [H. How to Run and Synchronize](#h-how-to-run-and-synchronize)
- [I. Sitemap URLs and Verification](#i-sitemap-urls-and-verification)
- [J. Optional Locations Hub Companion Plugin](#j-optional-locations-hub-companion-plugin)
- [K. Troubleshooting](#k-troubleshooting)
- [L. Security and Best Practices](#l-security-and-best-practices)
- [M. Uninstallation](#m-uninstallation)

---

## A. Project Overview

### The Problem It Solves
WordPress and SEO plugins (including Rank Math) generate XML sitemaps exclusively by querying database records (`wp_posts`, `wp_terms`). If you deploy standalone static HTML landing pages—whether in `/locations/`, `/test-locations/`, `/xyz/`, or custom subfolders—they bypass the database for high performance and low server overhead. However, standard SEO plugins are completely unaware of these files, leaving them out of your XML sitemaps.

### How StillWorks Bridges the Gap
1. **Multi-Folder Discovery:** Automatically scans any custom directory under your WordPress root as well as standalone root HTML files, safely skipping WordPress core folders (`wp-admin`, `wp-includes`, `wp-content`).
2. **Dedicated Child Sitemap (`/html-sitemap.xml`):** Integrates with Rank Math via the `rank_math/sitemap/providers` filter to expose `/html-sitemap.xml`, automatically linked from your primary `/sitemap_index.xml`.
3. **Interactive Admin Controls:** Provides a dashboard under **Tools > HTML Sitemap Sync** with per-page inclusion checkboxes and folder/file rule lists.
4. **Change Detection & Targeted Invalidation:** Whenever files or settings are updated, it flushes only the root sitemap and HTML sitemap cache files, keeping standard post and page caches intact.

---

## B. Features

- **Multi-Directory Discovery:** Discovers `.html` files in `/locations/`, `/test-locations/`, `/xyz/`, any newly created folder, and root-level static pages.
- **Admin Dashboard Controls:** Visual checkboxes to include or exclude any discovered page directly from the WordPress admin (**Tools > HTML Sitemap Sync**).
- **Rule-Based Exclusion Lists:** Admin settings for custom excluded folders and excluded filenames.
- **WordPress Core Protection:** Skips system directories (`wp-admin`, `wp-includes`, `wp-content`, `backups`, `.git`, `node_modules`, `vendor`) so internal files never leak into sitemaps.
- **Dedicated Child Sitemap:** Exposed as `/html-sitemap.xml` inside `/sitemap_index.xml`.
- **Path Traversal Protection:** Validates canonical filesystem paths (`realpath`) and ignores symlinks to prevent path traversal outside WordPress root.
- **Default File Filtering:** Automatically excludes non-landing files (`index.html`, `404.html`, `thank-you.html`, `readme.html`).
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

## F. Administrator Access Controls

Administrators have complete visual control over what appears in `/html-sitemap.xml`:

1. Navigate to **WordPress Admin > Tools > HTML Sitemap Sync**.
2. **Per-Page Checkboxes:**
   - Every detected HTML file across your folders is listed with its file path, public URL, and last modified date.
   - Simply **check** the pages you want in the sitemap, and **uncheck** any pages you want excluded.
3. **Custom Excluded Folders:**
   - Add folder names (one per line) in the "Custom Excluded Folders" box to ignore entire directories (e.g. `private`, `drafts`, `temp`).
4. **Excluded Filenames:**
   - Add filenames (one per line) in the "Excluded Filenames" box to ignore specific files anywhere on the site.
5. Click **Save Settings & Sync Sitemap**.
   - Your preferences are saved, the Rank Math cache is immediately purged, and `/html-sitemap.xml` is updated live.

---

## G. Adding HTML Pages in Any Folder

You can add standalone `.html` files in **any custom folder** or the WordPress root:

### Examples:
- `/locations/delhi.html` &rarr; `https://example.com/locations/delhi.html`
- `/test-locations/mumbai.html` &rarr; `https://example.com/test-locations/mumbai.html`
- `/landing/summer-sale.html` &rarr; `https://example.com/landing/summer-sale.html`
- `/custom-page.html` &rarr; `https://example.com/custom-page.html`

> **Note for Docker Users:** If you run WordPress inside Docker, make sure any new host folder is mapped in `docker-compose.yml` under `volumes:` (e.g. `./test-locations:/var/www/html/test-locations`), or place subfolders inside an already-mapped folder like `locations/` (e.g. `locations/test/`).

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
- **HTML Pages Child Sitemap:**
  ```text
  https://example.com/html-sitemap.xml
  ```

### What You Will See:
1. When viewing `sitemap_index.xml`, you will see:
   - `post-sitemap.xml`
   - `page-sitemap.xml`
   - `category-sitemap.xml`
   - **`html-sitemap.xml`**
2. When clicking into `html-sitemap.xml`, all included standalone HTML files from `/locations/`, `/test-locations/`, and other custom folders are listed with their full URLs and `<lastmod>` timestamps.

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
| **`/html-sitemap.xml` returns 404** | Rewrite rules cached. | Go to **Settings > Permalinks** and click **Save Changes**. Verify Rank Math Sitemap module is active. |
| **Page appears as "Excluded" in Admin** | Unchecked in dashboard or matches filename rule. | Go to **Tools > HTML Sitemap Sync**, check the box next to the page, and click **Save Settings & Sync Sitemap**. |
| **New folder on Docker host not appearing** | Folder not mounted in container. | Add the folder to `volumes:` in `docker-compose.yml`, or place subfolders inside `locations/`. |
| **Cache invalidation fails** | Permissions on cache directory. | Ensure the web server user has write permissions to `wp-content/uploads/rank-math/`. |

---

## L. Security and Best Practices

1. **Safe Permissions:** Keep directories at `755` and files at `644`. Never use `777`.
2. **Path Traversal Mitigation:** The plugin uses `realpath()` and validates that all files reside inside the canonical root, rejecting symbolic links.
3. **Access Control:** Settings saving and manual synchronization require the `manage_options` capability and are protected with WordPress security nonces.
4. **Credential Isolation:** Never commit `.env` files or database dumps.
