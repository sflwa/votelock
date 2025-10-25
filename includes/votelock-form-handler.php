<?php
/**
 * File: includes/votelock-form-handler.php
 * Handles the Gravity Forms submission hook for anonymization and cleanup.
 * NOTE: Only loaded if Gravity Forms is active.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Ensure the hook is added after settings are loaded
add_action( 'init', 'votelock_register_gf_hook' );

function votelock_register_gf_hook() {
    // Use the globally defined helper function
    $settings = votelock_get_settings();
    $target_form_id = $settings['form_id'];

    if ( $target_form_id > 0 ) {
        // Hook runs after the form entry is saved to the database.
        add_action( "gform_after_submission_{$target_form_id}", 'votelock_anonymize_and_destroy_key', 10, 2 );
    }
}

function votelock_anonymize_and_destroy_key( $entry, $form ) {
    global $wpdb;
    $key_table = $wpdb->prefix . 'votelock_access_keys';
    $entry_table = $wpdb->prefix . 'gf_entry';
    
    $user_id = rgar( $entry, 'created_by' ); 
    $entry_id = rgar( $entry, 'id' );

    if ( empty( $user_id ) || ! is_numeric( $user_id ) || absint($user_id) === 0 ) {
        return; 
    }
    
    // 1. DELETE THE ACCESS KEY (Enforces single-submission)
    $wpdb->delete( $key_table, array( 'user_id' => absint($user_id) ), array( '%d' ) );

    // 2. ANONYMIZE THE GF ENTRY
    $wpdb->update( 
        $entry_table, 
        array( 
            'created_by' => 0,  // Set to 0 to break the link to the user
            'ip' => ''          // Clear IP for maximum anonymity
        ), 
        array( 'id' => absint($entry_id) ), 
        array( '%d', '%s' ), 
        array( '%d' ) 
    );
}
