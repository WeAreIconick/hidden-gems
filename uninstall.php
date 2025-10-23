<?php
/**
 * Uninstall script for Ctrl+Find plugin
 * 
 * This file is executed when the plugin is deleted via WordPress admin.
 * It cleans up all plugin data, options, and transients.
 */

// If uninstall not called from WordPress, exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Security check - ensure user has proper capabilities
if ( ! current_user_can( 'delete_plugins' ) ) {
    exit;
}

/**
 * Clean up plugin data
 */
function ctrl_find_cleanup() {
    
    // Delete plugin options (if any were created)
    delete_option( 'ctrl_find_settings' );
    delete_option( 'ctrl_find_version' );
    
    // Delete site options (multisite)
    delete_site_option( 'ctrl_find_settings' );
    delete_site_option( 'ctrl_find_version' );
    
    // Delete transients
    delete_transient( 'ctrl_find_popular_tags' );
    
    // Clear any cached data
    wp_cache_delete( 'ctrl_find_popular_tags', 'ctrl_find' );
    
    // Clear all caches related to this plugin
    wp_cache_flush_group( 'ctrl_find' );
    
    // Remove any scheduled hooks
    wp_clear_scheduled_hook( 'ctrl_find_cleanup_cache' );
    
    // Remove any custom database tables (if created)
    global $wpdb;
    
    // Example of removing a custom table (uncomment if needed):
    // $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}ctrl_find_cache" );
    
    // Remove any user meta (if created)
    delete_metadata( 'user', 0, 'ctrl_find_preferences', '', true );
    
    // Remove any post meta (if created)
    delete_metadata( 'post', 0, 'ctrl_find_meta', '', true );
    
    // Remove any comment meta (if created)
    delete_metadata( 'comment', 0, 'ctrl_find_meta', '', true );
    
    // Remove any term meta (if created)
    delete_metadata( 'term', 0, 'ctrl_find_meta', '', true );
    
    // Clear any object cache
    if ( function_exists( 'wp_cache_delete_group' ) ) {
        wp_cache_delete_group( 'ctrl_find' );
    }
}

// Run cleanup
ctrl_find_cleanup();

// Plugin uninstalled and cleaned up successfully
