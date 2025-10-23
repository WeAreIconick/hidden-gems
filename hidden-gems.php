<?php
/**
 * Plugin Name: Hidden Gems
 * Description: Discover high-quality WordPress plugins that haven't been widely adopted yet
 * Version: 1.0.0
 * Author: Iconick
 * Author URI: https://iconick.io
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HiddenGems {
    
    /**
     * Instance
     */
    private static $instance = null;
    
    /**
     * Get instance
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'install_plugins_tabs', array( $this, 'add_super_search_tab' ) );
            add_action( 'install_plugins_hidden-gems', array( $this, 'render_super_search_tab' ) );
        
        // AJAX handlers
        add_action( 'wp_ajax_hidden_gems_get_install_nonce', array( $this, 'ajax_get_install_nonce' ) );
        add_action( 'wp_ajax_hidden_gems_fetch_plugins', array( $this, 'ajax_fetch_plugins' ) );
        add_action( 'wp_ajax_hidden_gems_test', array( $this, 'ajax_test' ) );
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets() {
        $screen = get_current_screen();
        if ( $screen && 'plugin-install' === $screen->id ) {
            // Add aggressive cache busting
            $version = time() . wp_rand(1000, 9999);
            
            // Add cache-busting headers
            header( 'Cache-Control: no-cache, no-store, must-revalidate' );
            header( 'Pragma: no-cache' );
            header( 'Expires: 0' );
            
            // Enqueue WordPress core scripts and styles that we need
            wp_enqueue_script( 'jquery' );
            wp_enqueue_script( 'thickbox' );
            wp_enqueue_style( 'thickbox' );
            wp_enqueue_style( 'common' ); // WordPress admin common styles
            wp_enqueue_style( 'wp-admin' ); // WordPress admin styles
        }
    }
    
    /**
     * Add Hidden Gems tab
     */
    public function add_super_search_tab( $tabs ) {
        $tabs['hidden-gems'] = __( 'Hidden Gems', 'hidden-gems' );
        return $tabs;
    }
    
    /**
     * Fetch plugins from WordPress.org API with timeout protection
     */
    private function fetch_plugins_from_api( $args = array() ) {
        $defaults = array(
            'search' => '',
            'tag' => '',
            'author' => '',
            'per_page' => 200,
            'page' => 1,
            'browse' => 'search'
        );
        
        $args = wp_parse_args( $args, $defaults );
        
        $api_url = 'https://api.wordpress.org/plugins/info/1.2/';
        $request_args = array(
            'action' => 'query_plugins',
            'request' => array(
                'browse' => $args['browse'],
                'per_page' => $args['per_page'],
                'page' => $args['page'],
                'fields' => array(
                    'name', 'slug', 'version', 'author', 'author_profile',
                    'rating', 'num_ratings', 'active_installs', 'last_updated',
                    'added', 'short_description', 'tags', 'icons', 'homepage',
                    'download_link'
                )
            )
        );
        
        // Add search parameters if provided
        if ( ! empty( $args['search'] ) ) {
            $request_args['request']['search'] = $args['search'];
        }
        if ( ! empty( $args['tag'] ) ) {
            $request_args['request']['tag'] = $args['tag'];
        }
        if ( ! empty( $args['author'] ) ) {
            $request_args['request']['author'] = $args['author'];
        }
        
        // WordPress.org API only accepts GET requests
        $query_string = http_build_query( $request_args );
        $full_url = $api_url . '?' . $query_string;
        
        // Set a very short timeout and use stream context for better control
        $context = stream_context_create([
            'http' => [
                'timeout' => 5, // Very short timeout
                'user_agent' => 'HiddenGems/1.0.0'
            ]
        ]);
        
        // Use file_get_contents with stream context for maximum timeout control
        $response_data = @file_get_contents( $full_url, false, $context );
        
        if ( $response_data === false ) {
            return false;
        }
        
        $data = json_decode( $response_data, true );
        
        if ( ! $data || ! isset( $data['plugins'] ) ) {
            return false;
        }
        
        return $data['plugins'];
    }
    
    /**
     * Fetch plugins from WordPress.org API with timeout protection
     */
    private function fetch_plugins_from_api_old( $args = array() ) {
        $defaults = array(
            'search' => '',
            'tag' => '',
            'author' => '',
            'per_page' => 200,
            'page' => 1,
            'browse' => 'search'
        );
        
        $args = wp_parse_args( $args, $defaults );
        
        $api_url = 'https://api.wordpress.org/plugins/info/1.2/';
        $request_args = array(
            'action' => 'query_plugins',
            'request' => array(
                'browse' => $args['browse'],
                'per_page' => $args['per_page'],
                'page' => $args['page'],
                'fields' => array(
                    'name', 'slug', 'version', 'author', 'author_profile',
                    'rating', 'num_ratings', 'active_installs', 'last_updated',
                    'added', 'short_description', 'tags', 'icons', 'homepage',
                    'download_link'
                )
            )
        );
        
        // Add search parameters if provided
        if ( ! empty( $args['search'] ) ) {
            $request_args['request']['search'] = $args['search'];
        }
        if ( ! empty( $args['tag'] ) ) {
            $request_args['request']['tag'] = $args['tag'];
        }
        if ( ! empty( $args['author'] ) ) {
            $request_args['request']['author'] = $args['author'];
        }
        
        // WordPress.org API only accepts GET requests
        $query_string = http_build_query( $request_args );
        $full_url = $api_url . '?' . $query_string;
        
        // Set a very short timeout and use stream context for better control
        $context = stream_context_create([
            'http' => [
                'timeout' => 5, // Very short timeout
                'user_agent' => 'HiddenGems/1.0.0'
            ]
        ]);
        
        // Try wp_remote_get first
        $response = wp_remote_get( $full_url, array(
            'timeout' => 3, // Ultra-short timeout to prevent hanging
            'headers' => array(
                'User-Agent' => 'HiddenGems/1.0.0'
            )
        ) );
        
        if ( is_wp_error( $response ) ) {
            return false;
        }
        
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        
        if ( ! $data || ! isset( $data['plugins'] ) ) {
            return false;
        }
        
        return $data['plugins'];
    }
    
    /**
     * AJAX handler for fetching plugins from WordPress.org API
     */
    public function ajax_fetch_plugins() {
        check_ajax_referer( 'hidden_gems_nonce', 'nonce' );
        
        if ( ! current_user_can( 'install_plugins' ) ) {
            wp_die( 'Insufficient permissions' );
        }
        
        // Check cache first to prevent repeated API calls
        $cache_key = 'hidden_gems_plugins_cache';
        $cached_plugins = get_transient( $cache_key );
        
        if ( false !== $cached_plugins ) {
            wp_send_json_success( array(
                'plugins' => $cached_plugins,
                'count' => count( $cached_plugins ),
                'cached' => true
            ) );
            return;
        }
        
        // Get pagination parameters
        $page = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
        $per_page = isset( $_POST['per_page'] ) ? absint( $_POST['per_page'] ) : 24;
        $search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
        $max_installs = isset( $_POST['max_installs'] ) ? absint( $_POST['max_installs'] ) : 100000;
        $min_quality = isset( $_POST['min_quality'] ) ? absint( $_POST['min_quality'] ) : 0;
        $sort = isset( $_POST['sort'] ) ? sanitize_text_field( wp_unslash( $_POST['sort'] ) ) : 'newest';
        $server_side = isset( $_POST['server_side'] ) ? (bool) $_POST['server_side'] : false;
        
        // If server-side pagination is requested, implement it
        if ( $server_side ) {
            $result = $this->fetch_plugins_server_side( $page, $per_page, $search, $max_installs, $min_quality, $sort );
            
            if ( false === $result ) {
                wp_send_json_error( array( 'message' => 'Failed to fetch plugins from WordPress.org API' ) );
            }
            
            wp_send_json_success( $result );
        }
        
        // Get more plugins from multiple sources to find more hidden gems
        $all_plugins = array();
        
        // Get newest plugins (the real hidden gems)
        $new_plugins = $this->fetch_plugins_from_api( array(
            'per_page' => 500,
            'browse' => 'new'
        ) );
        
        if ($new_plugins && is_array($new_plugins)) {
            $all_plugins = array_merge($all_plugins, $new_plugins);
        }
        
        // Get recently updated plugins (might be hidden gems too)
        $updated_plugins = $this->fetch_plugins_from_api( array(
            'per_page' => 500,
            'browse' => 'updated'
        ) );
        
        if ($updated_plugins && is_array($updated_plugins)) {
            $all_plugins = array_merge($all_plugins, $updated_plugins);
        }
        
        // Get some popular plugins with lower install counts (might have hidden gems)
        $popular_plugins = $this->fetch_plugins_from_api( array(
            'per_page' => 500,
            'browse' => 'popular'
        ) );
        
        if ($popular_plugins && is_array($popular_plugins)) {
            $all_plugins = array_merge($all_plugins, $popular_plugins);
        }
        
        // Remove duplicates and limit to 2000 total for more variety
        $unique_plugins = array();
        $seen_slugs = array();
        
        foreach ($all_plugins as $plugin) {
            if (!in_array($plugin['slug'], $seen_slugs)) {
                $unique_plugins[] = $plugin;
                $seen_slugs[] = $plugin['slug'];
                if (count($unique_plugins) >= 2000) {
                    break;
                }
            }
        }
        
        $plugins = $unique_plugins;
        
        // If we have no plugins, return a simple error message instead of hanging
        if ( empty( $plugins ) ) {
            wp_send_json_error( array( 
                'message' => 'Unable to fetch plugins at this time. The WordPress.org API may be temporarily unavailable. Please try refreshing the page in a few minutes.',
                'retry' => true
            ) );
        }
        
        // Cache the results for 30 minutes to prevent repeated API calls
        set_transient( $cache_key, $plugins, 30 * MINUTE_IN_SECONDS );
        
        wp_send_json_success( array(
            'plugins' => $plugins,
            'count' => count( $plugins )
        ) );
    }
    
    /**
     * Server-side pagination for better performance
     */
    private function fetch_plugins_server_side( $page, $per_page, $search, $max_installs, $min_quality, $sort ) {
        // Calculate offset
        $offset = ( $page - 1 ) * $per_page;
        
        // Determine browse type based on sort
        $browse_type = 'new'; // default
        switch ( $sort ) {
            case 'rating':
                $browse_type = 'popular'; // Best rated are usually popular
                break;
            case 'updated':
                $browse_type = 'updated';
                break;
            case 'newest':
            default:
                $browse_type = 'new';
                break;
        }
        
        // Fetch plugins from API with pagination
        $api_args = array(
            'per_page' => min( $per_page * 5, 200 ), // Fetch more to account for filtering (increased multiplier)
            'page' => $page,
            'browse' => $browse_type
        );
        
        if ( $search ) {
            $api_args['browse'] = 'search';
            $api_args['search'] = $search;
        }
        
        $plugins = $this->fetch_plugins_from_api( $api_args );
        
        if ( false === $plugins ) {
            return false;
        }
        
        // Apply client-side filters
        $filtered_plugins = array();
        foreach ( $plugins as $plugin ) {
            if ( $this->passes_filters( $plugin, $search, $max_installs, $min_quality ) ) {
                $filtered_plugins[] = $plugin;
            }
        }
        
        // Apply sorting
        $filtered_plugins = $this->sort_plugins( $filtered_plugins, $sort );
        
        // Paginate results
        $total_filtered = count( $filtered_plugins );
        $paginated_plugins = array_slice( $filtered_plugins, 0, $per_page );
        
        return array(
            'plugins' => $paginated_plugins,
            'count' => $total_filtered,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil( $total_filtered / $per_page )
        );
    }
    
    /**
     * Check if plugin passes filters
     */
    private function passes_filters( $plugin, $search, $max_installs, $min_quality ) {
        // Search filter
        if ( $search ) {
            $searchable_text = strtolower( $plugin['name'] . ' ' . $plugin['short_description'] . ' ' . $plugin['author'] );
            if ( strpos( $searchable_text, strtolower( $search ) ) === false ) {
                return false;
            }
        }
        
        // Max installs filter
        if ( $max_installs > 0 && $plugin['active_installs'] > $max_installs ) {
            return false;
        }
        
        // Min quality filter
        if ( $min_quality > 0 ) {
            $min_rating = $min_quality * 20; // Convert stars to percentage
            if ( $plugin['rating'] < $min_rating ) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Sort plugins
     */
    private function sort_plugins( $plugins, $sort_by ) {
        switch ( $sort_by ) {
            case 'rating':
                usort( $plugins, function( $a, $b ) {
                    return ( $b['rating'] ?? 0 ) - ( $a['rating'] ?? 0 );
                } );
                break;
            case 'updated':
                usort( $plugins, function( $a, $b ) {
                    return strtotime( $b['last_updated'] ) - strtotime( $a['last_updated'] );
                } );
                break;
            case 'newest':
            default:
                usort( $plugins, function( $a, $b ) {
                    return strtotime( $b['added'] ) - strtotime( $a['added'] );
                } );
                break;
        }
        
        return $plugins;
    }
    
    /**
     * AJAX handler for install nonces
     */
    public function ajax_get_install_nonce() {
        check_ajax_referer( 'hidden_gems_nonce', 'nonce' );
        
        if ( ! current_user_can( 'install_plugins' ) ) {
            wp_die( 'Insufficient permissions' );
        }
        
        $slug = isset( $_POST['slug'] ) ? sanitize_text_field( wp_unslash( $_POST['slug'] ) ) : '';
        $install_url = wp_nonce_url( admin_url( "update.php?action=install-plugin&plugin=" . $slug ), "install-plugin_" . $slug );
        
        wp_send_json_success( array(
            'install_url' => $install_url
        ) );
    }
    
    /**
     * Test AJAX endpoint
     */
    public function ajax_test() {
        wp_send_json_success( array( 'message' => 'AJAX is working!' ) );
    }
    
    /**
     * Render Super Search tab
     */
    public function render_super_search_tab( $paged = 1 ) {
        // Use WordPress core structure
        echo '<div class="wp-list-table widefat plugin-install">';
        echo '<h2 class="screen-reader-text">Plugins list</h2>';
        
        // Plugin search and filter interface
        echo '<div id="plugin-search-interface" style="margin: 20px 0;">';
        
        // Add the filtering interface
        $this->render_filter_interface();
        
        echo '<div id="the-list">';
        echo '<div id="plugin-results">';
        echo '<div class="hidden-gems-loading">';
        echo '<div class="spinner is-active"></div>';
        echo 'Discovering hidden gems from WordPress.org...';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        // Add aggressive cache busting and use only WordPress core classes
        $version = time() . wp_rand(1000, 9999);
        
        // Force cache busting with meta tags
        echo '<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">';
        echo '<meta http-equiv="Pragma" content="no-cache">';
        echo '<meta http-equiv="Expires" content="0">';
        ?>
        <style>
        /* Cache busted: <?php echo esc_html( $version ); ?> */
        #plugin-results {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        #the-list {
            margin: 20px 0;
        }
        .plugin-card {
            transition: opacity 0.3s ease, transform 0.3s ease;
            width: 100%;
        }
        .hidden-gems-hidden {
            opacity: 0;
            transform: scale(0.95);
            pointer-events: none;
        }
        .hidden-gems-visible {
            opacity: 1;
            transform: scale(1);
        }
        
        /* Enhanced pagination styling - break out of grid and center */
        .tablenav {
            display: flex;
            justify-content: center;
            width: 100%;
            margin: 20px 0;
            grid-column: 1 / -1; /* Break out of grid columns */
        }
        
        .tablenav-pages {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: center;
            width: 100%;
        }
        
        /* Pagination wrapper to break out of grid */
        .tablenav-wrapper {
            grid-column: 1 / -1 !important;
            width: 100% !important;
            display: flex;
            justify-content: center;
            margin: 20px 0;
        }
        
        .tablenav-pages .page-numbers {
            font-size: 14px !important;
            padding: 8px 12px !important;
            min-width: 32px !important;
            text-align: center !important;
            text-decoration: none;
            border: 1px solid #c3c4c7;
            border-radius: 3px;
            transition: all 0.2s ease;
            margin: 0 4px !important; /* Add spacing between numbers */
        }
        
        .tablenav-pages .page-numbers:hover {
            background-color: #f6f7f7;
            border-color: #8c8f94;
        }
        
        .tablenav-pages .current {
            font-weight: bold !important;
            background: #0073aa !important;
            color: white !important;
            border-color: #0073aa !important;
        }
        
        .tablenav-pages .button {
            font-size: 14px !important;
            padding: 8px 12px !important;
            margin: 0 4px !important; /* Add spacing between buttons */
        }
        
        .tablenav-pages .button:disabled,
        .tablenav-pages .button.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        /* Loading state styling - absolutely positioned and centered */
        .hidden-gems-loading {
            position: fixed !important;
            top: 50% !important;
            left: 50% !important;
            transform: translate(-50%, -50%) !important;
            display: flex !important;
            flex-direction: column !important;
            align-items: center !important;
            justify-content: center !important;
            padding: 40px !important;
            color: #646970 !important;
            font-size: 16px !important;
            font-weight: 500 !important;
            text-align: center !important;
            background: rgba(255, 255, 255, 0.98) !important;
            border-radius: 8px !important;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15) !important;
            z-index: 99999 !important;
            min-width: 300px !important;
            max-width: 400px !important;
            border: 1px solid #c3c4c7 !important;
        }
        
        .hidden-gems-loading .spinner {
            margin-bottom: 15px;
        }
        
        /* Break loading state out of grid completely - override any conflicting styles */
        #plugin-results .hidden-gems-loading,
        .hidden-gems-loading {
            position: fixed !important;
            top: 50% !important;
            left: 50% !important;
            transform: translate(-50%, -50%) !important;
            grid-column: none !important;
            margin: 0 !important;
            z-index: 99999 !important;
            width: auto !important;
            height: auto !important;
        }
        
        /* Mobile responsiveness */
        @media (max-width: 768px) {
            #plugin-results {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .tablenav-pages .page-numbers {
                padding: 12px 8px !important;
                min-width: 44px !important; /* Touch target size */
                font-size: 16px !important;
            }
            
            .tablenav {
                justify-content: center;
                width: 100%;
                margin: 15px 0;
            }
            
            .tablenav-pages {
                justify-content: center;
                gap: 4px;
                width: 100%;
                flex-direction: column;
                align-items: center;
            }
            
            .displaying-num {
                width: 100%;
                text-align: center;
                margin-bottom: 10px;
                order: -1;
            }
            
            .pagination-links {
                display: flex;
                justify-content: center;
                flex-wrap: wrap;
                gap: 4px;
            }
        }
        
        /* Focus styles for accessibility */
        .tablenav-pages .page-numbers:focus,
        .tablenav-pages .button:focus {
            outline: 2px solid #0073aa;
            outline-offset: 2px;
        }
        
        /* Screen reader only text */
        .screen-reader-text {
            position: absolute !important;
            clip: rect(1px, 1px, 1px, 1px);
            width: 1px;
            height: 1px;
            overflow: hidden;
        }
        </style>
        
        <script type="text/javascript">
        // Cache busted: <?php echo esc_html( $version ); ?>
        console.log('Hidden Gems: Script starting to load...');
        
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Hidden Gems: DOM loaded, initializing...');
            
            // Check if required elements exist
            var pluginResults = document.getElementById('plugin-results');
            var filterForm = document.getElementById('plugin-filter-form');
            
            console.log('Hidden Gems: plugin-results element:', pluginResults);
            console.log('Hidden Gems: plugin-filter-form element:', filterForm);
            
            if (!pluginResults) {
                console.error('Hidden Gems: plugin-results element not found!');
                return;
            }
            
            var allPlugins = [];
            var filteredPlugins = [];
            
            // URL management functions
            function getCurrentPageFromURL() {
                var urlParams = new URLSearchParams(window.location.search);
                var page = parseInt(urlParams.get('paged')) || 1;
                return Math.max(1, page);
            }
            
            function getFiltersFromURL() {
                var urlParams = new URLSearchParams(window.location.search);
                return {
                    search: urlParams.get('search') || '',
                    max_installs: urlParams.get('max_installs') || '100000',
                    min_quality: urlParams.get('min_quality') || '0',
                    sort: urlParams.get('sort') || 'newest'
                };
            }
            
            function updateURL(page, filters) {
                var url = new URL(window.location);
                url.searchParams.set('paged', page);
                
                if (filters.search) url.searchParams.set('search', filters.search);
                else url.searchParams.delete('search');
                
                if (filters.max_installs && filters.max_installs !== '100000') {
                    url.searchParams.set('max_installs', filters.max_installs);
                } else {
                    url.searchParams.delete('max_installs');
                }
                
                if (filters.min_quality && filters.min_quality !== '0') {
                    url.searchParams.set('min_quality', filters.min_quality);
                } else {
                    url.searchParams.delete('min_quality');
                }
                
                if (filters.sort && filters.sort !== 'newest') {
                    url.searchParams.set('sort', filters.sort);
                } else {
                    url.searchParams.delete('sort');
                }
                
                window.history.pushState({}, '', url);
            }
            
            function applyFiltersToForm(filters) {
                var form = document.getElementById('plugin-filter-form');
                if (!form) return;
                
                if (filters.search) form.querySelector('#search').value = filters.search;
                if (filters.max_installs) form.querySelector('#max-installs').value = filters.max_installs;
                if (filters.min_quality) form.querySelector('#min-quality').value = filters.min_quality;
                if (filters.sort) form.querySelector('#sort').value = filters.sort;
            }
            
            function showLoadingState() {
                document.getElementById('plugin-results').innerHTML = 
                    '<div class="hidden-gems-loading">' +
                    '<div class="spinner is-active"></div>' +
                    'Loading hidden gems...' +
                    '</div>';
            }
            
            function showError(message) {
                document.getElementById('plugin-results').innerHTML = 
                    '<div class="no-results" style="text-align: center; padding: 40px; color: #d63638;">' +
                    '<h3>Error</h3>' +
                    '<p>' + message + '</p>' +
                    '<button class="button" onclick="location.reload()">Try Again</button>' +
                    '</div>';
            }
            
            function loadPluginsFromAPI() {
                console.log('Hidden Gems: Discovering hidden gems from WordPress.org API...');
                console.log('Hidden Gems: ajaxurl available:', typeof ajaxurl !== 'undefined' ? ajaxurl : 'UNDEFINED');
                
                // First test if AJAX is working at all
                console.log('Hidden Gems: Testing AJAX connection...');
                var testData = new FormData();
                testData.append('action', 'hidden_gems_test');
                testData.append('nonce', '<?php echo esc_js( wp_create_nonce( 'hidden_gems_nonce' ) ); ?>');
                
                fetch(ajaxurl, {
                    method: 'POST',
                    body: testData
                })
                .then(function(response) {
                    console.log('Hidden Gems: Test AJAX response:', response.status);
                    return response.json();
                })
                .then(function(data) {
                    console.log('Hidden Gems: Test AJAX result:', data);
                    if (data.success) {
                        console.log('Hidden Gems: AJAX is working, proceeding with plugin fetch...');
                        loadPluginsFromAPIReal();
                    } else {
                        console.log('Hidden Gems: AJAX test failed');
                        showError('AJAX connection failed');
                    }
                })
                .catch(function(error) {
                    console.log('Hidden Gems: AJAX test error:', error);
                    showError('AJAX connection error: ' + error);
                });
            }
            
            function loadPluginsFromAPIReal() {
                console.log('Hidden Gems: Starting real plugin fetch...');
                
                showLoadingState();
                
                // Set a timeout to prevent hanging
                var timeoutId = setTimeout(function() {
                    console.log('Hidden Gems: Request timed out');
                    document.getElementById('plugin-results').innerHTML = 
                        '<div class="no-results" style="text-align: center; padding: 40px; color: #d63638;">' +
                        '<h3>Request Timeout</h3>' +
                        '<p>The request took too long. Please try again.</p>' +
                        '<button class="button" onclick="location.reload()">Try Again</button>' +
                        '</div>';
                }, 15000); // 15 second timeout
                
                // Load plugins
                var formData = new FormData();
                formData.append('action', 'hidden_gems_fetch_plugins');
                formData.append('nonce', '<?php echo esc_js( wp_create_nonce( 'hidden_gems_nonce' ) ); ?>');
                
                console.log('Hidden Gems: Making AJAX request to:', ajaxurl);
                
                fetch(ajaxurl, {
                    method: 'POST',
                    body: formData
                })
                .then(function(response) {
                    clearTimeout(timeoutId);
                    console.log('Hidden Gems: Got response from server:', response.status);
                    return response.json();
                })
                .then(function(data) {
                    if (data.success) {
                        allPlugins = data.data.plugins;
                        console.log('Hidden Gems: Found', allPlugins.length, 'potential hidden gems from API');
                        applyCurrentFilters(currentPage); // Use current page from URL
                    } else {
                        console.log('Hidden Gems: Failed to load plugins:', data.data.message);
                        document.getElementById('plugin-results').innerHTML = 
                            '<div class="no-results" style="text-align: center; padding: 40px; color: #d63638;">' +
                            '<h3>Error loading plugins</h3>' +
                            '<p>' + data.data.message + '</p>' +
                            '<button class="button" onclick="location.reload()">Try Again</button>' +
                            '</div>';
                    }
                })
                .catch(function(error) {
                    clearTimeout(timeoutId);
                    console.log('Hidden Gems: AJAX failed:', error);
                    document.getElementById('plugin-results').innerHTML = 
                        '<div class="no-results" style="text-align: center; padding: 40px; color: #d63638;">' +
                        '<h3>Connection Error</h3>' +
                        '<p>Unable to load plugins. Please check your internet connection and try again.</p>' +
                        '<button class="button" onclick="location.reload()">Try Again</button>' +
                        '</div>';
                });
            } // Close loadPluginsFromAPI function
            
            // Handle filter form submission
            document.getElementById('plugin-filter-form').addEventListener('submit', function(e) {
                e.preventDefault();
                console.log('Hidden Gems: Form submitted');
                applyCurrentFilters(1); // Reset to page 1 when filtering
            });
            
            function applyCurrentFilters(page = 1) {
                console.log('Hidden Gems: applyCurrentFilters called with page:', page);
                var form = document.getElementById('plugin-filter-form');
                var formData = new FormData(form);
                var filters = {};
                
                // Convert form data to object
                for (var pair of formData.entries()) {
                    filters[pair[0]] = pair[1];
                }
                
                console.log('Hidden Gems: Applying filters:', filters);
                
                // Update URL with current filters and page
                updateURL(page, filters);
                
                filteredPlugins = allPlugins.filter(function(plugin) {
                    return passesFilters(plugin, filters);
                });
                
                console.log('Hidden Gems: Found', filteredPlugins.length, 'hidden gems from', allPlugins.length, 'total plugins');
                
                // Sort plugins
                sortPlugins(filteredPlugins, filters.sort || 'newest');
                
                // Paginate results
                var perPage = 24;
                var totalPages = Math.ceil(filteredPlugins.length / perPage);
                var startIndex = (page - 1) * perPage;
                var endIndex = startIndex + perPage;
                var pagePlugins = filteredPlugins.slice(startIndex, endIndex);
                
                console.log('Hidden Gems: Pagination - Page:', page, 'Per page:', perPage, 'Total pages:', totalPages);
                console.log('Hidden Gems: Showing plugins', startIndex + 1, 'to', endIndex, 'of', filteredPlugins.length);
                console.log('Hidden Gems: Page plugins count:', pagePlugins.length);
                
                displayPlugins(pagePlugins, filteredPlugins.length, page, totalPages);
            }
            
            function passesFilters(plugin, filters) {
                // Search term
                if (filters.search) {
                    var searchTerm = filters.search.toLowerCase();
                    var searchableText = (
                        plugin.name + ' ' +
                        plugin.short_description + ' ' +
                        plugin.author + ' ' +
                        Object.keys(plugin.tags || {}).join(' ')
                    ).toLowerCase();
                    
                    if (!searchableText.includes(searchTerm)) {
                        return false;
                    }
                }
                
                // Quality filter (min rating)
                if (filters.min_quality && filters.min_quality > 0) {
                    var minRating = parseInt(filters.min_quality) * 20; // Convert to percentage
                    if (plugin.rating < minRating) {
                        return false;
                    }
                }
                
                // Max installs filter (key for hidden gems)
                if (filters.max_installs && filters.max_installs > 0) {
                    if (plugin.active_installs > parseInt(filters.max_installs)) {
                        return false;
                    }
                }
                
                return true;
            }
            
            function sortPlugins(plugins, sortBy) {
                plugins.sort(function(a, b) {
                    switch (sortBy) {
                        case 'rating':
                            return (b.rating || 0) - (a.rating || 0);
                        case 'newest':
                            return new Date(b.added) - new Date(a.added);
                        case 'updated':
                            return new Date(b.last_updated) - new Date(a.last_updated);
                        default:
                            return new Date(b.added) - new Date(a.added); // Default to newest
                    }
                });
            }
            
            function displayPlugins(plugins, totalCount, currentPage, totalPages) {
                console.log('Hidden Gems: displayPlugins called with', plugins.length, 'plugins, page', currentPage, 'of', totalPages);
                var html = '';
                
                if (plugins.length === 0) {
                    html = '<div class="no-results" style="text-align: center; padding: 40px; color: #646970;">' +
                           '<h3>No hidden gems found</h3>' +
                           '<p>Try adjusting your search criteria or filters to discover more gems.</p>' +
                           '<button class="button reset-filters-btn">Reset Filters</button>' +
                           '</div>';
                } else {
                    console.log('Hidden Gems: Generating HTML for', plugins.length, 'plugins');
                    plugins.forEach(function(plugin, index) {
                        var tags = plugin.tags || {};
                        var icons = plugin.icons || {};
                        var icon = icons['1x'] || icons['2x'] || icons['default'] || '';
                        
                        // Determine if this is a hidden gem (prioritize newer, less popular plugins)
                        var isHiddenGem = plugin.active_installs < 50000 && plugin.rating >= 40; // Under 50K installs and 2+ stars
                        var isNewGem = plugin.active_installs < 1000 && plugin.rating >= 60; // Under 1K installs and 3+ stars (super hidden)
                        var addedDate = new Date(plugin.added);
                        var daysSinceAdded = Math.floor((new Date() - addedDate) / (1000 * 60 * 60 * 24));
                        
                        html += '<div class="plugin-card plugin-card-' + plugin.slug + '">';
                        html += '<div class="plugin-card-top">';
                        html += '<div class="name column-name">';
                        html += '<h3>';
                        html += '<a href="' + '<?php echo esc_url( admin_url( "plugin-install.php?tab=plugin-information&plugin=" ) ); ?>' + plugin.slug + '&TB_iframe=true&width=772&height=483" class="thickbox open-plugin-details-modal">';
                        if (icon) {
                            html += '<img src="' + icon + '" class="plugin-icon" alt=""> ';
                        }
                        html += plugin.name + '</a>';
                        html += '</h3>';
                        
                        // Move badges to their own line to prevent cutoff
                        if (isNewGem) {
                            html += '<div style="margin-top: 5px;"><span style="background: #ff6b6b; color: #fff; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; display: inline-block;">⭐ Super Hidden Gem</span></div>';
                        } else if (isHiddenGem) {
                            html += '<div style="margin-top: 5px;"><span style="background: #ffd700; color: #000; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; display: inline-block;">💎 Hidden Gem</span></div>';
                        }
                        html += '</div>';
                        html += '<div class="action-links">';
                        html += '<ul class="plugin-action-buttons">';
                        html += '<li><a class="install-now button" data-slug="' + plugin.slug + '" href="#" aria-label="Install ' + plugin.name + ' ' + plugin.version + ' now" data-name="' + plugin.name + ' ' + plugin.version + '" role="button">Install Now</a></li>';
                        html += '<li><a href="' + '<?php echo esc_url( admin_url( "plugin-install.php?tab=plugin-information&plugin=" ) ); ?>' + plugin.slug + '&TB_iframe=true&width=772&height=483" class="thickbox open-plugin-details-modal" aria-label="More information about ' + plugin.name + ' ' + plugin.version + '" data-title="' + plugin.name + ' ' + plugin.version + '">More Details</a></li>';
                        html += '</ul>';
                        html += '</div>';
                        html += '<div class="desc column-description">';
                        html += '<p>' + plugin.short_description + '</p>';
                        html += '<p class="authors"><cite>By <a href="' + plugin.author_profile + '" target="_blank">' + plugin.author + '</a></cite></p>';
                        html += '</div>';
                        html += '</div>';
                        html += '<div class="plugin-card-bottom">';
                        
                        // Added date badge
                        var addedText = '';
                        if (daysSinceAdded < 7) {
                            addedText = 'Added ' + daysSinceAdded + ' days ago';
                        } else if (daysSinceAdded < 30) {
                            addedText = 'Added ' + Math.floor(daysSinceAdded / 7) + ' weeks ago';
                        } else if (daysSinceAdded < 365) {
                            addedText = 'Added ' + Math.floor(daysSinceAdded / 30) + ' months ago';
                        } else {
                            addedText = 'Added ' + Math.floor(daysSinceAdded / 365) + ' years ago';
                        }
                        html += '<div style="background: #e7f3ff; color: #0073aa; padding: 4px 8px; border-radius: 3px; font-size: 12px; font-weight: 600; margin-bottom: 8px; display: inline-block;">' + addedText + '</div>';
                        
                        html += '<div class="vers column-rating">';
                        if (plugin.rating > 0) {
                            var stars = Math.round(plugin.rating / 20);
                            html += '<div class="star-rating">';
                            for (var i = 1; i <= 5; i++) {
                                if (i <= stars) {
                                    html += '<div class="star star-full" aria-hidden="true"></div>';
                                } else {
                                    html += '<div class="star star-empty" aria-hidden="true"></div>';
                                }
                            }
                            html += '</div>';
                            html += '<span class="num-ratings" aria-hidden="true">(' + plugin.num_ratings + ')</span>';
                        }
                        html += '</div>';
                        
                        // Enhanced install count with context for hidden gems
                        var installText = formatInstalls(plugin.active_installs) + ' Active Installations';
                        if (plugin.active_installs < 100) {
                            installText += ' - Fresh Discovery!';
                        } else if (plugin.active_installs < 1000) {
                            installText += ' - Undiscovered Gem!';
                        } else if (plugin.active_installs < 10000) {
                            installText += ' - Hidden Gem!';
                        } else if (plugin.active_installs < 50000) {
                            installText += ' - Emerging Plugin!';
                        }
                        html += '<div class="column-downloaded">' + installText + '</div>';
                        html += '</div>';
                        html += '</div>';
                        
                        // Log every 10th plugin for debugging
                        if ((index + 1) % 10 === 0) {
                            console.log('Hidden Gems: Processed', index + 1, 'plugins so far');
                        }
                    });
                    
                    console.log('Hidden Gems: Finished generating HTML for all', plugins.length, 'plugins');
                }
                
                // Add pagination if there are multiple pages
                if (totalPages && totalPages > 1) {
                    // Build page links using WordPress core format with enhanced accessibility
                    var startItem = ((currentPage - 1) * 24) + 1;
                    var endItem = Math.min(currentPage * 24, totalCount);
                    
                    var pageLinks = '<nav aria-label="Hidden gems pagination">';
                    pageLinks += '<span class="displaying-num">' + totalCount + ' hidden gems</span>';
                    pageLinks += '<span class="pagination-links">';
                    
                    // Previous button
                    if (currentPage > 1) {
                        pageLinks += '<a class="prev-page button" href="#" data-page="' + (currentPage - 1) + '" aria-label="Go to previous page, page ' + (currentPage - 1) + '"><span class="screen-reader-text">Previous page</span><span aria-hidden="true">‹</span></a>';
                    } else {
                        pageLinks += '<span class="tablenav-pages-navspan button disabled" aria-hidden="true" aria-label="Previous page (disabled)">‹</span>';
                    }
                    
                    // First page (if not visible)
                    if (currentPage > 3) {
                        pageLinks += '<a class="page-numbers" href="#" data-page="1" aria-label="Go to page 1">1</a>';
                        if (currentPage > 4) {
                            pageLinks += '<span class="page-numbers dots" aria-hidden="true">…</span>';
                        }
                    }
                    
                    // Show page numbers (limit to 5 pages around current)
                    var startPage = Math.max(1, currentPage - 2);
                    var endPage = Math.min(totalPages, currentPage + 2);
                    
                    for (var i = startPage; i <= endPage; i++) {
                        if (i === currentPage) {
                            pageLinks += '<span class="page-numbers current" aria-current="page" aria-label="Current page, page ' + i + '">' + i + '</span>';
                        } else {
                            pageLinks += '<a class="page-numbers" href="#" data-page="' + i + '" aria-label="Go to page ' + i + '">' + i + '</a>';
                        }
                    }
                    
                    // Last page (if not visible)
                    if (currentPage < totalPages - 2) {
                        if (currentPage < totalPages - 3) {
                            pageLinks += '<span class="page-numbers dots" aria-hidden="true">…</span>';
                        }
                        pageLinks += '<a class="page-numbers" href="#" data-page="' + totalPages + '" aria-label="Go to page ' + totalPages + '">' + totalPages + '</a>';
                    }
                    
                    // Next button
                    if (currentPage < totalPages) {
                        pageLinks += '<a class="next-page button" href="#" data-page="' + (currentPage + 1) + '" aria-label="Go to next page, page ' + (currentPage + 1) + '"><span class="screen-reader-text">Next page</span><span aria-hidden="true">›</span></a>';
                    } else {
                        pageLinks += '<span class="tablenav-pages-navspan button disabled" aria-hidden="true" aria-label="Next page (disabled)">›</span>';
                    }
                    
                    pageLinks += '</span>';
                    pageLinks += '</nav>';
                    
                    // Use proper WordPress core pagination structure with grid breakout
                    html += '<div class="tablenav-wrapper" style="grid-column: 1 / -1; width: 100%;">';
                    html += '<div class="tablenav"><div class="tablenav-pages" style="margin: 1em 0">' + pageLinks + '</div></div>';
                    html += '</div>';
                }
                
                document.getElementById('plugin-results').innerHTML = html;
            } // Close displayPlugins function
            
            // Handle install button clicks using vanilla JavaScript (outside displayPlugins)
            document.getElementById('plugin-results').addEventListener('click', function(e) {
                if (e.target.classList.contains('install-now')) {
                    e.preventDefault();
                    var slug = e.target.getAttribute('data-slug');
                    
                    // Use WordPress core plugin installation method with AJAX nonce
                    var formData = new FormData();
                        formData.append('action', 'hidden_gems_get_install_nonce');
                        formData.append('slug', slug);
                        formData.append('nonce', '<?php echo esc_js( wp_create_nonce( 'hidden_gems_nonce' ) ); ?>');
                    
                    fetch(ajaxurl, {
                        method: 'POST',
                        body: formData
                    })
                    .then(function(response) {
                        return response.json();
                    })
                    .then(function(data) {
                        if (data.success) {
                            window.location.href = data.data.install_url;
                        } else {
                            alert('Error: ' + data.data.message);
                        }
                    });
                }
            });
            
            // Handle pagination clicks (outside displayPlugins)
            document.getElementById('plugin-results').addEventListener('click', function(e) {
                console.log('Hidden Gems: Click detected on:', e.target);
                console.log('Hidden Gems: Click target classes:', e.target.className);
                console.log('Hidden Gems: Click target parent:', e.target.parentElement);
                
                // Check if it's a clickable pagination element (not disabled spans)
                var clickableElement = e.target;
                
                // If clicking on a span inside a button, get the parent button
                if (e.target.tagName === 'SPAN' && e.target.parentElement) {
                    clickableElement = e.target.parentElement;
                }
                
                if ((clickableElement.classList.contains('page-numbers') || 
                     clickableElement.classList.contains('prev-page') || 
                     clickableElement.classList.contains('next-page')) &&
                    !clickableElement.classList.contains('disabled') &&
                    !clickableElement.classList.contains('tablenav-pages-navspan')) {
                    
                    e.preventDefault();
                    var page = clickableElement.getAttribute('data-page');
                    console.log('Hidden Gems: Pagination clicked, page:', page);
                    if (page) {
                        // Show loading state briefly for better UX
                        showLoadingState();
                        
                        // Small delay to show loading state
                        setTimeout(function() {
                            applyCurrentFilters(parseInt(page));
                            
                            // Scroll to top of results with smooth animation
                            var resultsElement = document.getElementById('plugin-results');
                            if (resultsElement) {
                                resultsElement.scrollIntoView({ 
                                    behavior: 'smooth', 
                                    block: 'start',
                                    inline: 'nearest'
                                });
                                
                                // Announce page change to screen readers
                                var announcement = document.createElement('div');
                                announcement.setAttribute('aria-live', 'polite');
                                announcement.setAttribute('aria-atomic', 'true');
                                announcement.className = 'screen-reader-text';
                                announcement.textContent = 'Navigated to page ' + page;
                                document.body.appendChild(announcement);
                                
                                // Remove announcement after screen reader has time to read it
                                setTimeout(function() {
                                    document.body.removeChild(announcement);
                                }, 1000);
                            }
                        }, 100);
                    }
                }
            });
            
            function formatInstalls(count) {
                if (count >= 1000000) {
                    return Math.floor(count / 1000000) + '+ Million';
                } else if (count >= 1000) {
                    return Math.floor(count / 1000) + ',000+';
                } else {
                    return count + '+';
                }
            } // Close formatInstalls function
            
            // Initialize the plugin with URL parameters AFTER all functions are defined
            console.log('Hidden Gems: All functions defined, starting initialization...');
            var currentPage = getCurrentPageFromURL();
            var currentFilters = getFiltersFromURL();
            
            // Apply filters from URL on load
            if (Object.keys(currentFilters).length > 0) {
                applyFiltersToForm(currentFilters);
            }
            
            // Load plugins from WordPress.org API
            console.log('Hidden Gems: Calling loadPluginsFromAPI...');
            loadPluginsFromAPI();
            
        }); // Close DOMContentLoaded event listener
        </script>
        <?php
        
        echo '</div>';
    }
    
    /**
     * Render filter interface
     */
    private function render_filter_interface() {
        // Get current filter values from URL - only gem-finding filters
        // These are GET parameters for display purposes only, not form submissions
        $current_max_installs = 100000; // Default to 100K for more results
        $current_min_quality = 0; // Default to any rating for more results
        $current_sort = 'newest';
        $current_search = '';
        
        // Validate and sanitize GET parameters if they exist
        if ( isset( $_GET['max_installs'] ) && is_numeric( $_GET['max_installs'] ) ) {
            $current_max_installs = absint( $_GET['max_installs'] );
        }
        if ( isset( $_GET['min_quality'] ) && is_numeric( $_GET['min_quality'] ) ) {
            $current_min_quality = absint( $_GET['min_quality'] );
        }
        if ( isset( $_GET['sort'] ) && in_array( $_GET['sort'], array( 'newest', 'rating', 'updated' ) ) ) {
            $current_sort = sanitize_text_field( wp_unslash( $_GET['sort'] ) );
        }
        if ( isset( $_GET['search'] ) ) {
            $current_search = sanitize_text_field( wp_unslash( $_GET['search'] ) );
        }
        
        ?>
        <div class="hidden-gems-filters" style="margin: 20px 0; padding: 15px; background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; box-shadow: 0 1px 1px rgba(0,0,0,0.04);">
            <form method="get" class="hidden-gems-filter-form" id="plugin-filter-form">
                <!-- Always preserve the hidden-gems tab -->
                <input type="hidden" name="tab" value="hidden-gems">
                
                <!-- Gem-finding filters only -->
                <div style="display: flex; flex-wrap: wrap; gap: 15px; align-items: end; width: 100%;">
                    <div style="display: flex; flex-direction: column; min-width: 200px;">
                        <label for="search" style="font-weight: 600; margin-bottom: 5px; color: #1d2327;">Search:</label>
                        <input type="text" id="search" name="search" value="<?php echo esc_attr( $current_search ); ?>" placeholder="Search for specific gems..." style="padding: 6px 8px; border: 1px solid #8c8f94; border-radius: 3px; font-size: 13px;" />
                    </div>
                    
                    <div style="display: flex; flex-direction: column; min-width: 140px;">
                        <label for="max-installs" style="font-weight: 600; margin-bottom: 5px; color: #1d2327;">Max Installs:</label>
                        <select id="max-installs" name="max_installs" style="padding: 6px 8px; border: 1px solid #8c8f94; border-radius: 3px; font-size: 13px;">
                            <option value="10000" <?php selected( $current_max_installs, 10000 ); ?>>Under 10K</option>
                            <option value="50000" <?php selected( $current_max_installs, 50000 ); ?>>Under 50K</option>
                            <option value="100000" <?php selected( $current_max_installs, 100000 ); ?>>Under 100K</option>
                            <option value="500000" <?php selected( $current_max_installs, 500000 ); ?>>Under 500K</option>
                        </select>
                    </div>
                    
                    <div style="display: flex; flex-direction: column; min-width: 120px;">
                        <label for="min-quality" style="font-weight: 600; margin-bottom: 5px; color: #1d2327;">Quality:</label>
                        <select id="min-quality" name="min_quality" style="padding: 6px 8px; border: 1px solid #8c8f94; border-radius: 3px; font-size: 13px;">
                            <option value="0" <?php selected( $current_min_quality, 0 ); ?>>Any Rating</option>
                            <option value="3" <?php selected( $current_min_quality, 3 ); ?>>3+ Stars</option>
                            <option value="4" <?php selected( $current_min_quality, 4 ); ?>>4+ Stars</option>
                            <option value="5" <?php selected( $current_min_quality, 5 ); ?>>5 Stars</option>
                        </select>
                    </div>
                    
                    <div style="display: flex; flex-direction: column; min-width: 120px;">
                        <label for="sort" style="font-weight: 600; margin-bottom: 5px; color: #1d2327;">Sort By:</label>
                        <select id="sort" name="sort" style="padding: 6px 8px; border: 1px solid #8c8f94; border-radius: 3px; font-size: 13px;">
                            <option value="newest" <?php selected( $current_sort, 'newest' ); ?>>Newest</option>
                            <option value="rating" <?php selected( $current_sort, 'rating' ); ?>>Highest Rated</option>
                            <option value="updated" <?php selected( $current_sort, 'updated' ); ?>>Recently Updated</option>
                        </select>
                    </div>
                    
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <input type="submit" class="button button-primary" value="Find Gems" style="height: auto; padding: 6px 12px;" />
                                <a href="<?php echo esc_url( admin_url( 'plugin-install.php?tab=hidden-gems' ) ); ?>" class="button" style="height: auto; padding: 6px 12px;">Reset</a>
                    </div>
                </div>
                
                <!-- Helper text -->
                <div style="margin-top: 10px; font-size: 13px; color: #646970;">
                    Showing plugins with under <?php echo number_format( $current_max_installs ); ?> installs - adjust filters to find more hidden gems!
                </div>
            </form>
        </div>
        <?php
    }
}

// Initialize the plugin
HiddenGems::get_instance();