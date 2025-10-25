<?php
/**
 * File: includes/votelock-shortcode.php
 * Handles the access control logic via the [access_key_gate] shortcode.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Hook for the shortcode
add_shortcode( 'access_key_gate', 'votelock_access_gate_handler' );

function votelock_access_gate_handler( $atts ) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'votelock_access_keys';
    
    // A. CHECK LOGIN STATUS
    if ( ! is_user_logged_in() ) {
        return '<div class="votelock-error notice-box">❌ You must be logged in to access the ballot.</div>';
    }
    
    $user_id = get_current_user_id();
    // Sanitize the key parameter from the URL
    $key_param = isset( $_GET['key'] ) ? sanitize_text_field( $_GET['key'] ) : '';

    // B. CHECK FOR KEY IN URL
    if ( empty( $key_param ) ) {
        return '<div class="votelock-error notice-box">❌ Access Denied: The ballot link requires a unique key.</div>';
    }

    // C. CHECK KEY MATCH
    // Retrieve the key assigned to the logged-in user
    $key_exists = $wpdb->get_var( $wpdb->prepare( 
        "SELECT access_key FROM $table_name WHERE user_id = %d", 
        $user_id 
    ) );
    
    if ( is_null( $key_exists ) ) {
        // User has no key, meaning they either voted already (key was deleted) or were never issued one.
        return '<div class="votelock-error notice-box">🛑 **You have already voted, or your voting key has expired.**</div>';
    }

    if ( $key_exists !== $key_param ) {
        // Key exists but URL key doesn't match the one assigned to the logged-in user.
        return '<div class="votelock-error notice-box">⚠️ **Invalid Key:** The key provided does not match your assigned voting token.</div>';
    }
    
    // D. ACCESS GRANTED: RENDER THE FORM
    
    // IMPORTANT: Replace '123' with your actual Gravity Forms ID
    $form_id = 123; // <-- *** UPDATE THIS ID ***
    
    // Render the Gravity Form using its shortcode
    $form_output = do_shortcode( '[gravityform id="' . absint($form_id) . '" title="false" description="false" ajax="true"]' );
    
    return $form_output;
}
