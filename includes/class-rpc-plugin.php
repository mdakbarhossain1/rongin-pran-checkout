<?php
if (!defined('ABSPATH')) exit;

class RPC_Plugin {
    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function activate() {
        // Block activation if WooCommerce not active
        if (!class_exists('WooCommerce')) {
            deactivate_plugins(plugin_basename(RPC_PLUGIN_FILE));
            wp_die(
                esc_html__('Rongin Pran Checkout requires WooCommerce to be installed and activated.', 'ronginpran-checkout'),
                esc_html__('Plugin dependency missing', 'ronginpran-checkout'),
                ['back_link' => true]
            );
        }
    }

    public static function deactivate() {
        // Nothing for now
    }

    public function init() {
        $this->load_textdomain();

        // Runtime check (if WooCommerce gets deactivated later)
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', [$this, 'admin_notice_woocommerce_missing']);
            return;
        }

        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);

        add_shortcode('ronginpran_checkout', [$this, 'shortcode_checkout']);

        add_action('wp_ajax_rpc_get_product', [$this, 'ajax_get_product']);
        add_action('wp_ajax_nopriv_rpc_get_product', [$this, 'ajax_get_product']);

        add_action('wp_ajax_rpc_create_order', [$this, 'ajax_create_order']);
        add_action('wp_ajax_nopriv_rpc_create_order', [$this, 'ajax_create_order']);

        // Elementor
        add_action('elementor/widgets/register', [$this, 'register_elementor_widget']);
    }

    public function load_textdomain() {
        load_plugin_textdomain(
            'ronginpran-checkout',
            false,
            dirname(plugin_basename(RPC_PLUGIN_FILE)) . '/languages'
        );
    }

    public function admin_notice_woocommerce_missing() {
        echo '<div class="notice notice-error"><p>' .
            esc_html__('Rongin Pran Checkout requires WooCommerce to be installed and activated.', 'ronginpran-checkout') .
            '</p></div>';
    }

    /**
     * Enqueue only on pages containing the shortcode
     */
    public function enqueue_assets() {
        if (is_admin() || !is_singular()) return;

        global $post;
        if (!$post instanceof WP_Post) return;

        if (!has_shortcode($post->post_content, 'ronginpran_checkout')) {
            return;
        }

        wp_enqueue_style(
            'rpc-checkout-style',
            RPC_PLUGIN_URL . 'assets/css/checkout.css',
            [],
            RPC_VERSION
        );

        wp_enqueue_script(
            'rpc-checkout-script',
            RPC_PLUGIN_URL . 'assets/js/checkout.js',
            ['jquery'],
            RPC_VERSION,
            true
        );

        wp_localize_script('rpc-checkout-script', 'rpc_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('rpc_secure_nonce'),
            'debug'    => current_user_can('manage_options'),
        ]);
    }

    public function shortcode_checkout($atts) {
        $atts = shortcode_atts([
            'product_id'        => 0,
            'title'             => 'অর্ডার করুন',
            'delivery_dhaka'    => '70',
            'delivery_outside'  => '130',
        ], $atts, 'ronginpran_checkout');

        $atts['product_id'] = absint($atts['product_id']);
        $atts['delivery_dhaka'] = floatval($atts['delivery_dhaka']);
        $atts['delivery_outside'] = floatval($atts['delivery_outside']);

        // Unique ID per instance (important for Elementor multi-widget pages)
        $instance_id = 'rpc_' . wp_generate_uuid4();

        ob_start();
        $template = RPC_PLUGIN_DIR . 'templates/checkout-form.php';
        if (file_exists($template)) {
            include $template;
        } else {
            echo '<div class="rpc-error-box">' . esc_html__('Checkout template not found.', 'ronginpran-checkout') . '</div>';
        }
        return ob_get_clean();
    }

    public function ajax_get_product() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'rpc_secure_nonce')) {
            wp_send_json_error(['message' => 'Security check failed'], 403);
        }

        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        if ($product_id < 1) {
            wp_send_json_error(['message' => 'Invalid product ID'], 400);
        }

        $product = wc_get_product($product_id);
        if (!$product) {
            wp_send_json_error(['message' => 'Product not found'], 404);
        }

        $data = [
            'product' => [
                'id'            => $product->get_id(),
                'name'          => $product->get_name(),
                'price'         => $product->get_price(),
                'regular_price' => $product->get_regular_price(),
                'type'          => $product->get_type(),
            ],
            'images' => [],
            'variations' => [],
            'attributes' => [],
        ];

        $image_ids = $product->get_gallery_image_ids();
        if ($product->get_image_id()) {
            array_unshift($image_ids, $product->get_image_id());
        }
        foreach ($image_ids as $image_id) {
            $url = wp_get_attachment_url($image_id);
            if ($url) $data['images'][] = esc_url_raw($url);
        }

        if ($product->is_type('variable')) {
            $variations = $product->get_available_variations();

            foreach ($variations as $variation) {
                $variation_product = wc_get_product($variation['variation_id']);
                if (!$variation_product) continue;

                // only in stock + purchasable
                if (!$variation_product->is_in_stock() || !$variation_product->is_purchasable()) continue;

                $variation_data = [
                    'id'    => $variation_product->get_id(),
                    'price' => $variation_product->get_price(),
                    'attributes' => [],
                ];

                if (!empty($variation['attributes'])) {
                    foreach ($variation['attributes'] as $key => $value) {
                        $attribute_name = str_replace('attribute_', '', $key);
                        $variation_data['attributes'][] = [
                            'name' => sanitize_key($attribute_name),
                            'option' => (string) $value,
                        ];
                    }
                }

                $data['variations'][] = $variation_data;
            }

            // attribute options (for fallback display)
            $attributes = $product->get_attributes();
            foreach ($attributes as $attribute) {
                if ($attribute->get_variation()) {
                    $data['attributes'][] = [
                        'label'   => wc_attribute_label($attribute->get_name()),
                        'slug'    => $attribute->get_name(),
                        'options' => $attribute->get_options(),
                    ];
                }
            }
        }

        wp_send_json_success($data);
    }

    public function ajax_create_order() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'rpc_secure_nonce')) {
            wp_send_json_error(['message' => 'Security check failed'], 403);
        }

        $first_name = sanitize_text_field($_POST['first_name'] ?? '');
        $phone      = sanitize_text_field($_POST['phone'] ?? '');
        $address    = sanitize_text_field($_POST['address'] ?? '');
        $email      = sanitize_email($_POST['email'] ?? '');
        $product_id = absint($_POST['product_id'] ?? 0);
        $variation_id = absint($_POST['variation_id'] ?? 0);

        // Delivery zone only (server computes charge!)
        $delivery_zone = sanitize_key($_POST['delivery_zone'] ?? 'dhaka'); // dhaka|outside
        if (!in_array($delivery_zone, ['dhaka', 'outside'], true)) {
            $delivery_zone = 'dhaka';
        }

        // delivery charges (you can make these settings later)
        $delivery_charge = ($delivery_zone === 'outside') ? 130 : 70;

        // Validation
        $errors = [];
        if ($first_name === '') $errors[] = 'Name is required';
        if ($phone === '') $errors[] = 'Phone is required';
        if ($address === '') $errors[] = 'Address is required';

        // BD phone validation (01[3-9]XXXXXXXX)
        if ($phone && !preg_match('/^01[3-9]\d{8}$/', $phone)) {
            $errors[] = 'Invalid phone number';
        }

        if ($product_id < 1) $errors[] = 'Invalid product';

        if (!empty($errors)) {
            wp_send_json_error(['message' => implode(', ', $errors)], 400);
        }

        // Default email if missing
        if (!$email) {
            $email = 'customer@ronginpran.com';
        }

        try {
            $product = wc_get_product($product_id);
            if (!$product) {
                wp_send_json_error(['message' => 'Product not found'], 404);
            }

            // If variation provided, validate it
            $line_item_product = $product;

            if ($variation_id > 0) {
                $variation = wc_get_product($variation_id);
                if (!$variation || !$variation->is_type('variation')) {
                    wp_send_json_error(['message' => 'Invalid variation'], 400);
                }

                // Ensure variation belongs to the selected product
                if ((int) $variation->get_parent_id() !== (int) $product_id) {
                    wp_send_json_error(['message' => 'Variation does not belong to this product'], 400);
                }

                if (!$variation->is_purchasable() || !$variation->is_in_stock()) {
                    wp_send_json_error(['message' => 'Selected variation is not available'], 400);
                }

                $line_item_product = $variation;
            }

            $order = wc_create_order();

            $item_id = $order->add_product($line_item_product, 1);

            // Addresses
            $order->set_billing_first_name($first_name);
            $order->set_billing_phone($phone);
            $order->set_billing_address_1($address);
            $order->set_billing_country('BD');
            $order->set_billing_email($email);

            $order->set_shipping_first_name($first_name);
            $order->set_shipping_address_1($address);
            $order->set_shipping_country('BD');

            // Payment method
            $order->set_payment_method('cod');
            $order->set_payment_method_title('Cash on Delivery');

            // Delivery fee (server computed)
            if ($delivery_charge > 0) {
                $fee = new WC_Order_Item_Fee();
                $fee->set_name('ডেলিভারি চার্জ');
                $fee->set_amount($delivery_charge);
                $fee->set_total($delivery_charge);
                $order->add_item($fee);
            }

            // Helpful meta
            $order->update_meta_data('_rpc_delivery_zone', $delivery_zone);
            $order->update_meta_data('_rpc_source', 'ronginpran_checkout');

            if ($item_id && $variation_id > 0) {
                $order_item = $order->get_item($item_id);
                if ($order_item) {
                    $order_item->add_meta_data('_rpc_variation_id', $variation_id, true);
                }
            }

            $order->calculate_totals();
            $order->set_status('pending');
            $order->save();

            wp_send_json_success([
                'message' => 'Order created successfully!',
                'order_id' => $order->get_id(),
                'order_number' => $order->get_order_number(),
            ]);
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function register_elementor_widget($widgets_manager) {
        if (!class_exists('Elementor\Widget_Base')) return;

        require_once RPC_PLUGIN_DIR . 'includes/class-rpc-elementor-widget.php';
        $widgets_manager->register(new \Elementor_RonginPran_Checkout_Widget());
    }
}
