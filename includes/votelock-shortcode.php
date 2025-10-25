<?php
/**
 * File: includes/votelock-shortcode.php
 * Handles the access control logic via the [access_key_gate] shortcode.
 * Implements a two-step confirmation before consuming the key.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_shortcode( 'access_key_gate', 'votelock_access_gate_handler' );

function votelock_access_gate_handler( $atts ) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'votelock_access_keys';
    
    // Get configuration settings
    $settings = votelock_get_settings();
    $form_id = $settings['form_id'];
    
    $user_id = get_current_user_id();
    $key_param = isset( $_GET['key'] ) ? sanitize_text_field( $_GET['key'] ) : '';
    $consent_submitted = isset( $_POST['votelock_consent'] ) && $_POST['votelock_consent'] === 'yes';

    // --- 0. Initial Validation (Applies to both steps) ---

    if ( $form_id === 0 && current_user_can( 'manage_options' ) ) {
        return '<div class="votelock-error notice-box admin-only">❌ **ADMIN NOTICE:** Configure the Form ID in VoteLock Settings.</div>';
    }
    if ( ! is_user_logged_in() ) {
        return '<div class="votelock-error notice-box">❌ You must be logged in to access the ballot.</div>';
    }
    if ( empty( $key_param ) ) {
        return '<div class="votelock-error notice-box">❌ Access Denied: The ballot link requires a unique key.</div>';
    }

    // Check if the key is still valid and belongs to the user
    $key_exists_db = $wpdb->get_var( $wpdb->prepare( 
        "SELECT access_key FROM $table_name WHERE user_id = %d", 
        $user_id 
    ) );
    
    if ( is_null( $key_exists_db ) ) {
        // Key is already consumed (either by previous vote or previous consent)
        return '<div class="votelock-error notice-box">🛑 **You have already voted, or your access key has been used.**</div>';
    }

    if ( $key_exists_db !== $key_param ) {
        // Key exists but is incorrect for the user
        return '<div class="votelock-error notice-box">⚠️ **Invalid Key:** The key provided does not match your assigned voting token.</div>';
    }
    
    // --- 1. Key Consumption and Form Display ---

    if ( $consent_submitted ) {
        // The user submitted the confirmation form.
        
        // Final check on the nonce
        if ( ! check_admin_referer( 'votelock_consent_check_' . $user_id ) ) {
             return '<div class="votelock-error notice-box">❌ Security check failed. Please refresh the page.</div>';
        }
        
        // CRUCIAL STEP: DELETE THE KEY BEFORE RENDERING THE FORM
        $wpdb->delete( $table_name, array( 'user_id' => $user_id ), array( '%d' ) );
        
        // Render the Gravity Form
        return do_shortcode( '[gravityform id="' . absint($form_id) . '" title="false" description="false" ajax="true"]' );

    } 
    
    // --- 2. Confirmation Prompt (Initial Page Load) ---
    
    ob_start();
    ?>
    <div class="votelock-confirmation-box" style="padding: 20px; border: 1px solid #ffcc00; background-color: #fff8e1; border-radius: 5px;">
        <h3 style="margin-top: 0;">🛑 Are You Ready to Vote?</h3>
        <p>By clicking "Yes, I Agree," your unique access key will be **permanently consumed.**</p>
        <p>You **will not be able** to come back later if you navigate away from the ballot, and admins **will not be able to reset** your access.</p>
        
        <form method="post" action="<?php echo esc_url( add_query_arg( 'key', $key_param, get_permalink() ) ); ?>">
            <?php wp_nonce_field( 'votelock_consent_check_' . $user_id ); ?>
            <input type="hidden" name="votelock_consent" value="yes" />

            <p>
                <input type="checkbox" id="votelock_agree" name="votelock_agree" required style="margin-right: 5px;">
                <label for="votelock_agree">I understand and agree to permanently consume my voting key to access the ballot.</label>
            </p>
            
            <input type="submit" class="button button-primary" value="Yes, I Agree & Access Ballot" />
        </form>
    </div>
    <?php
    return ob_get_clean();
}
