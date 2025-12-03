<?php

/**
 * Plugin Name: Woo ShortCart
 * Description: Generate short URLs for WooCommerce products that add the product to cart and redirect to checkout.
 * Author: Mahim Zaman
 * Author URI: https://www.mahimzaman.com
 * Version: 1.0
 */

if (! defined('ABSPATH')) exit;

define('WSC_PLUGIN_DIR', plugin_dir_path(__FILE__));

include_once WSC_PLUGIN_DIR . 'includes/admin-page.php';

// Create custom post type
function wsc_register_short_url_cpt()
{
    register_post_type('wsc_short_url', [
        'public' => false,
        'show_ui' => true,
        'label' => 'Short URLs',
        'supports' => false, // No title/body editor
        'capability_type' => 'post',
        'menu_icon' => 'dashicons-randomize',
        'show_in_menu' => true,
        'map_meta_cap' => true,
        'show_in_admin_bar' => false,
    ]);
}
add_action('init', 'wsc_register_short_url_cpt');

// Rewrite rule
function wsc_add_rewrite_rule()
{
    add_rewrite_rule('^buy/([^/]+)/?', 'index.php?wsc_short_code=$matches[1]', 'top');
}
add_action('init', 'wsc_add_rewrite_rule');

// Query var
function wsc_add_query_vars($vars)
{
    $vars[] = 'wsc_short_code';
    return $vars;
}
add_filter('query_vars', 'wsc_add_query_vars');

// Handle redirection
function wsc_handle_redirect()
{
    $code = get_query_var('wsc_short_code');
    if (! $code) return;

    $posts = get_posts([
        'post_type' => 'wsc_short_url',
        'title' => $code,
        'posts_per_page' => 1
    ]);

    if ($posts) {
        $product_id = get_post_meta($posts[0]->ID, '_wsc_product_id', true);
        if ($product_id) {
            WC()->cart->empty_cart();
            $product = wc_get_product($product_id);
            $variation_data = [];

            if ($product->is_type('variation')) {
                $parent_id = $product->get_parent_id();
                $variation_data = $product->get_attributes();
                WC()->cart->add_to_cart($parent_id, 1, $product_id, $variation_data);
            } else {
                WC()->cart->add_to_cart($product_id);
            }
            wp_redirect(wc_get_checkout_url());
            exit;
        }
    }

    wp_redirect(home_url());
    exit;
}
add_action('template_redirect', 'wsc_handle_redirect');

add_filter('post_row_actions', function ($actions, $post) {
    if ($post->post_type === 'wsc_short_url') {
        unset($actions['edit']);
        unset($actions['inline hide-if-no-js']);
        unset($actions['trash']);
    }
    return $actions;
}, 10, 2);

add_action('admin_head', function () {
    global $post_type;
    if ($post_type === 'wsc_short_url') {
        echo '<style>.page-title-action { display: none !important; }</style>';
    }
});

add_filter('manage_edit-wsc_short_url_columns', function ($columns) {
    return [
        'cb'        => '<input type="checkbox" />',
        'product'   => 'Product',
        'short_url' => 'Short URL',
    ];
});

add_action('manage_wsc_short_url_posts_custom_column', function ($column, $post_id) {
    $product_id = get_post_meta($post_id, '_wsc_product_id', true);
    $product = wc_get_product($product_id);
    $code = get_the_title($post_id);
    $url = esc_url(home_url('/buy/' . $code));

    if ($column === 'product' && $product) {
        echo wp_kses_post($product->get_image([50, 50]));
        echo '<p><strong>' . esc_html($product->get_name()) . '</strong></p>';
    }
    if ($column === 'short_url') {
?>
        <input type="text" value="<?php echo esc_attr($url); ?>" readonly style="width: 70%;" />
        <button class="button copy-url" data-url="<?php echo esc_attr($url); ?>">Copy</button>
    <?php
    }
}, 10, 2);

add_action('admin_init', function () {
    global $pagenow;
    if ($pagenow === 'post-new.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'wsc_short_url') {
        wp_redirect(admin_url('edit.php?post_type=wsc_short_url'));
        exit;
    }
});


add_action('admin_enqueue_scripts', function () {
    wp_enqueue_style('admin-fa', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css');
    wp_enqueue_style('admin-css', plugin_dir_url(__FILE__) . 'assets/admin.css');
    wp_enqueue_script('admin-js', plugin_dir_url(__FILE__) . 'assets/admin.js', array(), time(), 'all');
});
