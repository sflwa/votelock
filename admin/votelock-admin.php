<?php
/**
 * File: admin/votelock-admin.php
 * Handles the admin page for generating and exporting voting access keys.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// --- ADMIN MENU & PAGE SETUP ---

add_action( 'admin_menu', 'votelock_admin_menu' );
function votelock_admin_menu() {
    add_management_page( 
        'Voting Key Generator', 
        'Voting Keys', 
        'manage_options', 
        'votelock-keys', 
        'votelock_keys_page_content' 
    );
}

function votelock_keys_page_content() {
    // Handle form submissions first
    if ( isset( $_POST['votelock_generate'] ) ) {
        votelock_handle_key_generation();
    } elseif ( isset( $_POST['votelock_export'] ) ) {
        votelock_handle_key_export();
    }
    
    ?>
    <div class="wrap">
        <h2>Generate and Export Voting Keys</h2>
        <p>Use the generator below to create unique, one-time access keys for users based on their role.</p>
        <form method="post">
            <?php wp_nonce_field( 'votelock_generate_keys' ); ?>
            <p>
                <label for="role">Target Users by Role:</label>
                <?php wp_dropdown_roles(); // Generates a <select> with user roles ?> 
            </p>
            <p class="submit">
                <input type="submit" name="votelock_generate" class="button button-primary" value="Generate Keys for Selected Role">
                <input type="submit" name="votelock_export" class="button button-secondary" value="Export ALL Keys to CSV">
            </p>
        </form>
    </div>
    <?php
}

// --- KEY GENERATION LOGIC ---

function votelock_handle_key_generation() {
    if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'votelock_generate_keys' ) ) {
        wp_die( 'Security check failed.', 403 );
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'votelock_access_keys';
    $role = sanitize_key( $_POST['role'] );
    $user_query = new WP_User_Query( array( 'role' => $role, 'fields' => 'ID' ) );
    $users_inserted = 0;

    foreach ( $user_query->get_results() as $user_id ) {
        // Check if user already has a key to prevent duplicates
        $key_exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table_name WHERE user_id = %d", $user_id ) );
        
        if ( ! $key_exists ) {
            // Generate a cryptographically secure 64-character hex key
            $access_key = bin2hex( random_bytes( 32 ) ); 
            $wpdb->insert( $table_name, array( 'user_id' => $user_id, 'access_key' => $access_key ), array( '%d', '%s' ) );
            $users_inserted++;
        }
    }
    
    // Display result message
    echo '<div class="notice notice-success is-dismissible"><p>✅ Generated ' . absint($users_inserted) . ' new keys for users with the "<strong>' . esc_html($role) . '</strong>" role.</p></div>';
}


// --- KEY EXPORT LOGIC ---

function votelock_handle_key_export() {
    if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'votelock_generate_keys' ) ) {
        wp_die( 'Security check failed.', 403 );
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'votelock_access_keys';
    
    // Join to get user email for easier management/distribution
    $results = $wpdb->get_results( "
        SELECT T1.user_id, T2.user_email, T1.access_key 
        FROM $table_name T1 
        INNER JOIN {$wpdb->users} T2 ON T1.user_id = T2.ID", 
        ARRAY_A 
    );

    if ( empty( $results ) ) {
        // Display a warning and stop if no keys exist
        echo '<div class="notice notice-warning is-dismissible"><p>No keys available to export.</p></div>';
        return;
    }

    // Set headers for CSV download
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename=voting_access_keys_' . time() . '.csv' );
    
    $output = fopen( 'php://output', 'w' );
    
    // CSV Header Row
    $headers = array( 'user_id', 'email', 'access_key', 'full_url' );
    fputcsv( $output, $headers );

    // IMPORTANT: You MUST replace 'YOUR_BALLOT_PAGE_ID' with the actual ID of your WordPress ballot page.
    $ballot_page_id = 123; // <-- *** UPDATE THIS ID ***
    $ballot_url = get_permalink( $ballot_page_id );

    // Data Rows
    foreach ( $results as $row ) {
        // Construct the full voting URL
        $full_url = add_query_arg( 'key', $row['access_key'], $ballot_url );
        
        $csv_data = array( $row['user_id'], $row['user_email'], $row['access_key'], $full_url );
        fputcsv( $output, $csv_data );
    }
    
    fclose( $output );
    exit; // Terminate script execution after file output
}
