<?php
/**
 * Plugin Name: StillWorks HTML Sitemap Sync
 * Description: Discovers standalone HTML pages from any folder and integrates them with Rank Math as an HTML child sitemap with admin controls.
 * Version: 1.3.0
 * Author: StillWorks
 */

defined( 'ABSPATH' ) || exit;

final class StillWorks_HTML_Sitemap_Sync {

    /**
     * Permanent core system directories that must always be excluded.
     */
    private const CORE_EXCLUDED_DIRS = [
        'wp-admin',
        'wp-includes',
        'wp-content',
        'backups',
        '.git',
        '.github',
        'node_modules',
        'vendor',
    ];

    /**
     * Default filenames to exclude from sitemap inclusion.
     */
    private const DEFAULT_EXCLUDED_FILES = [
        'index.html',
        '404.html',
        'thank-you.html',
        'readme.html',
    ];

    private const SIGNATURE_OPTION = 'stillworks_html_sitemap_signature';
    private const SETTINGS_OPTION  = 'stillworks_html_sitemap_settings';

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'register_admin_page' ] );

        // Keep request-based automatic change detection.
        add_action( 'init', [ __CLASS__, 'sync_cache_signature' ], 99 );

        // Schedule periodic synchronization.
        add_filter( 'cron_schedules', [ __CLASS__, 'register_cron_interval' ] );
        add_action( 'init', [ __CLASS__, 'schedule_sync' ], 100 );
        add_action(
            'stillworks_html_sitemap_cron_sync',
            [ __CLASS__, 'sync_cache_signature' ]
        );

        // Secure manual synchronization.
        add_action(
            'admin_post_stillworks_html_sitemap_manual_sync',
            [ __CLASS__, 'handle_manual_sync' ]
        );

        // Secure admin settings save.
        add_action(
            'admin_post_stillworks_html_sitemap_save_settings',
            [ __CLASS__, 'handle_save_settings' ]
        );

        add_filter(
            'rank_math/sitemap/providers',
            [ __CLASS__, 'register_provider' ]
        );
    }

    /**
     * Retrieve stored settings with safe defaults.
     */
    public static function get_settings() {
        $defaults = [
            'custom_excluded_dirs'  => [],
            'custom_excluded_files' => self::DEFAULT_EXCLUDED_FILES,
            'excluded_paths'        => [],
        ];

        $saved = get_option( self::SETTINGS_OPTION, [] );
        if ( ! is_array( $saved ) ) {
            $saved = [];
        }

        return wp_parse_args( $saved, $defaults );
    }

    /**
     * Register the custom provider.
     */
    public static function register_provider( $providers ) {

        if ( ! class_exists( '\RankMath\Sitemap\Router' ) ) {
            return $providers;
        }

        $providers[] = new StillWorks_HTML_Sitemap_Provider();

        return $providers;
    }

    /**
     * Scan directories and return all discovered HTML files.
     *
     * @param bool $all If true, returns all discovered files with an 'included' flag. If false, returns only included files.
     * @return array
     */
    public static function scan_files( $all = false ) {

        $root = realpath( ABSPATH );

        if ( false === $root || ! is_dir( $root ) ) {
            return [
                'files' => [],
                'error' => 'The WordPress root directory could not be resolved.',
            ];
        }

        $settings = self::get_settings();

        // Merge core excluded dirs with admin-configured custom excluded dirs
        $excluded_dirs = array_unique( array_merge(
            self::CORE_EXCLUDED_DIRS,
            (array) $settings['custom_excluded_dirs']
        ) );
        $excluded_dirs = (array) apply_filters( 'stillworks_html_sitemap_excluded_dirs', $excluded_dirs );

        // Merge default excluded files with admin-configured excluded files
        $excluded_files = array_unique( array_merge(
            self::DEFAULT_EXCLUDED_FILES,
            (array) $settings['custom_excluded_files']
        ) );
        $excluded_files = (array) apply_filters( 'stillworks_html_sitemap_excluded_files', $excluded_files );

        $excluded_paths = (array) ( $settings['excluded_paths'] ?? [] );
        $allowed_dirs   = (array) apply_filters( 'stillworks_html_sitemap_allowed_dirs', [] );

        $excluded_dirs_map  = array_map( 'strtolower', $excluded_dirs );
        $excluded_files_map = array_map( 'strtolower', $excluded_files );
        $excluded_paths_map = array_map( 'strtolower', $excluded_paths );
        $allowed_dirs_map   = array_filter( array_map( 'strtolower', $allowed_dirs ) );

        $files = [];

        try {
            $root_items = scandir( $root );

            if ( false !== $root_items ) {
                foreach ( $root_items as $item ) {
                    if ( '.' === $item || '..' === $item ) {
                        continue;
                    }

                    $full_item_path = $root . DIRECTORY_SEPARATOR . $item;

                    // 1. Standalone HTML file in the WordPress root directory
                    if ( is_file( $full_item_path ) && ! is_link( $full_item_path ) ) {
                        if ( 'html' === strtolower( pathinfo( $item, PATHINFO_EXTENSION ) ) ) {
                            $is_file_excluded = in_array( strtolower( $item ), $excluded_files_map, true );
                            $is_path_excluded = in_array( strtolower( $item ), $excluded_paths_map, true );
                            $filter_allowed   = apply_filters( 'stillworks_html_sitemap_include_file', true, $item, $full_item_path );

                            $is_included = ( ! $is_file_excluded && ! $is_path_excluded && $filter_allowed );

                            if ( $all || $is_included ) {
                                $modified = filemtime( $full_item_path );
                                if ( false !== $modified ) {
                                    $encoded_path = rawurlencode( $item );
                                    $files[] = [
                                        'file'      => $item,
                                        'url'       => home_url( '/' . $encoded_path ),
                                        'timestamp' => $modified,
                                        'lastmod'   => gmdate( 'Y-m-d H:i:s', $modified ),
                                        'included'  => $is_included,
                                        'reason'    => $is_file_excluded ? 'Filename rule' : ( $is_path_excluded ? 'Admin unselected' : '' ),
                                    ];
                                }
                            }
                        }
                        continue;
                    }

                    // 2. Scan subdirectories (locations, test-locations, xyz, landing, etc.)
                    if ( is_dir( $full_item_path ) && ! is_link( $full_item_path ) ) {
                        $dir_name_lower = strtolower( $item );

                        // Skip core and custom excluded directories
                        if ( in_array( $dir_name_lower, $excluded_dirs_map, true ) ) {
                            continue;
                        }

                        // If whitelist filter is provided, enforce it
                        if ( ! empty( $allowed_dirs_map ) && ! in_array( $dir_name_lower, $allowed_dirs_map, true ) ) {
                            continue;
                        }

                        $iterator = new RecursiveIteratorIterator(
                            new RecursiveDirectoryIterator(
                                $full_item_path,
                                FilesystemIterator::SKIP_DOTS
                            )
                        );

                        foreach ( $iterator as $file ) {
                            if ( ! $file->isFile() || $file->isLink() ) {
                                continue;
                            }

                            if ( 'html' !== strtolower( $file->getExtension() ) ) {
                                continue;
                            }

                            $real_path = $file->getRealPath();
                            if ( false === $real_path ) {
                                continue;
                            }

                            // Ensure resolved path stays within the WordPress root
                            if ( 0 !== strpos( $real_path, $root . DIRECTORY_SEPARATOR ) ) {
                                continue;
                            }

                            $relative_path = str_replace(
                                DIRECTORY_SEPARATOR,
                                '/',
                                substr( $real_path, strlen( $root ) + 1 )
                            );

                            $base_name = strtolower( basename( $relative_path ) );
                            $is_file_excluded = in_array( $base_name, $excluded_files_map, true );
                            $is_path_excluded = in_array( strtolower( $relative_path ), $excluded_paths_map, true );
                            $filter_allowed   = apply_filters( 'stillworks_html_sitemap_include_file', true, $relative_path, $real_path );

                            $is_included = ( ! $is_file_excluded && ! $is_path_excluded && $filter_allowed );

                            if ( $all || $is_included ) {
                                $modified = filemtime( $real_path );
                                if ( false !== $modified ) {
                                    $encoded_path = implode(
                                        '/',
                                        array_map(
                                            'rawurlencode',
                                            explode( '/', $relative_path )
                                        )
                                    );

                                    $files[] = [
                                        'file'      => $relative_path,
                                        'url'       => home_url( '/' . $encoded_path ),
                                        'timestamp' => $modified,
                                        'lastmod'   => gmdate( 'Y-m-d H:i:s', $modified ),
                                        'included'  => $is_included,
                                        'reason'    => $is_file_excluded ? 'Filename rule' : ( $is_path_excluded ? 'Admin unselected' : '' ),
                                    ];
                                }
                            }
                        }
                    }
                }
            }

        } catch ( Throwable $exception ) {
            return [
                'files' => [],
                'error' => 'Unable to scan directories: ' . $exception->getMessage(),
            ];
        }

        usort(
            $files,
            static function ( $a, $b ) {
                return strcmp( $a['file'], $b['file'] );
            }
        );

        return [
            'files' => $files,
            'error' => null,
        ];
    }

    /**
     * Detect changes to discovered files and invalidate Rank Math caches.
     */
    public static function sync_cache_signature() {

        if ( ! class_exists( '\RankMath\Sitemap\Cache' ) ) {
            return 'rank_math_unavailable';
        }

        // Only include active, included files in the signature
        $result = self::scan_files( false );

        if ( ! empty( $result['error'] ) ) {
            return 'scan_error';
        }

        $signature_data = [];

        foreach ( $result['files'] as $file ) {
            $signature_data[] = [
                'file'      => $file['file'],
                'url'       => $file['url'],
                'timestamp' => $file['timestamp'],
            ];
        }

        $signature = hash(
            'sha256',
            wp_json_encode( $signature_data )
        );

        $previous = get_option( self::SIGNATURE_OPTION, false );

        // Establish initial baseline
        if ( false === $previous ) {
            add_option( self::SIGNATURE_OPTION, $signature, '', false );
            return 'baseline_created';
        }

        if ( hash_equals( (string) $previous, $signature ) ) {
            return 'unchanged';
        }

        // Remove html child sitemap, legacy locations child sitemap, and root index
        $cache_directory = \RankMath\Sitemap\Cache::get_cache_directory();
        $registry = \RankMath\Sitemap\Cache::cached_files();

        if ( ! is_array( $registry ) ) {
            $registry = [];
        }

        $root_filename      = 'rank_math_' . md5( '1_1_' . home_url() ) . '.xml';
        $html_filename      = 'rank_math_' . md5( 'html_1_' . home_url() ) . '.xml';
        $locations_filename = 'rank_math_' . md5( 'locations_1_' . home_url() ) . '.xml';

        $files_to_delete = [
            $root_filename,
            $html_filename,
            $locations_filename,
        ];

        foreach ( $registry as $filename => $sitemap_type ) {
            if ( 'html' === (string) $sitemap_type || 'locations' === (string) $sitemap_type ) {
                $files_to_delete[] = $filename;
            }
        }

        $files_to_delete = array_unique( $files_to_delete );

        foreach ( $files_to_delete as $filename ) {
            if (
                ! is_string( $filename ) ||
                basename( $filename ) !== $filename ||
                0 !== strpos( $filename, 'rank_math_' ) ||
                '.xml' !== substr( $filename, -4 )
            ) {
                continue;
            }

            $cache_file = $cache_directory . $filename;

            if ( is_file( $cache_file ) && ! @unlink( $cache_file ) ) {
                error_log(
                    '[HTML-SITEMAP] Could not delete cache file: ' . $filename
                );
                return 'cache_invalidation_failed';
            }

            if ( is_file( $cache_file ) ) {
                return 'cache_invalidation_failed';
            }
        }

        // Clean registry
        foreach ( $registry as $filename => $sitemap_type ) {
            if (
                'html' === (string) $sitemap_type ||
                'locations' === (string) $sitemap_type ||
                $root_filename === $filename
            ) {
                unset( $registry[ $filename ] );
            }
        }

        \RankMath\Sitemap\Cache::cached_files( $registry );

        update_option( self::SIGNATURE_OPTION, $signature, false );

        return 'updated';
    }

    /**
     * Register a custom 15-minute WP-Cron interval.
     */
    public static function register_cron_interval( $schedules ) {

        if ( ! isset( $schedules['stillworks_15_minutes'] ) ) {
            $schedules['stillworks_15_minutes'] = [
                'interval' => 15 * MINUTE_IN_SECONDS,
                'display'  => 'Every 15 Minutes (StillWorks)',
            ];
        }

        return $schedules;
    }

    /**
     * Schedule recurring synchronization once.
     */
    public static function schedule_sync() {

        if ( ! wp_next_scheduled( 'stillworks_html_sitemap_cron_sync' ) ) {
            wp_schedule_event(
                time() + 60,
                'stillworks_15_minutes',
                'stillworks_html_sitemap_cron_sync'
            );
        }
    }

    /**
     * Handle the administrator's manual Sync Now request.
     */
    public static function handle_manual_sync() {

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'You do not have permission to perform this action.' );
        }

        check_admin_referer( 'stillworks_html_sitemap_manual_sync' );

        $status = self::sync_cache_signature();

        $redirect = add_query_arg(
            'sync_status',
            sanitize_key( $status ),
            admin_url( 'tools.php?page=html-sitemap-sync' )
        );

        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Handle saving admin inclusion / exclusion settings.
     */
    public static function handle_save_settings() {

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'You do not have permission to perform this action.' );
        }

        check_admin_referer( 'stillworks_html_sitemap_save_settings' );

        // 1. Process custom excluded directories
        $raw_dirs = isset( $_POST['custom_excluded_dirs'] ) ? sanitize_textarea_field( wp_unslash( $_POST['custom_excluded_dirs'] ) ) : '';
        $dirs_list = array_filter( array_map( 'trim', preg_split( '/[\r\n,]+/', $raw_dirs ) ) );
        $custom_excluded_dirs = array_map( 'sanitize_file_name', $dirs_list );

        // 2. Process custom excluded files
        $raw_files = isset( $_POST['custom_excluded_files'] ) ? sanitize_textarea_field( wp_unslash( $_POST['custom_excluded_files'] ) ) : '';
        $files_list = array_filter( array_map( 'trim', preg_split( '/[\r\n,]+/', $raw_files ) ) );
        $custom_excluded_files = array_map( 'sanitize_file_name', $files_list );

        // Always keep index.html and 404.html in the excluded list
        foreach ( [ 'index.html', '404.html' ] as $required_excluded ) {
            if ( ! in_array( $required_excluded, $custom_excluded_files, true ) ) {
                $custom_excluded_files[] = $required_excluded;
            }
        }

        // 3. Process individual page checkboxes
        $all_discovered = self::scan_files( true );
        $all_paths = array_column( $all_discovered['files'] ?? [], 'file' );

        $selected_paths = isset( $_POST['included_files'] ) && is_array( $_POST['included_files'] )
            ? array_map( 'sanitize_text_field', wp_unslash( $_POST['included_files'] ) )
            : [];

        // Any discovered path that was NOT checked by admin is placed in excluded_paths
        $excluded_paths = [];
        foreach ( $all_paths as $path ) {
            if ( ! in_array( $path, $selected_paths, true ) ) {
                $excluded_paths[] = $path;
            }
        }

        $new_settings = [
            'custom_excluded_dirs'  => array_values( array_unique( $custom_excluded_dirs ) ),
            'custom_excluded_files' => array_values( array_unique( $custom_excluded_files ) ),
            'excluded_paths'        => array_values( array_unique( $excluded_paths ) ),
        ];

        update_option( self::SETTINGS_OPTION, $new_settings, false );

        // Invalidate cache immediately so new selections reflect in sitemap
        self::sync_cache_signature();

        $redirect = add_query_arg(
            'sync_status',
            'settings_saved',
            admin_url( 'tools.php?page=html-sitemap-sync' )
        );

        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Register WordPress Tools menu.
     */
    public static function register_admin_page() {

        add_management_page(
            'HTML Sitemap Sync',
            'HTML Sitemap Sync',
            'manage_options',
            'html-sitemap-sync',
            [ __CLASS__, 'render_admin_page' ]
        );
    }

    /**
     * Display discovered files and admin controls in WordPress Admin.
     */
    public static function render_admin_page() {

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die(
                esc_html__(
                    'You do not have permission to access this page.'
                )
            );
        }

        $result   = self::scan_files( true ); // Retrieve all files for management
        $files    = $result['files'];
        $settings = self::get_settings();

        $included_count = count( array_filter( $files, static function( $f ) { return ! empty( $f['included'] ); } ) );

        ?>
        <div class="wrap">
            <h1>HTML Sitemap Sync &amp; Access Controls</h1>

            <p>
                Manage standalone HTML pages across all custom directories. Check the pages you want included in your <code>/html-sitemap.xml</code> child sitemap.
            </p>

            <?php
            $sync_messages = [
                'settings_saved' => 'Settings saved successfully! Rank Math sitemap cache invalidated and refreshed.',
                'updated' => 'Changes detected. Rank Math sitemap cache invalidated.',
                'unchanged' => 'Sync completed. No file changes were detected.',
                'baseline_created' => 'Initial sitemap signature created.',
                'scan_error' => 'Sync failed because directories could not be scanned.',
                'rank_math_unavailable' => 'Sync failed because Rank Math sitemap functionality is unavailable.',
            ];

            $sync_status = isset( $_GET['sync_status'] )
                ? sanitize_key( wp_unslash( $_GET['sync_status'] ) )
                : '';

            if ( isset( $sync_messages[ $sync_status ] ) ) :
            ?>
                <div class="notice notice-success is-dismissible">
                    <p><strong><?php echo esc_html( $sync_messages[ $sync_status ] ); ?></strong></p>
                </div>
            <?php endif; ?>

            <div style="background:#fff;border:1px solid #ccd0d4;padding:15px;margin:20px 0;border-radius:4px;display:flex;align-items:center;justify-content:space-between;">
                <div>
                    <p style="margin:0 0 5px 0;">
                        <strong>Sitemap URL:</strong>
                        <a href="<?php echo esc_url( home_url( '/html-sitemap.xml' ) ); ?>"
                           target="_blank" rel="noopener noreferrer" style="font-weight:600;">
                            <?php echo esc_html( home_url( '/html-sitemap.xml' ) ); ?>
                        </a>
                    </p>
                    <p style="margin:0;color:#666;">
                        Active pages in sitemap: <strong><?php echo (int) $included_count; ?></strong> of <?php echo count( $files ); ?> detected | Scheduled sync: Every 15 minutes (WP-Cron)
                    </p>
                </div>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:0;">
                    <input type="hidden" name="action" value="stillworks_html_sitemap_manual_sync">
                    <?php wp_nonce_field( 'stillworks_html_sitemap_manual_sync' ); ?>
                    <?php submit_button( 'Sync Now', 'secondary', 'submit', false ); ?>
                </form>
            </div>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="stillworks_html_sitemap_save_settings">
                <?php wp_nonce_field( 'stillworks_html_sitemap_save_settings' ); ?>

                <h2>Inclusion &amp; Exclusion Controls</h2>

                <?php if ( $result['error'] ) : ?>
                    <div class="notice notice-error">
                        <p><?php echo esc_html( $result['error'] ); ?></p>
                    </div>
                <?php else : ?>

                    <?php if ( empty( $files ) ) : ?>
                        <p>No standalone HTML files were discovered on the server.</p>
                    <?php else : ?>

                        <table class="widefat striped" style="margin-top:15px;">
                            <thead>
                                <tr>
                                    <th style="width:50px;text-align:center;">Include</th>
                                    <th>File Path</th>
                                    <th>Public Page URL</th>
                                    <th style="width:130px;">Status</th>
                                    <th style="width:160px;">Last Modified (UTC)</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php foreach ( $files as $item ) : ?>
                                    <tr>
                                        <td style="text-align:center;">
                                            <input type="checkbox"
                                                   name="included_files[]"
                                                   value="<?php echo esc_attr( $item['file'] ); ?>"
                                                   <?php checked( ! empty( $item['included'] ) ); ?>>
                                        </td>

                                        <td>
                                            <code><?php echo esc_html( $item['file'] ); ?></code>
                                        </td>

                                        <td>
                                            <a href="<?php echo esc_url( $item['url'] ); ?>"
                                               target="_blank" rel="noopener noreferrer">
                                                <?php echo esc_html( $item['url'] ); ?>
                                            </a>
                                        </td>

                                        <td>
                                            <?php if ( ! empty( $item['included'] ) ) : ?>
                                                <span style="color:#0a7a37;font-weight:600;">&#10003; Included</span>
                                            <?php else : ?>
                                                <span style="color:#a00;font-weight:600;">&times; Excluded</span>
                                                <?php if ( ! empty( $item['reason'] ) ) : ?>
                                                    <span style="font-size:11px;color:#888;">(<?php echo esc_html( $item['reason'] ); ?>)</span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <?php echo esc_html( $item['lastmod'] ); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                    <?php endif; ?>

                <?php endif; ?>

                <h3 style="margin-top:30px;">Rule-Based Exclusions (Optional)</h3>
                <p class="description">
                    Exclude entire folders or specific file names automatically. Enter one per line.
                </p>

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="custom_excluded_dirs">Custom Excluded Folders</label></th>
                        <td>
                            <textarea id="custom_excluded_dirs"
                                      name="custom_excluded_dirs"
                                      rows="3"
                                      class="large-text code"><?php echo esc_textarea( implode( "\n", (array) $settings['custom_excluded_dirs'] ) ); ?></textarea>
                            <p class="description">Folders entered here will be completely skipped (e.g. <code>private</code> or <code>draft-folder</code>). WordPress core directories (<code>wp-admin</code>, <code>wp-content</code>, etc.) are always protected automatically.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="custom_excluded_files">Excluded Filenames</label></th>
                        <td>
                            <textarea id="custom_excluded_files"
                                      name="custom_excluded_files"
                                      rows="3"
                                      class="large-text code"><?php echo esc_textarea( implode( "\n", (array) $settings['custom_excluded_files'] ) ); ?></textarea>
                            <p class="description">Filenames to ignore anywhere (e.g. <code>index.html</code>, <code>404.html</code>, <code>thank-you.html</code>, <code>readme.html</code>).</p>
                        </td>
                    </tr>
                </table>

                <?php submit_button( 'Save Settings & Sync Sitemap', 'primary', 'submit_settings' ); ?>
            </form>
        </div>
        <?php
    }
}

/**
 * Rank Math custom provider for standalone HTML pages.
 */
final class StillWorks_HTML_Sitemap_Provider {

    private const TYPE = 'html';

    /**
     * Tell Rank Math which sitemap type this provider handles.
     */
    public function handles_type( $type ) {
        return self::TYPE === $type;
    }

    /**
     * Add the child sitemap to Rank Math's sitemap index.
     */
    public function get_index_links( $max_entries ) {

        $result = StillWorks_HTML_Sitemap_Sync::scan_files( false );

        if ( ! empty( $result['error'] ) || empty( $result['files'] ) ) {
            return [];
        }

        $files = $result['files'];

        $max_entries = max( 1, (int) $max_entries );

        $page_count = (int) ceil( count( $files ) / $max_entries );

        $latest_timestamp = max(
            array_column( $files, 'timestamp' )
        );

        $index = [];

        for ( $page = 1; $page <= $page_count; $page++ ) {

            $suffix = ( 1 === $page ) ? '' : (string) $page;

            $index[] = [
                'loc' => \RankMath\Sitemap\Router::get_base_url(
                    self::TYPE . '-sitemap' . $suffix . '.xml'
                ),
                'lastmod' => gmdate(
                    'Y-m-d H:i:s',
                    $latest_timestamp
                ),
            ];
        }

        return $index;
    }

    /**
     * Return URL entries for the requested sitemap page.
     */
    public function get_sitemap_links( $type, $max_entries, $current_page ) {

        if ( ! $this->handles_type( $type ) ) {
            return [];
        }

        $result = StillWorks_HTML_Sitemap_Sync::scan_files( false );

        if ( ! empty( $result['error'] ) || empty( $result['files'] ) ) {
            return [];
        }

        $max_entries = max( 1, (int) $max_entries );
        $current_page = max( 1, (int) $current_page );

        $offset = ( $current_page - 1 ) * $max_entries;

        $page_files = array_slice(
            $result['files'],
            $offset,
            $max_entries
        );

        $links = [];

        foreach ( $page_files as $file ) {
            $links[] = [
                'loc' => $file['url'],
                'mod' => $file['lastmod'],
            ];
        }

        return $links;
    }
}

StillWorks_HTML_Sitemap_Sync::init();
