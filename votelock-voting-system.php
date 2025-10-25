<?php
/**
 * Plugin Name: VoteLock Anonymous Voting System
 * Description: Restricts Gravity Forms submissions to one per logged-in user using a disposable access key, then anonymizes the entry.
 * Version: 1.0.1
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
// ... (votelock_create_key_table remains unchanged) ...
register_activation_hook( __FILE__, 'votelock_create_key_table' );
function votelock_create_key_table() {
    // ... (unchanged code) ...
    global $wpdb;
    $table_name = $wpdb->prefix . 'votelock_access_keys';
    // ... (unchanged code) ...
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
    add_option( 'votelock_db_version', VOTELOCK_DB_VERSION );
}


// --- GLOBAL HELPER FUNCTION (MOVED HERE) ---

/**
 * Retrieves the stored plugin settings (Form ID and Page ID).
 * @return array
 */
function votelock_get_settings() {
    $defaults = [
        'form_id' => 0,
        'page_id' => 0
    ];
    // This function must be defined globally and only once.
    return get_option( 'votelock_plugin_settings', $defaults );
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
