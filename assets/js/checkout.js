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