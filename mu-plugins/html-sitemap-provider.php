<?php
/**
 * Plugin Name: StillWorks HTML Sitemap Sync
 * Description: Discovers standalone HTML pages and integrates them with Rank Math.
 * Version: 1.1.0
 * Author: StillWorks
 */

defined( 'ABSPATH' ) || exit;

final class StillWorks_HTML_Sitemap_Sync {

    private const DIRECTORY = 'locations';

    private const EXCLUDED_FILES = [
        'index.html',
        '404.html',
        'thank-you.html',
    ];

    private const SIGNATURE_OPTION = 'stillworks_html_sitemap_signature';

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

        add_filter(
            'rank_math/sitemap/providers',
            [ __CLASS__, 'register_provider' ]
        );
    }

    /**
     * Register the custom provider.
     *
     * Do not implement Rank Math's interface directly here:
     * MU-plugins load before normal plugins, so its interface
     * may not yet be available when this file is loaded.
     */
    public static function register_provider( $providers ) {

        if ( ! class_exists( '\RankMath\Sitemap\Router' ) ) {
            return $providers;
        }

        $providers[] = new StillWorks_HTML_Sitemap_Provider();

        return $providers;
    }

    /**
     * Discover eligible HTML files inside /locations/.
     */
    public static function scan_files() {

        $root = realpath( ABSPATH . self::DIRECTORY );

        if ( false === $root || ! is_dir( $root ) ) {
            return [
                'files' => [],
                'error' => 'The locations directory does not exist.',
            ];
        }

        $files = [];

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $root,
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

                // Ensure the resolved file remains inside locations/.
                if ( 0 !== strpos( $real_path, $root . DIRECTORY_SEPARATOR ) ) {
                    continue;
                }

                $relative_path = str_replace(
                    DIRECTORY_SEPARATOR,
                    '/',
                    substr( $real_path, strlen( $root ) + 1 )
                );

                if ( in_array(
                    strtolower( basename( $relative_path ) ),
                    self::EXCLUDED_FILES,
                    true
                ) ) {
                    continue;
                }

                $modified = filemtime( $real_path );

                if ( false === $modified ) {
                    continue;
                }

                $encoded_path = implode(
                    '/',
                    array_map(
                        'rawurlencode',
                        explode( '/', $relative_path )
                    )
                );

                $files[] = [
                    'file'      => $relative_path,
                    'url'       => home_url(
                        '/' . self::DIRECTORY . '/' . $encoded_path
                    ),
                    'timestamp' => $modified,
                    'lastmod'   => gmdate( 'Y-m-d H:i:s', $modified ),
                ];
            }

        } catch ( Throwable $exception ) {
            return [
                'files' => [],
                'error' => 'Unable to scan the directory: ' . $exception->getMessage(),
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

        $result = self::scan_files();

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

        // Establish the initial baseline without unnecessary invalidation.
        if ( false === $previous ) {
            add_option( self::SIGNATURE_OPTION, $signature, '', false );
            return 'baseline_created';
        }

        if ( hash_equals( (string) $previous, $signature ) ) {
            return 'unchanged';
        }

        // Remove only the custom child sitemap and the root sitemap index.
        $cache_directory = \RankMath\Sitemap\Cache::get_cache_directory();
        $registry = \RankMath\Sitemap\Cache::cached_files();

        if ( ! is_array( $registry ) ) {
            $registry = [];
        }

        $root_filename = 'rank_math_' . md5( '1_1_' . home_url() ) . '.xml';
        $locations_filename = 'rank_math_' . md5( 'locations_1_' . home_url() ) . '.xml';

        $files_to_delete = [
            $root_filename,
            $locations_filename,
        ];

        foreach ( $registry as $filename => $sitemap_type ) {
            if ( 'locations' === (string) $sitemap_type ) {
                $files_to_delete[] = $filename;
            }
        }

        $files_to_delete = array_unique( $files_to_delete );

        foreach ( $files_to_delete as $filename ) {
            // Only allow Rank Math cache filenames, never arbitrary paths.
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

        // Keep all other providers' cache registry entries intact.
        foreach ( $registry as $filename => $sitemap_type ) {
            if (
                'locations' === (string) $sitemap_type ||
                $root_filename === $filename
            ) {
                unset( $registry[ $filename ] );
            }
        }

        \RankMath\Sitemap\Cache::cached_files( $registry );

        // Save the signature only after targeted cache deletion succeeds.
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
     * Display discovered files in WordPress Admin.
     */
    public static function render_admin_page() {

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die(
                esc_html__(
                    'You do not have permission to access this page.'
                )
            );
        }

        $result = self::scan_files();
        $files  = $result['files'];

        ?>
        <div class="wrap">
            <h1>HTML Sitemap Sync</h1>

            <p>
                Scans standalone HTML files inside the
                <code>/locations/</code> directory.
            </p>

            <?php
            $sync_messages = [
                'updated' => 'Changes detected. Rank Math sitemap cache invalidated.',
                'unchanged' => 'Sync completed. No file changes were detected.',
                'baseline_created' => 'Initial sitemap signature created.',
                'scan_error' => 'Sync failed because the locations directory could not be scanned.',
                'rank_math_unavailable' => 'Sync failed because Rank Math sitemap functionality is unavailable.',
            ];

            $sync_status = isset( $_GET['sync_status'] )
                ? sanitize_key( wp_unslash( $_GET['sync_status'] ) )
                : '';

            if ( isset( $sync_messages[ $sync_status ] ) ) :
            ?>
                <div class="notice notice-info is-dismissible">
                    <p><?php echo esc_html( $sync_messages[ $sync_status ] ); ?></p>
                </div>
            <?php endif; ?>

            <form method="post"
                  action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden"
                       name="action"
                       value="stillworks_html_sitemap_manual_sync">
                <?php wp_nonce_field( 'stillworks_html_sitemap_manual_sync' ); ?>
                <?php submit_button( 'Sync Now', 'primary', 'submit', false ); ?>
            </form>

            <p>
                <strong>Scheduled sync:</strong>
                Every 15 minutes (WP-Cron).
            </p>

            <p>
                <strong>Child sitemap:</strong>
                <a href="<?php echo esc_url( home_url( '/locations-sitemap.xml' ) ); ?>"
                   target="_blank" rel="noopener noreferrer">
                    <?php echo esc_html( home_url( '/locations-sitemap.xml' ) ); ?>
                </a>
            </p>

            <hr>

            <?php if ( $result['error'] ) : ?>

                <div class="notice notice-error">
                    <p><?php echo esc_html( $result['error'] ); ?></p>
                </div>

            <?php else : ?>

                <h2>
                    Detected HTML Pages:
                    <?php echo esc_html( count( $files ) ); ?>
                </h2>

                <?php if ( empty( $files ) ) : ?>

                    <p>No eligible HTML files were discovered.</p>

                <?php else : ?>

                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th>File</th>
                                <th>Page URL</th>
                                <th>Last Modified (UTC)</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ( $files as $item ) : ?>
                                <tr>
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
                                        <?php echo esc_html( $item['lastmod'] ); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                <?php endif; ?>

            <?php endif; ?>

            <p>
                <strong>Integration status:</strong>
                Rank Math provider registered. Automatic change detection enabled.
            </p>
        </div>
        <?php
    }
}

/**
 * Rank Math custom provider for standalone HTML pages.
 */
final class StillWorks_HTML_Sitemap_Provider {

    private const TYPE = 'locations';

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

        $result = StillWorks_HTML_Sitemap_Sync::scan_files();

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

        $result = StillWorks_HTML_Sitemap_Sync::scan_files();

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


