<?php
/**
 * Plugin Name: Rongin Pran Variable Product Checkout
 * Plugin URI: https://ronginpran.com
 * Description: Simple variable product checkout system for Elementor
 * Version: 1.0.2
 * Author: Rongin Pran
 * License: GPL v2 or later
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('RPC_VERSION', '1.0.2');
define('RPC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RPC_PLUGIN_URL', plugin_dir_url(__FILE__));


// Check if WooCommerce is active
add_action('admin_init', function() {
    if (!is_plugin_active('woocommerce/woocommerce.php')) {
        deactivate_plugins(plugin_basename(__FILE__));
        add_action('admin_notices', function() {
            echo '<div class="error"><p>Rongin Pran Checkout requires WooCommerce to be installed and activated.</p></div>';
        });
    }
});


// Main plugin class
class RonginPranCheckout {
    
    public function __construct() {
        // Initialize plugin
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // Register shortcode
        add_shortcode('ronginpran_checkout', array($this, 'checkout_shortcode'));
        
        // AJAX handlers
        add_action('wp_ajax_rpc_get_product', array($this, 'ajax_get_product'));
        add_action('wp_ajax_nopriv_rpc_get_product', array($this, 'ajax_get_product'));
        
        add_action('wp_ajax_rpc_create_order', array($this, 'ajax_create_order'));
        add_action('wp_ajax_nopriv_rpc_create_order', array($this, 'ajax_create_order'));
    }
    
    // Initialize plugin
    public function init() {
        // Nothing needed for now
    }
    
    // Enqueue scripts and styles
    public function enqueue_scripts() {
        // Only enqueue on pages with shortcode
        global $post;
        if (!is_admin()) {
            // CSS
            wp_enqueue_style(
                'rpc-checkout-style',
                RPC_PLUGIN_URL . 'assets/css/checkout.css',
                array(),
                RPC_VERSION
            );
            
            // JavaScript
            wp_enqueue_script(
                'rpc-checkout-script',
                RPC_PLUGIN_URL . 'assets/js/checkout.js',
                array('jquery'),
                RPC_VERSION,
                true
            );
            
            // Localize script for AJAX
            wp_localize_script('rpc-checkout-script', 'rpc_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('rpc_secure_nonce'),
                'debug' => current_user_can('administrator')
            ));
        }
    }
    
    // Shortcode for checkout form
    public function checkout_shortcode($atts) {
        // Extract shortcode attributes
        $atts = shortcode_atts(array(
            'product_id' => 0,
            'title' => 'অর্ডার করুন',
            'delivery_dhaka' => '70',
            'delivery_outside' => '130'
        ), $atts, 'ronginpran_checkout');
        
        // Start output buffering
        ob_start();
        
        // Include the template
        if (file_exists(RPC_PLUGIN_DIR . 'templates/checkout-form.php')) {
            include RPC_PLUGIN_DIR . 'templates/checkout-form.php';
        } else {
            echo '<div class="error">Checkout template not found</div>';
        }
        
        // Return the output
        return ob_get_clean();
    }
    
    // AJAX: Get product data
    public function ajax_get_product() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'rpc_secure_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        
        if ($product_id < 1) {
            wp_send_json_error('Invalid product ID');
        }
        
        // Get product
        $product = wc_get_product($product_id);
        
        if (!$product) {
            wp_send_json_error('Product not found');
        }
        
        // Prepare response
        $response = array(
            'success' => true,
            'product' => array(
                'id' => $product->get_id(),
                'name' => $product->get_name(),
                'price' => $product->get_price(),
                'regular_price' => $product->get_regular_price(),
                'type' => $product->get_type()
            )
        );
        
        // Get images
        $image_ids = $product->get_gallery_image_ids();
        if ($product->get_image_id()) {
            array_unshift($image_ids, $product->get_image_id());
        }
        
        $response['images'] = array();
        foreach ($image_ids as $image_id) {
            $response['images'][] = wp_get_attachment_url($image_id);
        }
        
        // If variable product, get variations and attributes
        if ($product->is_type('variable')) {
            $variations = $product->get_available_variations();
            $response['variations'] = array();
            $response['attributes'] = array();
            
            foreach ($variations as $variation) {
                $variation_product = wc_get_product($variation['variation_id']);
                if ($variation_product && $variation_product->is_in_stock()) {
                    $variation_data = array(
                        'id' => $variation['variation_id'],
                        'price' => $variation_product->get_price()
                    );
                    
                    // Get attributes
                    if (!empty($variation['attributes'])) {
                        $attributes = array();
                        foreach ($variation['attributes'] as $key => $value) {
                            $attribute_name = str_replace('attribute_', '', $key);
                            $attributes[] = array(
                                'name' => $attribute_name,
                                'option' => $value
                            );
                        }
                        $variation_data['attributes'] = $attributes;
                    }
                    
                    $response['variations'][] = $variation_data;
                }
            }
            
            // Get attribute options
            $attributes = $product->get_attributes();
            foreach ($attributes as $attribute) {
                if ($attribute->get_variation()) {
                    $response['attributes'][] = array(
                        'name' => wc_attribute_label($attribute->get_name()),
                        'slug' => $attribute->get_name(),
                        'options' => $attribute->get_options()
                    );
                }
            }
        }
        
        wp_send_json($response);
    }
    
    // AJAX: Create order
    public function ajax_create_order() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'rpc_secure_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        // Get and sanitize data
        $data = array(
            'first_name' => sanitize_text_field($_POST['first_name'] ?? ''),
            'phone' => sanitize_text_field($_POST['phone'] ?? ''),
            'address' => sanitize_text_field($_POST['address'] ?? ''),
            'email' => sanitize_email($_POST['email'] ?? 'customer@ronginpran.com'),
            'product_id' => intval($_POST['product_id'] ?? 0),
            'variation_id' => intval($_POST['variation_id'] ?? 0),
            'delivery_charge' => floatval($_POST['delivery_charge'] ?? 0)
        );
        
        // Validation
        $errors = array();
        if (empty($data['first_name'])) $errors[] = 'Name is required';
        if (empty($data['phone'])) $errors[] = 'Phone is required';
        if (empty($data['address'])) $errors[] = 'Address is required';
        if (!preg_match('/^01[3-9]\d{8}$/', $data['phone'])) $errors[] = 'Invalid phone number';
        
        if (!empty($errors)) {
            wp_send_json_error(implode(', ', $errors));
        }
        
        try {
            // Create order
            $order = wc_create_order();
            
            // Add product
            $product = wc_get_product($data['product_id']);
            if ($product) {
                $args = array();
                if ($data['variation_id'] > 0) {
                    $variation = wc_get_product($data['variation_id']);
                    if ($variation) {
                        $args['variation'] = $variation->get_attributes();
                    }
                }
                if ($data['variation_id']) {
                    $order->add_product(wc_get_product($data['variation_id']), 1);
                } else {
                    $order->add_product($product, 1);
                }
            }
            
            // Set addresses
            $order->set_billing_first_name($data['first_name']);
            $order->set_billing_phone($data['phone']);
            $order->set_billing_address_1($data['address']);
            $order->set_billing_country('BD');
            $order->set_billing_email($data['email']);
            
            $order->set_shipping_first_name($data['first_name']);
            $order->set_shipping_address_1($data['address']);
            $order->set_shipping_country('BD');
            
            // Set payment method
            $order->set_payment_method('cod');
            $order->set_payment_method_title('Cash on Delivery');
            
            // Add delivery charge
            if ($data['delivery_charge'] > 0) {
                $fee = new WC_Order_Item_Fee();
                $fee->set_name('ডেলিভারি চার্জ');
                $fee->set_amount($data['delivery_charge']);
                $fee->set_total($data['delivery_charge']);
                $order->add_item($fee);
            }
            
            // Calculate totals
            $order->calculate_totals();
            $order->set_status('pending');
            $order_id = $order->save();
            
            // Send success response
            wp_send_json_success(array(
                'message' => 'Order created successfully!',
                'order_id' => $order_id,
                'order_number' => $order->get_order_number()
            ));
            
        } catch (Exception $e) {
            wp_send_json_error('Error: ' . $e->getMessage());
        }
    }
}


// Initialize plugin
$GLOBALS['rongin_pran_checkout'] = new RonginPranCheckout();

// Elementor Widget
add_action('elementor/widgets/register', function($widgets_manager) {
    if (class_exists('Elementor\Widget_Base')) {
        require_once(RPC_PLUGIN_DIR . 'widgets/checkout-widget.php');
        $widgets_manager->register(new \Elementor_RonginPran_Checkout_Widget());
    }
});



