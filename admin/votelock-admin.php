<?php
/**
 * File: admin/votelock-admin.php
 * Handles the admin pages for settings, key generation, and export.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// --- ADMIN MENU SETUP ---

add_action( 'admin_menu', 'votelock_admin_menu' );
function votelock_admin_menu() {
    // Top-level menu page for the plugin
    add_menu_page(
        'VoteLock System',
        'VoteLock',
        'manage_options',
        'votelock-settings',
        'votelock_settings_page_content',
        'dashicons-lock',
        30
    );

    // Sub-menu for Settings
    add_submenu_page(
        'votelock-settings',
        'VoteLock Settings',
        'Settings',
        'manage_options',
        'votelock-settings',
        'votelock_settings_page_content'
    );

    // Sub-menu for Key Generator (Tools page)
    add_submenu_page(
        'votelock-settings', // Parent slug
        'Voting Key Generator', 
        'Key Generator', 
        'manage_options', 
        'votelock-keys-generator', 
        'votelock_keys_generator_page_content'
    );
}

// --- SETTINGS PAGE CONTENT ---

function votelock_settings_page_content() {
    ?>
    <div class="wrap">
        <h2>VoteLock System Settings</h2>
        
        <?php 
        // Handle settings save
        if ( isset( $_POST['votelock_save_settings'] ) ) {
            votelock_handle_settings_save();
        }
        
        $settings = votelock_get_settings();
        ?>
        
        <form method="post">
            <?php wp_nonce_field( 'votelock_save_settings' ); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="votelock_ballot_form_id">Gravity Forms Ballot ID</label></th>
                    <td>
                        <input type="number" id="votelock_ballot_form_id" name="votelock_ballot_form_id" 
                               value="<?php echo esc_attr( $settings['form_id'] ); ?>" 
                               min="1" required />
                        <p class="description">The Gravity Forms ID (e.g., 1, 2, 3) for the ballot form.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="votelock_ballot_page_id">WordPress Ballot Page ID</label></th>
                    <td>
                        <input type="number" id="votelock_ballot_page_id" name="votelock_ballot_page_id" 
                               value="<?php echo esc_attr( $settings['page_id'] ); ?>" 
                               min="1" required />
                        <p class="description">The WordPress Page ID where the <code>[access_key_gate]</code> shortcode is placed.</p>
                    </td>
                </tr>
            </table>
            <?php submit_button( 'Save Settings', 'primary', 'votelock_save_settings' ); ?>
        </form>
    </div>
    <?php
}

/**
 * Handles saving the form and page IDs to the options table.
 */
function votelock_handle_settings_save() {
    if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'votelock_save_settings' ) ) {
        return;
    }
    
    $form_id = absint( $_POST['votelock_ballot_form_id'] );
    $page_id = absint( $_POST['votelock_ballot_page_id'] );
    
    if ( $form_id > 0 && $page_id > 0 ) {
        $new_settings = [
            'form_id' => $form_id,
            'page_id' => $page_id
        ];
        
        update_option( 'votelock_plugin_settings', $new_settings );
        echo '<div class="notice notice-success is-dismissible"><p>✅ Settings saved successfully.</p></div>';
    } else {
        echo '<div class="notice notice-error is-dismissible"><p>⚠️ Both Form ID and Page ID must be valid positive numbers.</p></div>';
    }
}

/**
 * Retrieves the stored plugin settings.
 * @return array
 */
function votelock_get_settings() {
    $defaults = [
        'form_id' => 0,
        'page_id' => 0
    ];
    return get_option( 'votelock_plugin_settings', $defaults );
}

// --- KEY GENERATOR PAGE CONTENT ---

function votelock_keys_generator_page_content() {
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
                <label for="votelock_role">Target Users by Role:</label>
                
                <select name="role" id="votelock_role" required>
                    <?php wp_dropdown_roles(); // Outputs <option> tags ?> 
                </select>
                
            </p>
            <p class="submit">
                <input type="submit" name="votelock_generate" class="button button-primary" value="Generate Keys for Selected Role">
                <input type="submit" name="votelock_export" class="button button-secondary" value="Export ALL Keys to CSV">
            </p>
        </form>
    </div>
    <?php
}


// --- KEY GENERATION LOGIC (FIXED) ---

function votelock_handle_key_generation() {
    if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'votelock_generate_keys' ) ) {
        wp_die( 'Security check failed.', 403 );
    }
    
    // FIX: Use null coalescing operator (??) to prevent the "Undefined array key" warning
    $role = sanitize_key( $_POST['role'] ?? '' ); 
    
    if ( empty( $role ) ) {
        echo '<div class="notice notice-error is-dismissible"><p>⚠️ Please select a user role.</p></div>';
        return;
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'votelock_access_keys';
    
    $user_query = new WP_User_Query( array( 'role' => $role, 'fields' => 'ID' ) );
    $users_inserted = 0;

    foreach ( $user_query->get_results() as $user_id ) {
        $key_exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table_name WHERE user_id = %d", $user_id ) );
        
        if ( ! $key_exists ) {
            $access_key = bin2hex( random_bytes( 32 ) ); 
            $wpdb->insert( $table_name, array( 'user_id' => $user_id, 'access_key' => $access_key ), array( '%d', '%s' ) );
            $users_inserted++;
        }
    }
    
    echo '<div class="notice notice-success is-dismissible"><p>✅ Generated ' . absint($users_inserted) . ' new keys for users with the "<strong>' . esc_html($role) . '</strong>" role.</p></div>';
}

// --- KEY EXPORT LOGIC (Same as before) ---

function votelock_handle_key_export() {
    if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'votelock_generate_keys' ) ) {
        wp_die( 'Security check failed.', 403 );
    }

    $settings = votelock_get_settings();
    $ballot_page_id = $settings['page_id']; 

    if ( $ballot_page_id === 0 ) {
        echo '<div class="notice notice-error is-dismissible"><p>⚠️ Please configure the **WordPress Ballot Page ID** in the VoteLock Settings before exporting.</p></div>';
        return;
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'votelock_access_keys';
    
    $results = $wpdb->get_results( "
        SELECT T1.user_id, T2.user_email, T1.access_key 
        FROM $table_name T1 
        INNER JOIN {$wpdb->users} T2 ON T1.user_id = T2.ID", 
        ARRAY_A 
    );

    if ( empty( $results ) ) {
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

    $ballot_url = get_permalink( $ballot_page_id );
    
    // Data Rows
    foreach ( $results as $row ) {
        $full_url = add_query_arg( 'key', $row['access_key'], $ballot_url );
        $csv_data = array( $row['user_id'], $row['user_email'], $row['access_key'], $full_url );
        fputcsv( $output, $csv_data );
    }
    
    fclose( $output );
    exit;
}
