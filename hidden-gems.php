<?php
/**
 * Plugin Name: Hidden Gems
 * Description: Discover high-quality WordPress plugins that haven't been widely adopted yet
 * Version: 1.0.0
 * Author: Your Name
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
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets() {
        $screen = get_current_screen();
        if ( $screen && 'plugin-install' === $screen->id ) {
            // Add aggressive cache busting
            $version = time() . rand(1000, 9999);
            
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
     * Fetch plugins from WordPress.org API
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
        
        $response = wp_remote_get( $full_url, array(
            'timeout' => 30
        ) );
        
        if ( is_wp_error( $response ) ) {
            error_log( 'Hidden Gems API Error: ' . $response->get_error_message() );
            return false;
        }
        
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        
        if ( ! $data || ! isset( $data['plugins'] ) ) {
            error_log( 'Hidden Gems API Response: ' . $body );
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
        
        // Fetch more plugins using multiple strategies for better coverage
        $all_plugins = array();
        
        // Strategy 1: Get newest plugins
        $new_plugins = $this->fetch_plugins_from_api( array(
            'per_page' => 100,
            'browse' => 'new'
        ) );
        if ($new_plugins) {
            $all_plugins = array_merge($all_plugins, $new_plugins);
        }
        
        // Strategy 2: Get popular plugins (to have more variety)
        $popular_plugins = $this->fetch_plugins_from_api( array(
            'per_page' => 100,
            'browse' => 'popular'
        ) );
        if ($popular_plugins) {
            $all_plugins = array_merge($all_plugins, $popular_plugins);
        }
        
        // Strategy 3: Get plugins by searching common terms
        $search_terms = array('wordpress', 'plugin', 'tool', 'utility', 'widget', 'shortcode');
        foreach ($search_terms as $term) {
            $search_plugins = $this->fetch_plugins_from_api( array(
                'per_page' => 50,
                'browse' => 'search',
                'search' => $term
            ) );
            if ($search_plugins) {
                $all_plugins = array_merge($all_plugins, $search_plugins);
            }
        }
        
        // Remove duplicates and limit to 500 total
        $unique_plugins = array();
        $seen_slugs = array();
        
        foreach ($all_plugins as $plugin) {
            if (!in_array($plugin['slug'], $seen_slugs)) {
                $unique_plugins[] = $plugin;
                $seen_slugs[] = $plugin['slug'];
                if (count($unique_plugins) >= 500) {
                    break;
                }
            }
        }
        
        $plugins = $unique_plugins;
        
        if ( false === $plugins ) {
            wp_send_json_error( array( 'message' => 'Failed to fetch plugins from WordPress.org API' ) );
        }
        
        wp_send_json_success( array(
            'plugins' => $plugins,
            'count' => count( $plugins )
        ) );
    }
    
    /**
     * AJAX handler for install nonces
     */
    public function ajax_get_install_nonce() {
        check_ajax_referer( 'hidden_gems_nonce', 'nonce' );
        
        if ( ! current_user_can( 'install_plugins' ) ) {
            wp_die( 'Insufficient permissions' );
        }
        
        $slug = sanitize_text_field( $_POST['slug'] );
        $install_url = wp_nonce_url( admin_url( "update.php?action=install-plugin&plugin=" . $slug ), "install-plugin_" . $slug );
        
        wp_send_json_success( array(
            'install_url' => $install_url
        ) );
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
        echo '<div class="loading-status" style="text-align: center; padding: 40px; color: #646970; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 200px;">';
        echo '<div class="spinner is-active" style="margin: 0 auto 15px;"></div>';
        echo '<p style="margin: 0; font-size: 16px; font-weight: 500;">Discovering hidden gems from WordPress.org...</p>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        // Add aggressive cache busting and use only WordPress core classes
        $version = time() . rand(1000, 9999);
        
        // Force cache busting with meta tags
        echo '<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">';
        echo '<meta http-equiv="Pragma" content="no-cache">';
        echo '<meta http-equiv="Expires" content="0">';
        ?>
        <style>
        /* Cache busted: <?php echo $version; ?> */
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
        /* Larger pagination numbers */
        .tablenav-pages .page-numbers {
            font-size: 14px !important;
            padding: 8px 12px !important;
            min-width: 32px !important;
            text-align: center !important;
        }
        .tablenav-pages .current {
            font-weight: bold !important;
            background: #0073aa !important;
            color: white !important;
        }
        .tablenav-pages .button {
            font-size: 14px !important;
            padding: 8px 12px !important;
        }
        </style>
        
        <script type="text/javascript">
        // Cache busted: <?php echo $version; ?>
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
            
            // Load plugins from WordPress.org API
            loadPluginsFromAPI();
            
            function loadPluginsFromAPI() {
                console.log('Hidden Gems: Discovering hidden gems from WordPress.org API...');
                console.log('Hidden Gems: ajaxurl available:', typeof ajaxurl !== 'undefined' ? ajaxurl : 'UNDEFINED');
                
                // Load a large set of plugins for client-side filtering
                var formData = new FormData();
                formData.append('action', 'hidden_gems_fetch_plugins');
                formData.append('nonce', '<?php echo wp_create_nonce( 'hidden_gems_nonce' ); ?>');
                
                console.log('Hidden Gems: Making AJAX request to:', ajaxurl);
                
                fetch(ajaxurl, {
                    method: 'POST',
                    body: formData
                })
                .then(function(response) {
                    return response.json();
                })
                .then(function(data) {
                    if (data.success) {
                        allPlugins = data.data.plugins;
                        console.log('Hidden Gems: Found', allPlugins.length, 'potential hidden gems from API');
                        applyCurrentFilters(1); // Start with page 1
                    } else {
                        console.log('Hidden Gems: Failed to load plugins:', data.data.message);
                        document.getElementById('plugin-results').innerHTML = '<p style="color: red;">Error loading plugins: ' + data.data.message + '</p>';
                    }
                })
                .catch(function(error) {
                    console.log('Hidden Gems: AJAX failed:', error);
                    document.getElementById('plugin-results').innerHTML = '<p style="color: red;">AJAX Error: ' + error + '</p>';
                });
            } // Close loadPluginsFromAPI function
            
            // Handle filter form submission
            document.getElementById('plugin-filter-form').addEventListener('submit', function(e) {
                e.preventDefault();
                console.log('Hidden Gems: Form submitted');
                applyCurrentFilters();
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
                var html = '';
                
                if (plugins.length === 0) {
                    html = '<div class="no-results" style="text-align: center; padding: 40px; color: #646970;"><h3>No hidden gems found</h3><p>Try adjusting your search criteria or filters to discover more gems.</p></div>';
                } else {
                    plugins.forEach(function(plugin) {
                        var tags = plugin.tags || {};
                        var icons = plugin.icons || {};
                        var icon = icons['1x'] || icons['2x'] || icons['default'] || '';
                        
                        // Determine if this is a hidden gem
                        var isHiddenGem = plugin.active_installs < 10000 && plugin.rating >= 60; // Under 10K installs and 3+ stars
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
                        if (isHiddenGem) {
                            html += ' <span style="background: #ffd700; color: #000; padding: 2px 6px; border-radius: 3px; font-size: 11px; font-weight: bold;">💎 Hidden Gem</span>';
                        }
                        html += '</h3>';
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
                        
                        // Enhanced install count with context
                        var installText = formatInstalls(plugin.active_installs) + ' Active Installations';
                        if (plugin.active_installs < 1000) {
                            installText += ' - Undiscovered!';
                        } else if (plugin.active_installs < 10000) {
                            installText += ' - Hidden Gem!';
                        }
                        html += '<div class="column-downloaded">' + installText + '</div>';
                        html += '</div>';
                        html += '</div>';
                    });
                }
                
                // Add pagination if there are multiple pages
                if (totalPages && totalPages > 1) {
                    // Build page links using WordPress core format
                    var pageLinks = '<span class="displaying-num">' + totalCount + ' hidden gems</span>';
                    pageLinks += '<span class="pagination-links">';
                    
                    if (currentPage > 1) {
                        pageLinks += '<a class="prev-page button" href="#" data-page="' + (currentPage - 1) + '"><span class="screen-reader-text">Previous page</span><span aria-hidden="true">‹</span></a>';
                    } else {
                        pageLinks += '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">‹</span>';
                    }
                    
                    // Show page numbers (limit to 5 pages around current)
                    var startPage = Math.max(1, currentPage - 2);
                    var endPage = Math.min(totalPages, currentPage + 2);
                    
                    if (startPage > 1) {
                        pageLinks += '<a class="page-numbers" href="#" data-page="1">1</a>';
                        if (startPage > 2) {
                            pageLinks += '<span class="page-numbers dots">…</span>';
                        }
                    }
                    
                    for (var i = startPage; i <= endPage; i++) {
                        if (i === currentPage) {
                            pageLinks += '<span class="page-numbers current" aria-current="page">' + i + '</span>';
                        } else {
                            pageLinks += '<a class="page-numbers" href="#" data-page="' + i + '">' + i + '</a>';
                        }
                    }
                    
                    if (endPage < totalPages) {
                        if (endPage < totalPages - 1) {
                            pageLinks += '<span class="page-numbers dots">…</span>';
                        }
                        pageLinks += '<a class="page-numbers" href="#" data-page="' + totalPages + '">' + totalPages + '</a>';
                    }
                    
                    if (currentPage < totalPages) {
                        pageLinks += '<a class="next-page button" href="#" data-page="' + (currentPage + 1) + '"><span class="screen-reader-text">Next page</span><span aria-hidden="true">›</span></a>';
                    } else {
                        pageLinks += '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">›</span>';
                    }
                    
                    pageLinks += '</span>';
                    
                    // Use proper WordPress core pagination structure
                    html += '<div class="tablenav"><div class="tablenav-pages" style="margin: 1em 0">' + pageLinks + '</div></div>';
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
                        formData.append('nonce', '<?php echo wp_create_nonce( 'hidden_gems_nonce' ); ?>');
                    
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
                if (e.target.classList.contains('page-numbers') || e.target.classList.contains('prev-page') || e.target.classList.contains('next-page')) {
                    e.preventDefault();
                    var page = e.target.getAttribute('data-page');
                    console.log('Hidden Gems: Pagination clicked, page:', page);
                    if (page) {
                        applyCurrentFilters(parseInt(page));
                        // Scroll to top of results
                        document.getElementById('plugin-results').scrollIntoView({ behavior: 'smooth', block: 'start' });
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
        </script>
        <?php
        
        echo '</div>';
    }
    
    /**
     * Render filter interface
     */
    private function render_filter_interface() {
        // Get current filter values from URL - only gem-finding filters
        $current_max_installs = isset( $_GET['max_installs'] ) ? absint( $_GET['max_installs'] ) : 100000; // Default to 100K for more results
        $current_min_quality = isset( $_GET['min_quality'] ) ? absint( $_GET['min_quality'] ) : 0; // Default to any rating for more results
        $current_sort = isset( $_GET['sort'] ) ? sanitize_text_field( wp_unslash( $_GET['sort'] ) ) : 'newest';
        $current_search = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '';
        
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