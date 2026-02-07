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

(function($) {
    'use strict';
    
    // Configuration
    const CONFIG = {
        product_id: 0,
        delivery: {
            dhaka: 70,
            outside: 130
        }
    };
    
    // State
    const state = {
        product: null,
        variations: [],
        selectedVariation: null,
        selectedDelivery: 'dhaka',
        isLoading: false
    };
    
    // Initialize
    function init() {
        console.log('RPC Checkout Initializing...');
        
        // Get product ID
        const container = $('.rpc-checkout-container');
        if (container.length) {
            CONFIG.product_id = container.data('product-id') || 0;
            console.log('Product ID:', CONFIG.product_id);
            
            // Load product data
            if (CONFIG.product_id > 0) {
                loadProduct();
            } else {
                useFallbackData();
            }
            
            // Setup events
            setupEvents();
        }
    }
    
    // Load product data
    function loadProduct() {
        showLoading('পণ্য লোড হচ্ছে...');
    
        $.ajax({
            url: rpc_ajax.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'rpc_get_product',
                nonce: rpc_ajax.nonce,
                product_id: CONFIG.product_id
            },
            success: function(response) {
                if (response.success) {
                    state.product = response.product;
                    state.variations = response.variations || [];
                    updateProductInfo(response);
                } else {
                    useFallbackData();
                }
                hideLoading();
            },
            error: function() {
                useFallbackData();
                hideLoading();
            }
        });
    }

    
    // Update product info
    function updateProductInfo(data) {
        // Update price
        const price = data.product.price || data.product.regular_price || '1200';
        $('.rpc-price-value').text(formatPrice(price));
        
        // Update variations
        if (data.variations && data.variations.length > 0) {
            updateVariationSelects(data.variations);
        } else if (data.attributes && data.attributes.length > 0) {
            updateAttributeSelects(data.attributes);
        } else {
            useFallbackData();
        }
        
        // Update total
        updateTotal();
    }
    
    // Update variation selects
    function updateVariationSelects(variations) {
        const colors = new Set();
        const sizes = new Set();
        
        variations.forEach(variation => {
            if (variation.attributes) {
                variation.attributes.forEach(attr => {
                    const name = attr.name.toLowerCase();
                    if (name.includes('color') || name.includes('pa_color')) {
                        colors.add(attr.option);
                    }
                    if (name.includes('size') || name.includes('pa_size')) {
                        sizes.add(attr.option);
                    }
                });
            }
        });
        
        // Update selects
        updateSelect('#rpc-color-select', Array.from(colors), 'রং নির্বাচন করুন');
        updateSelect('#rpc-size-select', Array.from(sizes), 'সাইজ নির্বাচন করুন');
    }
    
    // Update attribute selects
    function updateAttributeSelects(attributes) {
        attributes.forEach(attr => {
            const name = attr.name.toLowerCase();
            if (name.includes('color') || attr.slug.includes('color')) {
                updateSelect('#rpc-color-select', attr.options || [], 'রং নির্বাচন করুন');
            }
            if (name.includes('size') || attr.slug.includes('size')) {
                updateSelect('#rpc-size-select', attr.options || [], 'সাইজ নির্বাচন করুন');
            }
        });
    }
    
    // Update select element
    function updateSelect(selector, options, placeholder) {
        const select = $(selector);
        select.empty();
        select.append(`<option value="">${placeholder}</option>`);
        
        options.forEach(option => {
            if (option) {
                select.append(`<option value="${option}">${option}</option>`);
            }
        });
    }
    
    // Use fallback data
    function useFallbackData() {
        const colors = ['লাল', 'নীল', 'কালো', 'সাদা', 'সবুজ'];
        const sizes = ['S', 'M', 'L', 'XL', 'XXL'];
        
        updateSelect('#rpc-color-select', colors, 'রং নির্বাচন করুন');
        updateSelect('#rpc-size-select', sizes, 'সাইজ নির্বাচন করুন');
        
        $('.rpc-price-value').text('৳ ১,২০০');
        updateTotal();
    }
    
    // Setup events
    function setupEvents() {
        // Color/size change
        $(document).on('change', '#rpc-color-select, #rpc-size-select', function() {
            findVariation();
        });
        
        // Delivery change
        $(document).on('change', 'input[name="rpc_delivery"]', function() {
            state.selectedDelivery = $(this).val();
            updateTotal();
            $('#rpc-delivery-charge').text('৳ ' + CONFIG.delivery[state.selectedDelivery]);
        });
        
        // Form submit
        $(document).on('submit', '#rpc-order-form', submitOrder);
        
        // Phone formatting
        $(document).on('input', '#rpc-phone', formatPhone);
        
        // Delivery option click
        $(document).on('click', '.rpc-delivery-option', function() {
            $('.rpc-delivery-option').removeClass('selected');
            $(this).addClass('selected');
            const value = $(this).data('value');
            $(`input[name="rpc_delivery"][value="${value}"]`).prop('checked', true).trigger('change');
        });
    }
    
    // Find matching variation
    function findVariation() {
        const color = $('#rpc-color-select').val();
        const size = $('#rpc-size-select').val();
        
        if (!color || !size) {
            state.selectedVariation = null;
            $('.rpc-variation-info').hide();
            updateTotal();
            return;
        }
        
        // Find variation
        const variation = state.variations.find(v => {
            if (!v.attributes) return false;
            
            let hasColor = false;
            let hasSize = false;
            
            v.attributes.forEach(attr => {
                const name = attr.name.toLowerCase();
                if ((name.includes('color') || name.includes('pa_color')) && attr.option === color) {
                    hasColor = true;
                }
                if ((name.includes('size') || name.includes('pa_size')) && attr.option === size) {
                    hasSize = true;
                }
            });
            
            return hasColor && hasSize;
        });
        
        if (variation) {
            state.selectedVariation = variation;
            $('.rpc-price-value').text(formatPrice(variation.price));
            $('.rpc-variation-info').show().html(`নির্বাচিত: ${color}, ${size}`);
        } else {
            state.selectedVariation = null;
            $('.rpc-variation-info').hide();
        }
        
        updateTotal();
    }
    
    // Update total price
    function updateTotal() {
        var total = 0;
        
        // Product price
        if (state.selectedVariation && state.selectedVariation.price) {
            total += parseFloat(state.selectedVariation.price);
        } else if (state.product && state.product.price) {
            total += parseFloat(state.product.price);
        } else {
            total += 1200;
        }
        
        // Delivery charge
        total += CONFIG.delivery[state.selectedDelivery] || 70;
        
        // Update display
        $('.rpc-total-price').text(formatPrice(total));
    }
    
    // Format price
    function formatPrice(amount) {
        const num = parseFloat(amount) || 0;
        return '৳ ' + num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }
    
    // Format phone
    function formatPhone() {
        let value = $(this).val().replace(/\D/g, '');
        
        if (value.startsWith('0')) value = value.substring(1);
        if (value.length > 0) value = '0' + value;
        if (value.length > 11) value = value.substring(0, 11);
        
        $(this).val(value);
    }
    
    // Submit order
    async function submitOrder(e) {
        e.preventDefault();
        
        if (state.isLoading) return;
        
        // Validate
        if (!validateForm()) return;
        
        // Prepare data
        const orderData = {
            first_name: $('#rpc-first-name').val().trim(),
            phone: $('#rpc-phone').val().trim(),
            address: $('#rpc-address').val().trim(),
            email: $('#rpc-email').val().trim() || 'customer@ronginpran.com',
            product_id: CONFIG.product_id,
            variation_id: state.selectedVariation ? state.selectedVariation.id : 0,
            delivery_charge: CONFIG.delivery[state.selectedDelivery]
        };
        
        console.log('Submitting order:', orderData);
        
        try {
            state.isLoading = true;
            showLoading('অর্ডার তৈরি হচ্ছে...');
            
            const response = await $.ajax({
                url: rpc_ajax.ajax_url,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: 'rpc_create_order',
                    nonce: rpc_ajax.nonce,
                    ...orderData
                }
            });
            
            console.log('Order response:', response);
            
            if (response.success) {
                showMessage(`🎉 অর্ডার সফল হয়েছে! অর্ডার নম্বর: ${response.data.order_number}`, 'success');
                resetForm();
            } else {
                throw new Error(response.data || 'Order failed');
            }
            
        } catch (error) {
            console.error('Order error:', error);
            showMessage(`অর্ডার জমা দিতে সমস্যা: ${error.message}`, 'error');
        } finally {
            state.isLoading = false;
            hideLoading();
        }
    }
    
    // Validate form
    function validateForm() {
        let valid = true;
        
        // Clear errors
        $('.rpc-form-input').removeClass('error');
        $('.rpc-error').remove();
        
        // Check fields
        const fields = [
            { id: '#rpc-first-name', msg: 'নাম আবশ্যক' },
            { id: '#rpc-phone', msg: 'মোবাইল নম্বর আবশ্যক' },
            { id: '#rpc-address', msg: 'ঠিকানা আবশ্যক' },
            { id: '#rpc-color-select', msg: 'রং নির্বাচন করুন' },
            { id: '#rpc-size-select', msg: 'সাইজ নির্বাচন করুন' }
        ];
        
        fields.forEach(field => {
            const value = $(field.id).val().trim();
            if (!value) {
                showError(field.id, field.msg);
                valid = false;
            }
        });
        
        // Validate phone
        const phone = $('#rpc-phone').val().trim();
        if (phone && !/^01[3-9]\d{8}$/.test(phone)) {
            showError('#rpc-phone', 'সঠিক মোবাইল নম্বর দিন');
            valid = false;
        }
        
        return valid;
    }
    
    // Show error
    function showError(selector, message) {
        $(selector).addClass('error');
        $(selector).after(`<div class="rpc-error" style="color:#dc2626;font-size:14px;margin-top:5px;">${message}</div>`);
    }
    
    // Show message
    function showMessage(message, type) {
        const div = $('.rpc-message');
        div.removeClass('success error').addClass(type).text(message).show();
        
        setTimeout(() => div.hide(), 10000);
    }
    
    // Show loading
    function showLoading(text) {
        $('.rpc-submit-btn').prop('disabled', true).html(`<span class="rpc-loading"></span> ${text}`);
    }
    
    // Hide loading
    function hideLoading() {
        $('.rpc-submit-btn').prop('disabled', false).text('অর্ডার সম্পন্ন করুন');
    }
    
    // Reset form
    function resetForm() {
        $('#rpc-order-form')[0].reset();
        $('#rpc-color-select, #rpc-size-select').val('');
        $('.rpc-variation-info').hide();
        state.selectedVariation = null;
        updateTotal();
    }
    
    // Start when ready
    $(document).ready(init);
    
})(jQuery);

<?php
/**
 * Checkout Form Template
 */
?>
<div class="rpc-checkout-container" data-product-id="<?php echo esc_attr($atts['product_id']); ?>">
    <h2 class="rpc-checkout-title"><?php echo esc_html($atts['title']); ?></h2>
    
    <!-- Price Display -->
    <div class="rpc-price-box">
        <span class="rpc-price-label">প্রোডাক্ট মূল্য</span>
        <span class="rpc-price-value rpc-price-value">৳ 0</span>
        
        <div style="margin: 20px 0; border-top: 1px dashed #ea580c; padding-top: 15px;">
            <span class="rpc-price-label">ডেলিভারি চার্জ</span>
            <span class="rpc-price-label" id="rpc-delivery-charge">৳ <?php echo esc_html($atts['delivery_dhaka']); ?></span>
        </div>
        
        <div style="border-top: 2px solid #ea580c; padding-top: 15px;">
            <span class="rpc-price-label">মোট পরিশোধ</span>
            <span class="rpc-total-price">৳ 0</span>
        </div>
    </div>
    
    <!-- Variation Selection -->
    <div class="rpc-select-wrapper">
        <div class="rpc-form-group">
            <label class="rpc-form-label" for="rpc-color-select">রং নির্বাচন করুন</label>
            <select class="rpc-form-input" id="rpc-color-select" name="color" required>
                <option value="">লোড হচ্ছে...</option>
            </select>
        </div>
        
        <div class="rpc-form-group">
            <label class="rpc-form-label" for="rpc-size-select">সাইজ নির্বাচন করুন</label>
            <select class="rpc-form-input" id="rpc-size-select" name="size" required>
                <option value="">লোড হচ্ছে...</option>
            </select>
        </div>
    </div>
    
    <div class="rpc-variation-info"></div>
    
    <!-- Delivery Options -->
    <div class="rpc-delivery-options">
        <div class="rpc-delivery-option selected" data-value="dhaka">
            <input type="radio" class="rpc-radio" name="rpc_delivery" value="dhaka" id="delivery-dhaka" checked>
            <label for="delivery-dhaka" style="flex: 1; cursor: pointer;">
                <strong>ঢাকার ভিতরে</strong>
                <div style="color: #666; font-size: 14px; margin-top: 2px;">ডেলিভারি চার্জ: ৳ <?php echo esc_html($atts['delivery_dhaka']); ?></div>
            </label>
        </div>
        
        <div class="rpc-delivery-option" data-value="outside">
            <input type="radio" class="rpc-radio" name="rpc_delivery" value="outside" id="delivery-outside">
            <label for="delivery-outside" style="flex: 1; cursor: pointer;">
                <strong>ঢাকার বাইরে</strong>
                <div style="color: #666; font-size: 14px; margin-top: 2px;">ডেলিভারি চার্জ: ৳ <?php echo esc_html($atts['delivery_outside']); ?></div>
            </label>
        </div>
    </div>
    
    <!-- Order Form -->
    <form id="rpc-order-form">
        <div class="rpc-form-group">
            <label class="rpc-form-label" for="rpc-first-name">পূর্ণ নাম *</label>
            <input type="text" class="rpc-form-input" id="rpc-first-name" name="first_name" placeholder="আপনার পূর্ণ নাম লিখুন" required>
        </div>
        
        <div class="rpc-form-group">
            <label class="rpc-form-label" for="rpc-phone">মোবাইল নাম্বার *</label>
            <input type="tel" class="rpc-form-input" id="rpc-phone" name="phone" placeholder="০১XXXXXXXXX" required>
        </div>
        
        <div class="rpc-form-group">
            <label class="rpc-form-label" for="rpc-email">ইমেইল (ঐচ্ছিক)</label>
            <input type="email" class="rpc-form-input" id="rpc-email" name="email" placeholder="আপনার ইমেইল">
        </div>
        
        <div class="rpc-form-group">
            <label class="rpc-form-label" for="rpc-address">সম্পূর্ণ ঠিকানা *</label>
            <textarea class="rpc-form-input" id="rpc-address" name="address" rows="3" placeholder="বাড়ি নং, রাস্তা, এলাকা, জেলা" required></textarea>
        </div>
        
        <button type="submit" class="rpc-submit-btn" id="rpc-submit-btn">
            অর্ডার সম্পন্ন করুন
        </button>
    </form>
    
    
    <!-- Message Display -->
    <div class="rpc-message" style="display: none;"></div>
    
    <div style="text-align: center; margin-top: 20px; color: #666; font-size: 14px;">
        <p>💵 Cash on Delivery — পণ্য হাতে পেয়ে টাকা পরিশোধ করুন</p>
        <p>⚡ ২৪ ঘণ্টার মধ্যে কনফার্মেশন কল পাবেন</p>
    </div>
</div>

<?php
/**
 * Elementor Checkout Widget
 */

class Elementor_RonginPran_Checkout_Widget extends \Elementor\Widget_Base {
    
    public function get_name() {
        return 'ronginpran_checkout';
    }
    
    public function get_title() {
        return __('Rongin Pran Checkout', 'ronginpran-checkout');
    }
    
    public function get_icon() {
        return 'eicon-cart';
    }
    
    public function get_categories() {
        return ['general'];
    }
    
    protected function register_controls() {
        // Content Section
        $this->start_controls_section(
            'content_section',
            [
                'label' => __('Settings', 'ronginpran-checkout'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );
        
        $this->add_control(
            'product_id',
            [
                'label' => __('Product ID', 'ronginpran-checkout'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => 110,
                'description' => __('Enter your WooCommerce product ID', 'ronginpran-checkout'),
            ]
        );
        
        $this->add_control(
            'title',
            [
                'label' => __('Title', 'ronginpran-checkout'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('অর্ডার করুন', 'ronginpran-checkout'),
                'placeholder' => __('Enter title', 'ronginpran-checkout'),
            ]
        );
        
        $this->add_control(
            'delivery_dhaka',
            [
                'label' => __('Dhaka Delivery Charge', 'ronginpran-checkout'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => 70,
                'description' => __('Delivery charge inside Dhaka', 'ronginpran-checkout'),
            ]
        );
        
        $this->add_control(
            'delivery_outside',
            [
                'label' => __('Outside Dhaka Delivery Charge', 'ronginpran-checkout'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => 130,
                'description' => __('Delivery charge outside Dhaka', 'ronginpran-checkout'),
            ]
        );
        
        $this->end_controls_section();
        
        // Style Section
        $this->start_controls_section(
            'style_section',
            [
                'label' => __('Style', 'ronginpran-checkout'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );
        
        $this->add_control(
            'background_color',
            [
                'label' => __('Background Color', 'ronginpran-checkout'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .rpc-checkout-container' => 'background-color: {{VALUE}}',
                ],
            ]
        );
        
        $this->add_control(
            'title_color',
            [
                'label' => __('Title Color', 'ronginpran-checkout'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .rpc-checkout-title' => 'color: {{VALUE}}',
                ],
            ]
        );
        
        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'title_typography',
                'selector' => '{{WRAPPER}} .rpc-checkout-title',
            ]
        );
        
        $this->end_controls_section();
    }
    
    protected function render() {
        $settings = $this->get_settings_for_display();
        
        // Generate shortcode
        $shortcode = sprintf(
            '[ronginpran_checkout product_id="%s" title="%s" delivery_dhaka="%s" delivery_outside="%s"]',
            esc_attr($settings['product_id']),
            esc_attr($settings['title']),
            esc_attr($settings['delivery_dhaka']),
            esc_attr($settings['delivery_outside'])
        );
        
        echo do_shortcode($shortcode);
    }
}