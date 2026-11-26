<?php
/**
 * Plugin Name: StillWorks Locations Hub
 * Description: Dynamic Locations Hub page display for standalone HTML and PHP location pages.
 * Version: 1.1.0
 * Author: StillWorks
 */

defined( 'ABSPATH' ) || exit;

final class StillWorks_Locations_Hub {

    /**
     * Initialize shortcode hook.
     */
    public static function init() {
        add_shortcode( 'stillworks_locations_hub', [ __CLASS__, 'render_locations_hub' ] );
    }

    /**
     * Safely extract the <title> tag from a local HTML or PHP file.
     * Bounded read: reads at most 2048 bytes from the file header.
     * Never executes or includes the file.
     *
     * @param string $relative_path Relative path from WordPress root or inside locations/.
     * @return string Human-readable title.
     */
    public static function get_location_title( $relative_path ) {
        if ( ! is_string( $relative_path ) || '' === trim( $relative_path ) ) {
            return __( 'Location', 'stillworks' );
        }

        $root = realpath( ABSPATH );
        if ( false === $root || ! is_dir( $root ) ) {
            return self::title_from_filename( $relative_path );
        }

        // Try direct path from ABSPATH first
        $real_path = realpath( $root . DIRECTORY_SEPARATOR . $relative_path );

        // Fallback: check inside location/ or locations/ directory if not found directly
        if ( false === $real_path || ! is_file( $real_path ) ) {
            foreach ( [ 'location', 'locations' ] as $dir_prefix ) {
                $check_root = realpath( ABSPATH . $dir_prefix );
                if ( false !== $check_root && is_dir( $check_root ) ) {
                    $test_path = realpath( $check_root . DIRECTORY_SEPARATOR . $relative_path );
                    if ( false !== $test_path && is_file( $test_path ) ) {
                        $real_path = $test_path;
                        break;
                    }
                }
            }
        }

        if (
            false === $real_path ||
            0 !== strpos( $real_path, $root . DIRECTORY_SEPARATOR ) ||
            ! is_file( $real_path )
        ) {
            return self::title_from_filename( $relative_path );
        }

        $handle = @fopen( $real_path, 'r' );
        if ( ! $handle ) {
            return self::title_from_filename( $relative_path );
        }

        $buffer = fread( $handle, 2048 );
        fclose( $handle );

        if ( is_string( $buffer ) && preg_match( '/<title[^>]*>(.*?)<\/title>/is', $buffer, $matches ) ) {
            $title = trim( html_entity_decode( $matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
            $title = preg_replace( '/\s+/', ' ', $title );
            if ( is_string( $title ) && '' !== $title ) {
                return $title;
            }
        }

        return self::title_from_filename( $relative_path );
    }

    /**
     * Fallback: format a clean title from the filename or parent folder name.
     *
     * @param string $relative_path
     * @return string
     */
    private static function title_from_filename( $relative_path ) {
        $base = basename( $relative_path );
        // If file is index.php or index.html, use the parent folder's name as the title!
        if ( in_array( strtolower( $base ), [ 'index.php', 'index.html' ], true ) ) {
            $dir = dirname( $relative_path );
            $name = ( '.' !== $dir ) ? basename( $dir ) : $base;
        } else {
            $name = preg_replace( '/\.(html|php)$/i', '', $base );
        }

        $name = str_replace( [ '-', '_' ], ' ', $name );
        return ucwords( $name );
    }

    /**
     * Render the dynamic locations grid.
     *
     * @return string Rendered HTML.
     */
    public static function render_locations_hub() {
        if (
            ! class_exists( 'StillWorks_HTML_Sitemap_Sync' ) ||
            ! is_callable( [ 'StillWorks_HTML_Sitemap_Sync', 'scan_files' ] )
        ) {
            return '<p class="stillworks-notice">' .
                esc_html__( 'Locations directory scanner is currently unavailable.', 'stillworks' ) .
                '</p>';
        }

        $result = StillWorks_HTML_Sitemap_Sync::scan_files();

        if (
            ! is_array( $result ) ||
            ! empty( $result['error'] ) ||
            ! isset( $result['files'] ) ||
            ! is_array( $result['files'] )
        ) {
            $error_msg = ( is_array( $result ) && ! empty( $result['error'] ) && is_string( $result['error'] ) )
                ? $result['error']
                : __( 'Unable to load locations.', 'stillworks' );
            return '<div class="notice notice-error"><p>' . esc_html( $error_msg ) . '</p></div>';
        }

        $files = $result['files'];

        if ( empty( $files ) ) {
            return '<p class="stillworks-empty">' .
                esc_html__( 'No location pages currently available.', 'stillworks' ) .
                '</p>';
        }

        ob_start();
        ?>
        <style>
        .stillworks-locations-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
            margin: 2rem 0;
            box-sizing: border-box;
        }
        .stillworks-location-card {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            background: var(--wp--preset--color--base, #ffffff);
            border: 1px solid var(--wp--preset--color--accent-4, #e2e8f0);
            border-radius: 8px;
            padding: 1.5rem;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            box-sizing: border-box;
        }
        .stillworks-location-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }
        .stillworks-location-card h3 {
            margin: 0 0 0.5rem 0;
            font-size: 1.25rem;
            line-height: 1.4;
            color: var(--wp--preset--color--contrast, #1a202c);
        }
        .stillworks-location-card .location-date {
            font-size: 0.875rem;
            color: var(--wp--preset--color--accent-5, #718096);
            margin: 0 0 1.25rem 0;
        }
        .stillworks-location-card .location-link {
            display: inline-block;
            margin-top: auto;
            color: var(--wp--preset--color--primary, #2563eb);
            font-weight: 600;
            text-decoration: underline;
        }
        .stillworks-location-card .location-link:hover {
            text-decoration: none;
        }
        </style>
        <div class="stillworks-locations-grid">
            <?php foreach ( $files as $item ) : ?>
                <?php
                if ( ! is_array( $item ) || empty( $item['file'] ) || empty( $item['url'] ) ) {
                    continue;
                }
                $title = self::get_location_title( $item['file'] );
                $url   = $item['url'];
                $lastmod_display = '';
                if ( ! empty( $item['timestamp'] ) && is_numeric( $item['timestamp'] ) && (int) $item['timestamp'] > 0 ) {
                    $lastmod_display = wp_date( get_option( 'date_format' ), (int) $item['timestamp'] );
                }
                ?>
                <article class="stillworks-location-card">
                    <h3><?php echo esc_html( $title ); ?></h3>
                    <?php if ( '' !== $lastmod_display ) : ?>
                        <p class="location-date">
                            <?php
                            /* translators: %s: formatted date */
                            printf( esc_html__( 'Updated: %s', 'stillworks' ), esc_html( $lastmod_display ) );
                            ?>
                        </p>
                    <?php endif; ?>
                    <a href="<?php echo esc_url( $url ); ?>" class="location-link" target="_self">
                        <?php esc_html_e( 'View Location Page &rarr;', 'stillworks' ); ?>
                    </a>
                </article>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}

StillWorks_Locations_Hub::init();
