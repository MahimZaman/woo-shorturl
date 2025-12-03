<?php

function wsc_admin_menu()
{
    add_submenu_page(
        'edit.php?post_type=wsc_short_url',
        'Create Short URL',
        'Create Short URL',
        'manage_woocommerce',
        'wsc-create-short-url',
        'wsc_render_admin_page'
    );
}
add_action('admin_menu', 'wsc_admin_menu');

function wsc_render_admin_page()
{
    if (isset($_POST['wsc_product']) && check_admin_referer('wsc_create_nonce')) {
        $product_id = intval($_POST['wsc_product']);
        $variation_id = isset($_POST['wsc_variation']) ? intval($_POST['wsc_variation']) : 0;
        $final_id = $variation_id ?: $product_id;
        $code = sanitize_title(wp_generate_password(6, false));

        $post_id = wp_insert_post([
            'post_title'  => $code,
            'post_type'   => 'wsc_short_url',
            'post_status' => 'publish',
        ]);

        if ($post_id) {
            update_post_meta($post_id, '_wsc_product_id', $final_id);
            echo '<div class="notice notice-success"><p>Short URL created: <strong>' . home_url('/buy/' . $code) . '</strong></p></div>';
        }
    }

    $products = wc_get_products(['limit' => -1]);
?>
    <div class="wrap">
        <h1>Create Short URL for Product</h1>
        <form method="POST" id="wsc-url-generator">
            <?php wp_nonce_field('wsc_create_nonce'); ?>

            <label><strong>Select Product:</strong></label><br>
            <select name="wsc_product" id="wsc_product_select" required>
                <option value="">Select a product</option>
                <?php foreach (wc_get_products(['limit' => -1]) as $product): ?>
                    <option value="<?php echo esc_attr($product->get_id()); ?>" data-type="<?php echo esc_attr($product->get_type()); ?>">
                        <?php echo esc_html($product->get_name()); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <br><br>
            <div id="wsc_variation_wrapper" style="display:none;">
                <label><strong>Select Variation:</strong></label><br>
                <select name="wsc_variation" id="wsc_variation_select"></select>
            </div>
            <button class="wsc_button">Create Short URL</button>
        </form>
    </div>
<?php
}


add_action('wp_ajax_wsc_get_variations', function () {
    $product_id = intval($_POST['product_id']);
    $product = wc_get_product($product_id);

    if (! $product || $product->get_type() !== 'variable') {
        wp_send_json_error();
    }

    $variations = $product->get_available_variations();
    $options = [];

    foreach ($variations as $variation) {
        $label_parts = [];

        foreach ($variation['attributes'] as $attr_key => $attr_val) {
            // Remove "attribute_" prefix
            $clean_key = str_replace('attribute_', '', $attr_key);

            // Get readable label (e.g., "Tire Size")
            $label = wc_attribute_label($clean_key);

            // Get readable value (e.g., "Tire A")
            $value = wc_get_product_terms($variation['variation_id'], $clean_key, ['fields' => 'names']);
            $value = ! empty($value) ? $value[0] : $attr_val;

            $label_parts[] = $label . ': ' . ucwords(str_replace('-', ' ', $value));
        }

        $options[] = [
            'id'    => $variation['variation_id'],
            'label' => implode(' / ', $label_parts),
        ];
    }

    wp_send_json_success($options);
});
