# StillWorks HTML Sitemap Sync

A lightweight, high-performance WordPress Must-Use (MU) plugin that automatically discovers standalone HTML pages on the filesystem and seamlessly registers them as a dedicated child sitemap in **Rank Math SEO**.

---

## Table of Contents

- [A. Project Overview](#a-project-overview)
- [B. Features](#b-features)
- [C. Project Structure](#c-project-structure)
- [D. Requirements](#d-requirements)
- [E. Installation Guide](#e-installation-guide)
- [F. Adding HTML Pages](#f-adding-html-pages)
- [G. How to Run and Synchronize](#g-how-to-run-and-synchronize)
- [H. Sitemap URLs and Verification](#h-sitemap-urls-and-verification)
- [I. Optional Locations Hub Status](#i-optional-locations-hub-status)
- [J. Troubleshooting](#j-troubleshooting)
- [K. Security and Best Practices](#k-security-and-best-practices)
- [L. Uninstallation](#l-uninstallation)
- [M. Testing and Validation](#m-testing-and-validation)

---

## A. Project Overview

### What StillWorks HTML Sitemap Sync Does
In many high-growth WordPress implementations, static or programmatic landing pages (such as city-specific or service-specific location pages) are generated as standalone `.html` files placed directly on the web server (e.g. `/locations/delhi.html`, `/locations/mumbai.html`).

Standard WordPress SEO plugins—including Rank Math—only query posts, custom post types, and taxonomies stored inside the WordPress database (`wp_posts` and `wp_terms`). Consequently, standalone static HTML files are completely invisible to standard XML sitemaps, leaving them unindexed by search engine crawlers unless discovered via external or manual backlinks.

**StillWorks HTML Sitemap Sync** bridges this gap:
1. It scans the `/locations/` directory recursively for `.html` files.
2. It registers a native custom sitemap provider with Rank Math (`StillWorks_HTML_Sitemap_Provider`).
3. It exposes a dedicated child XML sitemap (`/locations-sitemap.xml`) and links it inside the primary index (`/sitemap_index.xml`).
4. It monitors filesystem changes via a SHA-256 state signature and performs targeted cache invalidation whenever files are created, updated, or removed.

### Why Standalone HTML Pages?
- **Ultra-low TTFB & Blazing Speed:** Static HTML files are served directly by Apache, Nginx, or LiteSpeed without initiating PHP execution, loading WordPress core, or querying MySQL.
- **Zero Database Bloat:** Storing thousands of regional landing pages as custom post types or revisions can degrade database query performance and administrative responsiveness.
- **Resilience:** Static pages remain available even if the PHP-FPM process or database server is temporarily offline.

### Integration Workflow
```
[ Filesystem: /locations/*.html ]
             │
             ▼
[ Recursive Scanner & SHA-256 Signature Check ]
             │
   ┌─────────┴─────────┐
   │ Changes Detected? │
   └─────────┬─────────┘
             │ Yes
             ▼
[ Targeted Rank Math Cache Invalidation ]
             │
             ▼
[ Rank Math Sitemap Router (`locations-sitemap.xml`) ]
             │
             ▼
[ Root Sitemap Index (`/sitemap_index.xml`) ]
             │
             ▼
[ Search Engines / Google Search Console ]
```

---

## B. Features

The plugin includes only verified, production-tested functionality:

- **Automatic HTML Page Discovery:** Uses PHP's `RecursiveDirectoryIterator` and `RecursiveIteratorIterator` to detect `.html` files in the `locations/` directory.
- **Recursive Directory Support:** Discovers files located in the root `/locations/` folder as well as any subdirectories (e.g. `/locations/india/delhi.html`).
- **Path Traversal Protection:** Validates real filesystem paths against the canonical root directory (`realpath`) and discards symlinks to prevent directory traversal vulnerabilities.
- **Built-in File Filtering:** Automatically excludes system and utility files from sitemap inclusion (`index.html`, `404.html`, `thank-you.html`).
- **Accurate URL Encoding:** Generates clean, valid URLs via `rawurlencode` for multi-level paths.
- **Filesystem-Accurate Timestamps:** Reads `filemtime` for each file and formats last-modified dates to ISO-8601 UTC strings (`Y-m-d H:i:s`).
- **Rank Math Provider Integration:** Integrates into Rank Math's provider pipeline using the `rank_math/sitemap/providers` filter.
- **Automatic Multi-page Pagination:** Automatically partitions entries across paginated child sitemaps (`locations-sitemap.xml`, `locations-sitemap2.xml`, etc.) according to Rank Math's configured per-page entry limit.
- **Signature-Based Change Detection:** Calculates a SHA-256 fingerprint from sorted file paths, URLs, and modification timestamps.
- **Targeted Cache Invalidation:** Upon detected change, deletes only the root sitemap cache and the `locations` sitemap cache files, preserving cache files for standard posts, pages, and products.
- **Dual Synchronization Triggers:**
  - **WP-Cron Scheduled Event:** Recurring event `stillworks_html_sitemap_cron_sync` registered on a custom 15-minute schedule (`stillworks_15_minutes`).
  - **Request-Based Hook:** Runs on WordPress `init` (priority 99) to catch updates on page requests.
- **Admin Management Screen:** Dedicated UI under **Tools > HTML Sitemap Sync** displaying detected files, full URLs, UTC timestamps, sync status messages, and a manual **Sync Now** trigger protected by nonces and capability checks.

---

## C. Project Structure

```
WordPress-HTML-Sitemap-Demo/
├── .env.example              # Sanitized template for local Docker environment
├── .gitignore                # Git exclusions (credentials, SQL dumps, backups, logs)
├── docker-compose.yml        # Local development environment (MariaDB 11.8 + WordPress php8.4)
├── README.md                 # Project documentation and operational guide
├── locations/                # Standalone HTML files directory (served at /locations/)
│   ├── bangalore.html        # Sample location page
│   ├── delhi.html            # Sample location page
│   ├── dubai.html            # Sample location page
│   ├── mumbai.html           # Sample location page
│   └── pune.html             # Sample location page
└── mu-plugins/               # WordPress Must-Use plugins directory
    └── html-sitemap-provider.php # Primary plugin implementation file
```

### File & Directory Roles
- **`mu-plugins/html-sitemap-provider.php`**: The entire core plugin. Must be installed into `wp-content/mu-plugins/` so it loads automatically on every WordPress request before standard plugins.
- **`locations/`**: The directory mapped directly under the WordPress root (served at `https://example.com/locations/`). Contains static HTML landing pages.
- **`docker-compose.yml`**: Optional local developer harness launching WordPress 6.x and MariaDB 11.8 to test sitemap generation and Rank Math integration locally.

---

## D. Requirements

| Component | Tested / Supported Version | Notes |
|---|---|---|
| **WordPress** | 6.0 - 6.7+ | Tested on WordPress 6.x |
| **PHP** | 7.4 - 8.4+ | Tested on PHP 8.3 & PHP 8.4 (ZTS / CLI / Apache module) |
| **Rank Math SEO** | 1.0.80+ (Free or Pro) | The **XML Sitemap** module in Rank Math must be enabled |
| **Web Server** | Apache 2.4+ / Nginx 1.18+ | Must be configured to serve `.html` files directly from `/locations/` |
| **File Permissions** | `755` (dirs) / `644` (files) | Web server user (`www-data` or `nginx`) requires read access to `/locations/` |
| **Dependencies** | None | Uses standard PHP SPL Iterators and core WordPress / Rank Math APIs |

---

## E. Installation Guide

Follow these steps to deploy StillWorks HTML Sitemap Sync on an existing or new WordPress installation.

### Step 1: Obtain the Project Files
Clone the repository or download the source code:
```bash
git clone https://github.com/amalvarghese-30/WordPress-Seo-.git
cd WordPress-Seo-
```

### Step 2: Install the MU-Plugin
1. Check if the `wp-content/mu-plugins/` directory exists in your WordPress installation. If it does not exist, create it:
   ```bash
   mkdir -p /path/to/wordpress/wp-content/mu-plugins
   ```
2. Copy `html-sitemap-provider.php` into the `mu-plugins` directory:
   ```bash
   cp mu-plugins/html-sitemap-provider.php /path/to/wordpress/wp-content/mu-plugins/
   ```

> **Why an MU-Plugin?**
> Must-Use (`mu-plugins`) plugins are activated automatically by WordPress, cannot be disabled accidentally from the admin dashboard, and load before standard plugins.

### Step 3: Create the Locations Directory
Create the `locations` directory at the WordPress root directory (the same directory containing `wp-config.php` and `wp-content/`):
```bash
mkdir -p /path/to/wordpress/locations
```
Set correct permissions so the web server can read the files:
```bash
chmod 755 /path/to/wordpress/locations
```

### Step 4: Verify Rank Math Setup
1. Ensure the **Rank Math SEO** plugin is installed and activated.
2. In the WordPress Admin, navigate to **Rank Math > Dashboard** and confirm that the **Sitemap** module is switched **ON**.
3. Under **Rank Math > Sitemap Settings > General**, ensure sitemaps are active.

### Step 5: Verify Plugin Activation
1. In the WordPress admin dashboard, go to **Plugins > Installed Plugins**.
2. Click on the **Must-Use** tab at the top. You should see **StillWorks HTML Sitemap Sync** listed with version `1.1.0`.
3. Check **Tools > HTML Sitemap Sync** in the admin sidebar.

---

## F. Adding HTML Pages

### Placement and File Structure
Place standalone `.html` files inside the `/locations/` folder at the WordPress root.

Example:
```
/var/www/html/locations/
├── delhi.html
├── mumbai.html
└── south/
    └── bangalore.html
```

### URL Construction
The plugin automatically maps the filesystem hierarchy to the website URL:
- `/locations/delhi.html` → `https://example.com/locations/delhi.html`
- `/locations/south/bangalore.html` → `https://example.com/locations/south/bangalore.html`

Special characters and spaces are safely converted using `rawurlencode`.

### Excluded Files
Files named exactly as follows are skipped automatically and will **not** appear in the XML sitemap:
- `index.html`
- `404.html`
- `thank-you.html`

### Sample HTML File (`delhi.html`)
```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Web Development Services in Delhi</title>
    <meta name="description" content="Professional web development and digital solutions in Delhi.">
</head>
<body>
    <h1>Web Development Services in Delhi</h1>
    <p>Welcome to our Delhi location page. We provide cutting-edge web development, automation, and digital solutions.</p>
</body>
</html>
```

### Modification Timestamps
The plugin reads the operating system modification date (`filemtime`) of each HTML file. Whenever you edit or re-upload a file:
- The last-modified timestamp in the sitemap updates to the file's current UTC modification time.
- The change detection hash triggers targeted cache invalidation.

---

## G. How to Run and Synchronize

### Automatic Synchronization
The plugin incorporates two layers of automatic synchronization:
1. **WP-Cron Recurring Event:** Runs every 15 minutes (`stillworks_html_sitemap_cron_sync`).
2. **Request-Based Detection:** Evaluates on WordPress `init` (priority 99).

### Real System Cron vs. WP-Cron
Default WordPress cron requires incoming HTTP visitor traffic to trigger tasks. For reliable execution on low-traffic or staging sites:
1. Add `define( 'DISABLE_WP_CRON', true );` to `wp-config.php`.
2. Configure a Linux system cron job to execute WordPress cron every 5 to 15 minutes:
   ```bash
   */15 * * * * /usr/bin/php /var/www/html/wp-cron.php >/dev/null 2>&1
   ```

### Manual Synchronization
Administrators can force an immediate synchronization at any time:
1. Go to **WordPress Admin > Tools > HTML Sitemap Sync**.
2. Click **Sync Now**.
3. The page will refresh and display one of the following notices:
   - `Changes detected. Rank Math sitemap cache invalidated.`
   - `Sync completed. No file changes were detected.`
   - `Initial sitemap signature created.`

### How Cache Invalidation Works
When the SHA-256 signature changes:
- Only `rank_math_<hash(root)>.xml` (the root sitemap index) and `rank_math_<hash(locations)>.xml` (the locations child sitemap) are deleted from the Rank Math cache directory (`wp-content/uploads/rank-math/` or designated cache folder).
- Rank Math's internal cache registry is pruned specifically for the `locations` type.
- Post, page, category, and product sitemap caches remain undisturbed.

---

## H. Sitemap URLs and Verification

### Expected Sitemap URLs
- **Main Sitemap Index:**
  ```
  https://example.com/sitemap_index.xml
  ```
- **Custom Child Sitemap:**
  ```
  https://example.com/locations-sitemap.xml
  ```
  *(If your location files exceed Rank Math's "Links per sitemap" setting, additional child sitemaps like `locations-sitemap2.xml` will be generated automatically).*

### What to Verify
1. Open `https://example.com/sitemap_index.xml` in your browser. You should see a `<sitemap>` entry pointing to `/locations-sitemap.xml`:
   ```xml
   <sitemap>
       <loc>https://example.com/locations-sitemap.xml</loc>
       <lastmod>2026-11-25T06:00:00+00:00</lastmod>
   </sitemap>
   ```
2. Open `https://example.com/locations-sitemap.xml`. You should see an entry for each eligible HTML file:
   ```xml
   <url>
       <loc>https://example.com/locations/delhi.html</loc>
       <lastmod>2026-11-25T06:00:00+00:00</lastmod>
   </url>
   ```

### Google Search Console Submission
1. Log in to [Google Search Console](https://search.google.com/search-console).
2. Select your property.
3. In the left navigation, click **Sitemaps**.
4. Under **Add a new sitemap**, enter `sitemap_index.xml` and click **Submit**.

> **Note on Indexing:** Submitting a sitemap informs search engine crawlers of the existence and modification timestamps of your URLs. It does not guarantee immediate crawling or indexing, which is governed by Google's crawl budget, content quality checks, and search algorithms.

---

## I. Optional Locations Hub Status

This repository contains the standalone XML sitemap synchronization engine (`mu-plugins/html-sitemap-provider.php`). 

> [!NOTE]
> This codebase focuses strictly on the **backend XML sitemap generation and Rank Math integration**. It does not contain a separate frontend HTML shortcode or directory template (such as a `[locations_hub]` shortcode). If a frontend directory of locations is required, it can be implemented as a separate shortcode plugin querying `/locations/` or through custom WordPress page templates.

---

## J. Troubleshooting

| Issue | Cause | Solution |
|---|---|---|
| **MU-plugin not appearing in Admin** | File placed in wrong directory. | Ensure the file is placed at `wp-content/mu-plugins/html-sitemap-provider.php`, not in a subfolder inside `mu-plugins`. |
| **`/locations-sitemap.xml` returns 404** | Rank Math rewrite rules not flushed. | Go to **WordPress Admin > Settings > Permalinks** and click **Save Changes** to flush rewrite rules. Confirm Rank Math's Sitemap module is active. |
| **Newly added HTML files not showing** | Cache has not refreshed yet. | Go to **Tools > HTML Sitemap Sync** and click **Sync Now**. If still absent, verify the file has `.html` extension and is not named `index.html`, `404.html`, or `thank-you.html`. |
| **"The locations directory does not exist"** | Directory missing or permission denied. | Create `locations/` at the WordPress root directory and ensure the web server user has read and execute permissions (`chmod 755 locations`). |
| **Rank Math sitemap cache invalidation failed** | Cache directory write permissions. | Verify that PHP can delete files in `wp-content/uploads/rank-math/` (`chmod 775` or `755` depending on server ownership). |
| **Static HTML pages return 404** | Web server routing rules. | Ensure Apache or Nginx is configured to serve existing static files directly before passing requests to WordPress `index.php`. |
| **WP-Cron scheduled sync not running** | Low site traffic or WP-Cron disabled. | Trigger manual sync via **Tools > HTML Sitemap Sync**, or configure a system cron to call `wp-cron.php`. |

---

## K. Security and Best Practices

1. **Strict File Permissions:**
   - Directories: `755` (`drwxr-xr-x`)
   - Files: `644` (`-rw-r--r--`)
   - **Never run `chmod -R 777`** on production web servers. Overly permissive file rights allow malicious actors to inject unauthorized code or overwrite HTML pages.
2. **Treat Uploaded HTML Pages as Trusted Code:**
   - Static `.html` files can execute client-side JavaScript and render external forms. Only trusted site developers or administrators should be allowed to upload or commit files to `/locations/`.
   - Never allow unauthenticated or untrusted users to upload HTML files to this folder.
3. **Directory Traversal Mitigation:**
   - The plugin strictly validates canonical paths using `realpath()` and verifies that resolved paths begin with the base directory path followed by the directory separator. Symlinks are explicitly ignored (`$file->isLink()`).
4. **Environment & Credential Isolation:**
   - Never commit `.env` files, database dumps (`.sql`), or server backups to public or shared Git repositories.
   - Use `.env.example` as a template for local development variables.
5. **Backups:**
   - Always create a complete snapshot of `wp-content/` and your database before installing or upgrading WordPress MU-plugins.

---

## L. Uninstallation

To safely remove StillWorks HTML Sitemap Sync from your site:

1. Remove the MU-plugin file:
   ```bash
   rm /path/to/wordpress/wp-content/mu-plugins/html-sitemap-provider.php
   ```
2. Clear the Rank Math sitemap cache:
   - In WordPress admin, go to **Rank Math > Sitemap Settings**, toggle an option (e.g., change links per sitemap temporarily) and save to flush the cache.
   - Or delete cached XML files directly from `wp-content/uploads/rank-math/`.
3. (Optional) Remove the signature option from the database using WP-CLI or phpMyAdmin:
   ```bash
   wp option delete stillworks_html_sitemap_signature
   ```
4. **HTML Files Preserved:** Removing the MU-plugin will stop sitemap generation for `/locations/`, but will **not** delete your static HTML files in the `/locations/` directory.

---

## M. Testing and Validation

### 1. PHP Syntax Verification
Validate that the plugin file has no syntax errors:
```bash
php -l mu-plugins/html-sitemap-provider.php
# Expected output: No syntax errors detected in mu-plugins/html-sitemap-provider.php
```

### 2. Verify Must-Use Plugin Loading
In WP-CLI or WordPress Admin:
```bash
wp plugin list --must-use
```
Look for:
```
+----------------------------+--------+---------+---------+
| name                       | status | update  | version |
+----------------------------+--------+---------+---------+
| html-sitemap-provider.php  | active | none    | 1.1.0   |
+----------------------------+--------+---------+---------+
```

### 3. Validate Sitemap Generation
Use `curl` to inspect the live sitemap responses:
```bash
# Verify child sitemap is included in index:
curl -s https://example.com/sitemap_index.xml | grep "locations-sitemap.xml"

# Verify child sitemap lists your HTML pages:
curl -s https://example.com/locations-sitemap.xml | grep "<loc>"
```

### 4. Test Change Invalidation
1. Add a test file:
   ```bash
   echo "<html><body>Test</body></html>" > locations/test-location.html
   ```
2. Run manual sync or trigger `init` request.
3. Verify that `locations/test-location.html` appears in `/locations-sitemap.xml`.
4. Remove the test file:
   ```bash
   rm locations/test-location.html
   ```
5. Trigger sync and verify that the entry is purged from the XML sitemap.

---

## License

This project is released under the [MIT License](LICENSE).
