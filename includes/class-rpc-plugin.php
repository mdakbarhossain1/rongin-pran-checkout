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

        // Admin settings
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);

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
     * Get plugin settings (saved via Settings API)
     */
    public function get_settings(): array {
        $defaults = [
            'delivery_dhaka' => 70,
            'delivery_outside' => 130,
            'enable_quantity' => 1,
            'whatsapp_number' => '',
            'success_redirect' => 0,
        ];
        $opt = get_option('rpc_settings', []);
        if (!is_array($opt)) $opt = [];
        return array_merge($defaults, $opt);
    }

    /**
     * Admin: register submenu under WooCommerce
     */
    public function register_admin_menu() {
        if (!current_user_can('manage_woocommerce')) return;

        add_submenu_page(
            'woocommerce',
            __('RonginPran Checkout', 'ronginpran-checkout'),
            __('RonginPran Checkout', 'ronginpran-checkout'),
            'manage_woocommerce',
            'rpc-settings',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings() {
        register_setting('rpc_settings_group', 'rpc_settings', [$this, 'sanitize_settings']);

        add_settings_section(
            'rpc_main_section',
            __('Checkout Settings', 'ronginpran-checkout'),
            function () {
                echo '<p>' . esc_html__('Configure default delivery charges and UX options.', 'ronginpran-checkout') . '</p>';
            },
            'rpc-settings'
        );

        add_settings_field(
            'delivery_dhaka',
            __('Dhaka Delivery Charge', 'ronginpran-checkout'),
            [$this, 'field_number'],
            'rpc-settings',
            'rpc_main_section',
            ['key' => 'delivery_dhaka', 'min' => 0, 'step' => 1]
        );

        add_settings_field(
            'delivery_outside',
            __('Outside Dhaka Delivery Charge', 'ronginpran-checkout'),
            [$this, 'field_number'],
            'rpc-settings',
            'rpc_main_section',
            ['key' => 'delivery_outside', 'min' => 0, 'step' => 1]
        );

        add_settings_field(
            'enable_quantity',
            __('Enable Quantity Selector', 'ronginpran-checkout'),
            [$this, 'field_checkbox'],
            'rpc-settings',
            'rpc_main_section',
            ['key' => 'enable_quantity']
        );

        add_settings_field(
            'whatsapp_number',
            __('WhatsApp Number (optional)', 'ronginpran-checkout'),
            [$this, 'field_text'],
            'rpc-settings',
            'rpc_main_section',
            ['key' => 'whatsapp_number', 'placeholder' => '8801XXXXXXXXX']
        );

        add_settings_field(
            'success_redirect',
            __('Redirect to Woo Thank You Page after order', 'ronginpran-checkout'),
            [$this, 'field_checkbox'],
            'rpc-settings',
            'rpc_main_section',
            ['key' => 'success_redirect']
        );
    }

    public function sanitize_settings($input) {
        $out = [];
        $out['delivery_dhaka'] = isset($input['delivery_dhaka']) ? floatval($input['delivery_dhaka']) : 70;
        $out['delivery_outside'] = isset($input['delivery_outside']) ? floatval($input['delivery_outside']) : 130;
        $out['enable_quantity'] = !empty($input['enable_quantity']) ? 1 : 0;
        $out['whatsapp_number'] = sanitize_text_field($input['whatsapp_number'] ?? '');
        $out['success_redirect'] = !empty($input['success_redirect']) ? 1 : 0;
        return $out;
    }

    public function render_settings_page() {
        if (!current_user_can('manage_woocommerce')) return;
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('RonginPran Checkout Settings', 'ronginpran-checkout') . '</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields('rpc_settings_group');
        do_settings_sections('rpc-settings');
        submit_button();
        echo '</form>';
        echo '</div>';
    }

    public function field_number($args) {
        $key = $args['key'];
        $settings = $this->get_settings();
        $val = esc_attr($settings[$key] ?? '');
        $min = isset($args['min']) ? esc_attr($args['min']) : '0';
        $step = isset($args['step']) ? esc_attr($args['step']) : '1';
        echo '<input type="number" min="' . $min . '" step="' . $step . '" name="rpc_settings[' . esc_attr($key) . ']" value="' . $val . '" class="small-text" />';
    }

    public function field_text($args) {
        $key = $args['key'];
        $settings = $this->get_settings();
        $val = esc_attr($settings[$key] ?? '');
        $ph = esc_attr($args['placeholder'] ?? '');
        echo '<input type="text" name="rpc_settings[' . esc_attr($key) . ']" value="' . $val . '" placeholder="' . $ph . '" class="regular-text" />';
        if ($key === 'whatsapp_number') {
            echo '<p class="description">' . esc_html__('Example: 8801XXXXXXXXX (no + sign). Used for WhatsApp support button on success screen.', 'ronginpran-checkout') . '</p>';
        }
    }

    public function field_checkbox($args) {
        $key = $args['key'];
        $settings = $this->get_settings();
        $checked = !empty($settings[$key]) ? 'checked' : '';
        echo '<label><input type="checkbox" name="rpc_settings[' . esc_attr($key) . ']" value="1" ' . $checked . ' /> ' . esc_html__('Enabled', 'ronginpran-checkout') . '</label>';
    }


    /**
     * Enqueue only on pages containing the shortcode
     */
    public function enqueue_assets() {
        if (is_admin() || !is_singular()) return;

        $post_id = get_queried_object_id();
        if (!$post_id) return;

        $should_enqueue = false;

        // 1) If shortcode exists in content
        $post = get_post($post_id);
        if ($post instanceof WP_Post && has_shortcode($post->post_content, 'ronginpran_checkout')) {
            $should_enqueue = true;
        }

        // 2) If the page is built with Elementor
        if (!$should_enqueue && $this->is_built_with_elementor($post_id)) {
            $should_enqueue = true;
        }

        if (!$should_enqueue) return;

        // Enqueue CSS
        wp_enqueue_style(
            'rpc-checkout-style',
            RPC_PLUGIN_URL . 'assets/css/checkout.css',
            [],
            RPC_VERSION
        );

        // Enqueue JS
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
            'delivery_dhaka'    => '0',
            'delivery_outside'  => '0',
        ], $atts, 'ronginpran_checkout');

        $atts['product_id'] = absint($atts['product_id']);

        // Defaults from settings (can be overridden via shortcode/widget)
        $settings = $this->get_settings();
        $atts['delivery_dhaka'] = floatval($atts['delivery_dhaka']);
        $atts['delivery_outside'] = floatval($atts['delivery_outside']);

        if ($atts['delivery_dhaka'] <= 0) {
            $atts['delivery_dhaka'] = floatval($settings['delivery_dhaka'] ?? 70);
        }
        if ($atts['delivery_outside'] <= 0) {
            $atts['delivery_outside'] = floatval($settings['delivery_outside'] ?? 130);
        }

        $atts['_enable_quantity'] = !empty($settings['enable_quantity']) ? 1 : 0;
        $atts['_whatsapp_number'] = (string) ($settings['whatsapp_number'] ?? '');
        $atts['_success_redirect'] = !empty($settings['success_redirect']) ? 1 : 0;

        // Signed delivery payload to prevent tampering
        $charge_payload = $atts['product_id'] . ':' . $atts['delivery_dhaka'] . ':' . $atts['delivery_outside'];
        $charge_hash = wp_hash($charge_payload);

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

        // Delivery zone (server computes charge)
        $delivery_zone = sanitize_key($_POST['delivery_zone'] ?? 'dhaka'); // dhaka|outside
        if (!in_array($delivery_zone, ['dhaka', 'outside'], true)) {
            $delivery_zone = 'dhaka';
        }

        // Quantity
        $quantity = isset($_POST['quantity']) ? absint($_POST['quantity']) : 1;
        if ($quantity < 1) $quantity = 1;
        if ($quantity > 20) $quantity = 20;

        // Delivery charges: use signed payload if provided, otherwise settings defaults
        $settings = $this->get_settings();
        $dhaka_charge = floatval($settings['delivery_dhaka'] ?? 70);
        $outside_charge = floatval($settings['delivery_outside'] ?? 130);

        $charge_payload = sanitize_text_field($_POST['charge_payload'] ?? '');
        $charge_hash = sanitize_text_field($_POST['charge_hash'] ?? '');

        if ($charge_payload && $charge_hash && hash_equals(wp_hash($charge_payload), $charge_hash)) {
            $parts = explode(':', $charge_payload);
            if (count($parts) === 3) {
                $dhaka_charge = floatval($parts[1]);
                $outside_charge = floatval($parts[2]);
            }
        }

        $delivery_charge = ($delivery_zone === 'outside') ? $outside_charge : $dhaka_charge;


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

            $item_id = $order->add_product($line_item_product, $quantity);

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
            $order->update_meta_data('_rpc_quantity', $quantity);

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
                'success_redirect' => !empty($settings['success_redirect']) ? 1 : 0,
                'thankyou_url' => !empty($settings['success_redirect']) ? $order->get_checkout_order_received_url() : '',
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

    private function is_built_with_elementor($post_id): bool {
    // Elementor not installed/loaded
    if (!did_action('elementor/loaded')) return false;
    if (!class_exists('\Elementor\Plugin')) return false;

    try {
        $doc = \Elementor\Plugin::$instance->documents->get($post_id);
        return ($doc && $doc->is_built_with_elementor());
    } catch (\Throwable $e) {
        return false;
    }
}
}
