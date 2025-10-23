<?php
/**
 * Uninstall script for Hidden Gems plugin
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
function hidden_gems_cleanup() {
    
    // Delete plugin options (if any were created)
    delete_option( 'hidden_gems_settings' );
    delete_option( 'hidden_gems_version' );
    
    // Delete site options (multisite)
    delete_site_option( 'hidden_gems_settings' );
    delete_site_option( 'hidden_gems_version' );
    
    // Delete transients
    delete_transient( 'hidden_gems_popular_tags' );
    delete_transient( 'hidden_gems_plugins_cache' );
    
    // Clear any cached data
    wp_cache_delete( 'hidden_gems_popular_tags', 'hidden_gems' );
    
    // Clear all caches related to this plugin
    wp_cache_flush_group( 'hidden_gems' );
    
    // Remove any scheduled hooks
    wp_clear_scheduled_hook( 'hidden_gems_cleanup_cache' );
    
    // Remove any custom database tables (if created)
    global $wpdb;
    
    // Example of removing a custom table (uncomment if needed):
    // $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}hidden_gems_cache" );
    
    // Remove any user meta (if created)
    delete_metadata( 'user', 0, 'hidden_gems_preferences', '', true );
    
    // Remove any post meta (if created)
    delete_metadata( 'post', 0, 'hidden_gems_meta', '', true );
    
    // Remove any comment meta (if created)
    delete_metadata( 'comment', 0, 'hidden_gems_meta', '', true );
    
    // Remove any term meta (if created)
    delete_metadata( 'term', 0, 'hidden_gems_meta', '', true );
    
    // Clear any object cache
    if ( function_exists( 'wp_cache_delete_group' ) ) {
        wp_cache_delete_group( 'hidden_gems' );
    }
}

// Run cleanup
hidden_gems_cleanup();

// Plugin uninstalled and cleaned up successfully
