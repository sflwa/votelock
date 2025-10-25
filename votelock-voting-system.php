<?php
/**
 * Plugin Name: VoteLock Anonymous Voting System
 * Description: Restricts Gravity Forms submissions to one per logged-in user using a disposable access key, then anonymizes the entry.
 * Version: 1.0.0
 * Author: Your Name
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Global constants
define( 'VOTELOCK_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'VOTELOCK_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'VOTELOCK_DB_VERSION', '1.0' );

// --- DATABASE SETUP ---

/**
 * Creates the custom database table on plugin activation.
 */
register_activation_hook( __FILE__, 'votelock_create_key_table' );
function votelock_create_key_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'votelock_access_keys';
    
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) NOT NULL,
        access_key VARCHAR(64) NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY user_id (user_id),
        UNIQUE KEY access_key (access_key)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );

    add_option( 'votelock_db_version', VOTELOCK_DB_VERSION );
}


// --- INCLUDES ---

// Check if Gravity Forms is active before loading form-specific functions
if ( class_exists( 'GFAPI' ) ) {
    require_once VOTELOCK_PLUGIN_PATH . 'includes/votelock-form-handler.php';
} else {
    // Optionally display an admin notice if GF is missing
    add_action( 'admin_notices', function() {
        echo '<div class="notice notice-error"><p><strong>VoteLock Plugin:</strong> Requires Gravity Forms to be installed and active.</p></div>';
    });
}

require_once VOTELOCK_PLUGIN_PATH . 'admin/votelock-admin.php';
require_once VOTELOCK_PLUGIN_PATH . 'includes/votelock-shortcode.php';
