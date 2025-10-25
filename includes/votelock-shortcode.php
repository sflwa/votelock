<?php
/**
 * File: includes/votelock-shortcode.php
 * Handles the access control logic via the [access_key_gate] shortcode.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Function to get settings is now defined in votelock-admin.php, but we redefine it
// or ensure it's loaded if needed. For simplicity, we'll assume votelock-admin.php is loaded first.

add_shortcode( 'access_key_gate', 'votelock_access_gate_handler' );

function votelock_access_gate_handler( $atts ) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'votelock_access_keys';
    
    // Get settings dynamically
    $settings = votelock_get_settings();
    $form_id = $settings['form_id'];
    
    if ( $form_id === 0 ) {
        // Hide the form if not configured (only shown to admins)
        if ( current_user_can( 'manage_options' ) ) {
             return '<div class="votelock-error notice-box admin-only">❌ **ADMIN NOTICE:** Please configure the Gravity Forms Ballot ID in the VoteLock Settings.</div>';
        }
        return '';
    }

    // A. CHECK LOGIN STATUS
    if ( ! is_user_logged_in() ) {
        return '<div class="votelock-error notice-box">❌ You must be logged in to access the ballot.</div>';
    }
    
    $user_id = get_current_user_id();
    $key_param = isset( $_GET['key'] ) ? sanitize_text_field( $_GET['key'] ) : '';

    // B. CHECK FOR KEY IN URL
    if ( empty( $key_param ) ) {
        return '<div class="votelock-error notice-box">❌ Access Denied: The ballot link requires a unique key.</div>';
    }

    // C. CHECK KEY MATCH (The core restriction logic)
    $key_exists = $wpdb->get_var( $wpdb->prepare( 
        "SELECT access_key FROM $table_name WHERE user_id = %d", 
        $user_id 
    ) );
    
    if ( is_null( $key_exists ) ) {
        return '<div class="votelock-error notice-box">🛑 **You have already voted, or your voting key has expired.**</div>';
    }

    if ( $key_exists !== $key_param ) {
        return '<div class="votelock-error notice-box">⚠️ **Invalid Key:** The key provided does not match your assigned voting token.</div>';
    }
    
    // D. ACCESS GRANTED: RENDER THE FORM
    // Render the Gravity Form using the dynamically retrieved ID
    $form_output = do_shortcode( '[gravityform id="' . absint($form_id) . '" title="false" description="false" ajax="true"]' );
    
    return $form_output;
}
