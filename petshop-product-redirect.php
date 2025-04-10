<?php
/**
 * Plugin Name: Big Games Shop Product Redirect
 * Description: Adds a metabox to the product sidebar to select a product redirect from an external API and includes a settings page for managing the API URL, cache and global redirection options. Also supports Git‑based updates.
 * Version: 1.1.6
 * Author: Dom Kapelewski
 * Text Domain: petshop-product-redirect
 */

if (  !  defined( 'ABSPATH' ) ) {
    exit;
}

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    require_once __DIR__ . '/vendor/autoload.php';
}

/* ==========================================================================
Plugin Instructions:
This plugin allows you to set up product redirects by selecting a URL from an external API.
It provides a meta box on the product edit screen with a dropdown to choose a redirect URL.
IMPORTANT: The plugin requires the external API endpoint to be active.
Redirects will be disabled if the endpoint is not available.
Global settings on the options page control:
- Enabling redirect on single product pages.
- Displaying the redirect option in the product listing.
Please ensure that the endpoint is active and configured correctly via the settings page.
========================================================================== */

/* ==========================================================================
Plugin Settings: API URL, Transient Time and Global Redirect Options
========================================================================== */

/**
 * Get the API URL and transient expiration time from saved options.
 *
 * @return array Array with 'api_url' and 'transient_expiration'.
 */
function pr_get_plugin_settings() {
    $api_url              = get_option( 'pr_api_url', 'https://big-games.shop/wp-json/wc-products/v1/list' );
    $transient_time_hours = get_option( 'pr_transient_time_hours', 1 );
    $transient_expiration = $transient_time_hours * HOUR_IN_SECONDS;
    return array(
        'api_url'              => $api_url,
        'transient_expiration' => $transient_expiration
    );
}

/* ==========================================================================
Check for External API Endpoint
========================================================================== */

/**
 * Check if the external API endpoint is active and returning data.
 *
 * @return bool True if the endpoint is active, false otherwise.
 */
function pr_endpoint_active() {
    $api_data = pr_get_api_data();
    return  !  empty( $api_data );
}

/* ==========================================================================
API Data and Dropdown Builder Functions
========================================================================== */

/**
 * Retrieve the API data, using transient caching.
 *
 * @return array Array of API data or an empty array if unavailable.
 */
function pr_get_api_data() {
    $settings             = pr_get_plugin_settings();
    $api_url              = $settings['api_url'];
    $transient_expiration = $settings['transient_expiration'];
    $transient_key        = 'pr_api_data';
    $api_data             = get_transient( $transient_key );

    if ( false === $api_data ) {
        $response = wp_remote_get( $api_url, array( 'timeout' => 5 ) );
        if ( is_wp_error( $response ) ) {
            return array(); // Return empty array if the request fails.
        }
        $body     = wp_remote_retrieve_body( $response );
        $api_data = json_decode( $body, true );
        if (  !  is_array( $api_data ) ) {
            $api_data = array();
        }
        set_transient( $transient_key, $api_data, $transient_expiration );
    }

    return $api_data;
}

/**
 * Build the dropdown HTML with the hierarchical products.
 *
 * @param string $selected_redirect The currently selected URL.
 * @param string $select_id         Optional select element ID.
 * @param int    $product_id        Optional product ID for data attributes.
 * @return string HTML markup for the dropdown.
 */
function pr_build_dropdown( $selected_redirect = '', $select_id = 'pr_redirect_url', $product_id = 0 ) {
    $api_data = pr_get_api_data();

    $html = '<select name="' . esc_attr( $select_id ) . '" id="' . esc_attr( $select_id ) . '" style="width:100%;"';
    if ( $product_id ) {
        $html .= ' data-product-id="' . intval( $product_id ) . '"';
        $nonce = wp_create_nonce( 'pr_update_redirect_' . $product_id );
        $html .= ' data-nonce="' . esc_attr( $nonce ) . '" class="pr-redirect-dropdown"';
    }
    $html .= '>';
    $html .= '<option value="">' . __( '-- No Redirect --', 'petshop-product-redirect' ) . '</option>';

    if (  !  empty( $api_data ) && is_array( $api_data ) ) {
        foreach ( $api_data as $parent ) {
            if ( is_array( $parent ) && isset( $parent['name'] ) ) {
                $html .= '<option value="" disabled="disabled">' . esc_html( $parent['name'] ) . '</option>';
                if (  !  empty( $parent['children'] ) && is_array( $parent['children'] ) ) {
                    foreach ( $parent['children'] as $child ) {
                        if ( is_array( $child ) && isset( $child['name'] ) ) {
                            $html .= '<option value="" disabled="disabled">-- ' . esc_html( $child['name'] ) . '</option>';
                            if (  !  empty( $child['products'] ) && is_array( $child['products'] ) ) {
                                foreach ( $child['products'] as $product ) {
                                    if ( is_array( $product ) && isset( $product['url'], $product['name'] ) ) {
                                        $selected = ( $selected_redirect === $product['url'] ) ? 'selected="selected"' : '';
                                        $html .= '<option value="' . esc_attr( $product['url'] ) . '" ' . $selected . '>--- ' . esc_html( $product['name'] ) . '</option>';
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    $html .= '</select>';
    return $html;
}

/* ==========================================================================
Admin Endpoint Notice
========================================================================== */

/**
 * Display an admin notice if the external API endpoint is not active.
 */
function pr_endpoint_admin_notice() {
    if (  !  current_user_can( 'manage_options' ) ) {
        return;
    }
    if (  !  pr_endpoint_active() ) {
        echo '<div class="notice notice-error is-dismissible">';
        echo '<p>' . __( 'The required API endpoint is not available. Please ensure that the endpoint is added at the specified URL in the plugin settings. Redirects are disabled until the endpoint is active.', 'petshop-product-redirect' ) . '</p>';
        echo '</div>';
    }
}
add_action( 'admin_notices', 'pr_endpoint_admin_notice' );

/* ==========================================================================
Admin Metabox for Product Redirect (Product Edit Screen)
========================================================================== */

/**
 * Register the product edit meta box only if the single product redirection option is enabled.
 */
function pr_add_meta_box() {
    if ( get_option( 'pr_redirect_single_enabled', '1' ) !== '1' ) {
        return; // Do not add meta box if single product redirection is disabled globally.
    }
    add_meta_box(
        'product_redirect_meta_box',
        __( 'Product Redirect', 'petshop-product-redirect' ),
        'pr_render_meta_box',
        'product',
        'side',
        'default'
    );
}
add_action( 'add_meta_boxes', 'pr_add_meta_box' );

/**
 * Render the meta box for selecting a redirect URL.
 *
 * @param WP_Post $post Current post object.
 */
function pr_render_meta_box( $post ) {
    wp_nonce_field( 'pr_meta_box_nonce', 'pr_meta_box_nonce_field' );
    $selected_redirect = get_post_meta( $post->ID, '_pr_redirect_url', true );
    echo '<p>' . __( 'Select a product redirect:', 'petshop-product-redirect' ) . '</p>';
    echo pr_build_dropdown( $selected_redirect );
}

/**
 * Save the selected redirect URL from the meta box.
 *
 * @param int $post_id The post ID.
 */
function pr_save_meta_box_data( $post_id ) {
    if (  !  isset( $_POST['pr_meta_box_nonce_field'] ) || !  wp_verify_nonce( $_POST['pr_meta_box_nonce_field'], 'pr_meta_box_nonce' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if (  !  current_user_can( 'edit_product', $post_id ) ) {
        return;
    }
    if ( isset( $_POST['pr_redirect_url'] ) ) {
        $redirect_url = sanitize_text_field( $_POST['pr_redirect_url'] );
        update_post_meta( $post_id, '_pr_redirect_url', $redirect_url );
    }
}
add_action( 'save_post_product', 'pr_save_meta_box_data' );

/* ==========================================================================
Frontend Redirection
========================================================================== */

/**
 * Redirect single product pages if a redirect URL is set and the global option is enabled.
 */
function pr_redirect_product_page() {
    if ( is_singular( 'product' ) ) {
        // Do not redirect if the endpoint is not active.
        if (  !  pr_endpoint_active() ) {
            return;
        }
        global $post;
        $redirect_url = get_post_meta( $post->ID, '_pr_redirect_url', true );
        if (  !  empty( $redirect_url ) ) {
            wp_redirect( esc_url( $redirect_url ) );
            exit;
        }
    }
}
add_action( 'template_redirect', 'pr_redirect_product_page' );

/* ==========================================================================
Admin Notice on Product Edit Screen
========================================================================== */

function pr_product_redirect_admin_notice() {
    global $pagenow, $post;
    $screen = get_current_screen();

    if ( isset( $screen->post_type ) && 'product' === $screen->post_type && in_array( $pagenow, array( 'post.php', 'post-new.php' ), true ) && isset( $post->ID ) ) {
        $redirect_url = get_post_meta( $post->ID, '_pr_redirect_url', true );
        if (  !  empty( $redirect_url ) ) {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p>' . sprintf( __( 'This product is redirecting to <a href="%1$s" target="_blank">%1$s</a>.', 'petshop-product-redirect' ), esc_url( $redirect_url ) ) . '</p>';
            echo '</div>';
        } else {
            echo '<div class="notice notice-error is-dismissible">';
            echo '<p>' . __( 'This product is not set to redirect.', 'petshop-product-redirect' ) . '</p>';
            echo '</div>';
        }
    }
}
add_action( 'admin_notices', 'pr_product_redirect_admin_notice' );

/* ==========================================================================
Inline Dropdown in Product Listing (Admin Column)
========================================================================== */

/**
 * Add a product column for the redirect option if the global listing option is enabled.
 *
 * @param array $columns Array of original columns.
 * @return array Modified columns array.
 */
function pr_add_product_column( $columns ) {
    if ( get_option( 'pr_redirect_listing_enabled', '1' ) !== '1' ) {
        return $columns; // Return unmodified if product listing redirect is disabled.
    }
    $new_columns = array();
    foreach ( $columns as $key => $value ) {
        $new_columns[$key] = $value;
        if ( 'name' === $key ) {
            $new_columns['pr_redirect'] = __( 'Product Redirect', 'petshop-product-redirect' );
        }
    }
    return $new_columns;
}
add_filter( 'manage_edit-product_columns', 'pr_add_product_column', 15 );

/**
 * Render the redirect dropdown in the product listing.
 *
 * @param string $column  Column name.
 * @param int    $post_id Post ID.
 */
function pr_render_product_column( $column, $post_id ) {
    if ( 'pr_redirect' === $column ) {
        $selected_redirect = get_post_meta( $post_id, '_pr_redirect_url', true );
        echo pr_build_dropdown( $selected_redirect, 'pr_redirect_url_' . $post_id, $post_id );
    }
}
add_action( 'manage_product_posts_custom_column', 'pr_render_product_column', 10, 2 );

/* ==========================================================================
AJAX Handler for Updating Redirect from Product Listing
========================================================================== */

function pr_ajax_update_redirect() {
    if (  !  isset( $_POST['nonce'], $_POST['product_id'], $_POST['redirect_url'] ) ) {
        wp_send_json_error( __( 'Missing parameters.', 'petshop-product-redirect' ) );
    }
    $product_id   = absint( $_POST['product_id'] );
    $redirect_url = sanitize_text_field( wp_unslash( $_POST['redirect_url'] ) );
    $nonce        = sanitize_text_field( $_POST['nonce'] );

    if (  !  wp_verify_nonce( $nonce, 'pr_update_redirect_' . $product_id ) ) {
        wp_send_json_error( __( 'Nonce verification failed.', 'petshop-product-redirect' ) );
    }
    if (  !  current_user_can( 'edit_product', $product_id ) ) {
        wp_send_json_error( __( 'Insufficient permissions.', 'petshop-product-redirect' ) );
    }
    update_post_meta( $product_id, '_pr_redirect_url', $redirect_url );
    wp_send_json_success( __( 'Redirect URL updated.', 'petshop-product-redirect' ) );
}
add_action( 'wp_ajax_pr_update_redirect', 'pr_ajax_update_redirect' );

/* ==========================================================================
Enqueue Admin Scripts for Product Listing Page
========================================================================== */

function pr_enqueue_admin_scripts( $hook ) {
    if ( 'edit.php' === $hook && isset( $_GET['post_type'] ) && 'product' === $_GET['post_type'] ) {
        wp_enqueue_script( 'pr-admin-script', plugins_url( 'pr-admin.js', __FILE__ ), array( 'jquery' ), '1.0', true );
        wp_localize_script( 'pr-admin-script', 'pr_ajax_obj', array(
            'ajax_url' => admin_url( 'admin-ajax.php' )
        ) );
    }
}
add_action( 'admin_enqueue_scripts', 'pr_enqueue_admin_scripts' );

function pr_inline_admin_script() {
    $screen = get_current_screen();
    if ( 'edit-product' !== $screen->id ) {
        return;
    }
    ?>
    <style>
        #pr_redirect {
            width:20%;
        }
    </style>
    <script type="text/javascript">
        jQuery(document).ready(function($){
            $('.pr-redirect-dropdown').change(function(){
                var $this     = $(this);
                var productId = $this.data('product-id');
                var redirectUrl = $this.val();
                var nonce = $this.data('nonce');
                $.ajax({
                    url: pr_ajax_obj.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'pr_update_redirect',
                        product_id: productId,
                        redirect_url: redirectUrl,
                        nonce: nonce
                    },
                    success: function(response) {
                        if(response.success) {
                            $this.closest('td').append('<div class="pr-success" style="color:green;">' + response.data + '</div>');
                            setTimeout(function(){ $this.closest('td').find('.pr-success').fadeOut(); }, 3000 );
                        } else {
                            $this.closest('td').append('<div class="pr-error" style="color:red;">' + response.data + '</div>');
                            setTimeout(function(){ $this.closest('td').find('.pr-error').fadeOut(); }, 3000 );
                        }
                    },
                    error: function() {
                        $this.closest('td').append('<div class="pr-error" style="color:red;">AJAX error.</div>');
                        setTimeout(function(){ $this.closest('td').find('.pr-error').fadeOut(); }, 3000 );
                    }
                });
            });
        });
    </script>
    <?php
}
add_action( 'admin_footer', 'pr_inline_admin_script' );

/* ==========================================================================
Plugin Settings Page (Under WooCommerce Menu)
========================================================================== */

/**
 * Add a submenu page for plugin settings.
 */
function pr_add_settings_page() {
    add_submenu_page(
        'woocommerce',
        __( 'Product Redirect Settings', 'petshop-product-redirect' ),
        __( 'Redirect Settings', 'petshop-product-redirect' ),
        'manage_woocommerce',
        'pr-settings',
        'pr_render_settings_page'
    );
}
add_action( 'admin_menu', 'pr_add_settings_page' );

/**
 * Render the settings page.
 */
function pr_render_settings_page() {
    // Process form submission.
    if ( isset( $_POST['pr_save_settings'] ) ) {
        if (  !  isset( $_POST['pr_settings_nonce'] ) || !  wp_verify_nonce( $_POST['pr_settings_nonce'], 'pr_save_settings' ) ) {
            echo '<div class="notice notice-error"><p>' . __( 'Nonce verification failed.', 'petshop-product-redirect' ) . '</p></div>';
        } else {
            $api_url              = isset( $_POST['pr_api_url'] ) ? esc_url_raw( $_POST['pr_api_url'] ) : '';
            $transient_time_hours = isset( $_POST['pr_transient_time_hours'] ) ? absint( $_POST['pr_transient_time_hours'] ) : 1;
            update_option( 'pr_api_url', $api_url );
            update_option( 'pr_transient_time_hours', $transient_time_hours );

            // Save global redirection options.
            $redirect_single_enabled  = isset( $_POST['pr_redirect_single_enabled'] ) ? '1' : '0';
            $redirect_listing_enabled = isset( $_POST['pr_redirect_listing_enabled'] ) ? '1' : '0';
            update_option( 'pr_redirect_single_enabled', $redirect_single_enabled );
            update_option( 'pr_redirect_listing_enabled', $redirect_listing_enabled );

            if ( isset( $_POST['pr_flush_cache'] ) ) {
                delete_transient( 'pr_api_data' );
                echo '<div class="notice notice-success"><p>' . __( 'Transient cache flushed successfully.', 'petshop-product-redirect' ) . '</p></div>';
            }
            echo '<div class="notice notice-success"><p>' . __( 'Settings saved successfully.', 'petshop-product-redirect' ) . '</p></div>';
        }
    }
    $api_url                  = get_option( 'pr_api_url', 'https://big-games.shop/wp-json/wc-products/v1/list' );
    $transient_time_hours     = get_option( 'pr_transient_time_hours', 1 );
    $redirect_single_enabled  = get_option( 'pr_redirect_single_enabled', '1' );
    $redirect_listing_enabled = get_option( 'pr_redirect_listing_enabled', '1' );
    ?>
    <div class="wrap">
        <h1><?php _e( 'Product Redirect Settings', 'petshop-product-redirect' ); ?></h1>
        <div class="notice notice-info">
            <p><?php _e( 'Instructions: This plugin allows you to set up product redirects by selecting a URL from an external API. Use the settings below to configure the API URL, cache time and global options to enable redirection on single product pages and the display of the redirect option on product listings. Ensure that the required API endpoint is active, as redirects will be disabled if it is not available.', 'petshop-product-redirect' ); ?></p>
        </div>
        <form method="post" action="">
            <?php wp_nonce_field( 'pr_save_settings', 'pr_settings_nonce' ); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="pr_api_url"><?php _e( 'API URL', 'petshop-product-redirect' ); ?></label></th>
                    <td><input type="text" id="pr_api_url" name="pr_api_url" value="<?php echo esc_attr( $api_url ); ?>" style="width: 100%;" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="pr_transient_time_hours"><?php _e( 'Transient Time (Hours)', 'petshop-product-redirect' ); ?></label></th>
                    <td><input type="number" id="pr_transient_time_hours" name="pr_transient_time_hours" value="<?php echo esc_attr( $transient_time_hours ); ?>" min="1" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="pr_redirect_single_enabled"><?php _e( 'Enable redirect on single product page', 'petshop-product-redirect' ); ?></label></th>
                    <td>
                        <input type="checkbox" id="pr_redirect_single_enabled" name="pr_redirect_single_enabled" value="1" <?php checked( $redirect_single_enabled, '1' ); ?>/>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="pr_redirect_listing_enabled"><?php _e( 'Display redirect option in product listing', 'petshop-product-redirect' ); ?></label></th>
                    <td>
                        <input type="checkbox" id="pr_redirect_listing_enabled" name="pr_redirect_listing_enabled" value="1" <?php checked( $redirect_listing_enabled, '1' ); ?>/>
                    </td>
                </tr>
            </table>
            <?php submit_button( __( 'Save Settings', 'petshop-product-redirect' ), 'primary', 'pr_save_settings' ); ?>
            <p>
                <input type="submit" name="pr_flush_cache" value="<?php esc_attr_e( 'Flush Cache', 'petshop-product-redirect' ); ?>" class="button-secondary" />
            </p>
        </form>
    </div>
    <?php
}

/* ==========================================================================
Git‑Based Plugin Update System
========================================================================== */
/*
To enable automatic updates via a Git repository,
you can use the Plugin Update Checker library.
Download the library from:
https://github.com/YahnisElsts/plugin-update-checker
Then, include it in your plugin (e.g. in a subfolder called "plugin-update-checker")
and add the following code. Be sure to update the repository URL
to point to your plugin's Git repository.
 */
if ( class_exists( 'Puc_v4_Factory' ) ) {
    $myUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
        'https://github.com/dompl/petshop-product-redirect', // Replace with your Git repository URL.
        __FILE__,
        'petshop-product-redirect'
    );
}

/* ==========================================================================
Commit Note:
Updated plugin to remove the meta box and listing column when the corresponding global options are unchecked.
========================================================================== */
