<?php
/**
 * File: includes/votelock-form-handler.php
 * Handles the Gravity Forms submission hook for anonymization and cleanup.
 * NOTE: Only loaded if Gravity Forms is active.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// IMPORTANT: Replace '123' with your actual Gravity Forms ID
$target_form_id = 123; // <-- *** UPDATE THIS ID ***

// Hook runs after the form entry is saved to the database.
add_action( "gform_after_submission_{$target_form_id}", 'votelock_anonymize_and_destroy_key', 10, 2 );

function votelock_anonymize_and_destroy_key( $entry, $form ) {
    global $wpdb;
    $key_table = $wpdb->prefix . 'votelock_access_keys';
    $entry_table = $wpdb->prefix . 'gf_entry';
    
    $user_id = rgar( $entry, 'created_by' ); // GF stores the user ID in created_by initially
    $entry_id = rgar( $entry, 'id' );

    if ( empty( $user_id ) || ! is_numeric( $user_id ) ) {
        // If created_by is already missing or invalid, something is wrong.
        return;
    }
    
    // 1. DELETE THE ACCESS KEY (Enforces single-submission)
    $wpdb->delete( $key_table, array( 'user_id' => absint($user_id) ), array( '%d' ) );

    // 2. ANONYMIZE THE GF ENTRY
    // Set created_by (User ID) to 0 and clear the IP address field.
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
    
    // The entry is now saved, but the link back to the user is broken in the database.
}
