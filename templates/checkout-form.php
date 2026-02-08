<?php
/**
 * Template: Checkout form
 * Variables available: $atts, $instance_id, $charge_payload, $charge_hash
 */
if (!defined('ABSPATH')) exit;
?>
<div class="rpc-checkout-container"
     id="<?php echo esc_attr($instance_id); ?>"
     data-instance-id="<?php echo esc_attr($instance_id); ?>"
     data-product-id="<?php echo esc_attr($atts['product_id']); ?>"
     data-delivery-dhaka="<?php echo esc_attr($atts['delivery_dhaka']); ?>"
     data-delivery-outside="<?php echo esc_attr($atts['delivery_outside']); ?>"
     data-charge-payload="<?php echo esc_attr($charge_payload); ?>"
     data-charge-hash="<?php echo esc_attr($charge_hash); ?>"
     data-enable-quantity="<?php echo esc_attr($atts['_enable_quantity'] ?? 1); ?>"
     data-whatsapp-number="<?php echo esc_attr($atts['_whatsapp_number'] ?? ''); ?>"
>
    <h2 class="rpc-checkout-title"><?php echo esc_html($atts['title']); ?></h2>

    <div class="rpc-price-box">
        <div class="rpc-summary-row">
            <span class="rpc-price-label">প্রোডাক্ট মূল্য</span>
            <span class="rpc-price-value">৳ 0</span>
        </div>

        <div class="rpc-summary-row rpc-qty-row" style="display:none;">
            <span class="rpc-price-label">পরিমাণ</span>
            <span class="rpc-qty-label">1</span>
        </div>

        <div class="rpc-divider">
            <div class="rpc-summary-row">
                <span class="rpc-price-label">ডেলিভারি চার্জ</span>
                <span class="rpc-price-label rpc-delivery-charge">৳ <?php echo esc_html($atts['delivery_dhaka']); ?></span>
            </div>
        </div>

        <div class="rpc-total">
            <span class="rpc-price-label">মোট পরিশোধ</span>
            <span class="rpc-total-price">৳ 0</span>
        </div>
    </div>

    <div class="rpc-qty-wrapper" style="display:none;">
        <label class="rpc-form-label">পরিমাণ</label>
        <div class="rpc-qty-control">
            <button type="button" class="rpc-qty-btn rpc-qty-minus" aria-label="Decrease quantity">−</button>
            <input type="number" class="rpc-form-input rpc-qty-input" min="1" max="20" value="1" inputmode="numeric">
            <button type="button" class="rpc-qty-btn rpc-qty-plus" aria-label="Increase quantity">+</button>
        </div>
    </div>

    <div class="rpc-attributes-wrapper">
        <div class="rpc-attributes-loading">লোড হচ্ছে...</div>
    </div>

    <div class="rpc-variation-info"></div>

    <div class="rpc-delivery-options">
        <div class="rpc-delivery-option selected" data-value="dhaka">
            <input type="radio" class="rpc-radio" name="rpc_delivery_<?php echo esc_attr($instance_id); ?>" value="dhaka" checked>
            <label style="flex:1;cursor:pointer;">
                <strong>ঢাকার ভিতরে</strong>
                <div class="rpc-muted">ডেলিভারি চার্জ: ৳ <?php echo esc_html($atts['delivery_dhaka']); ?></div>
            </label>
        </div>

        <div class="rpc-delivery-option" data-value="outside">
            <input type="radio" class="rpc-radio" name="rpc_delivery_<?php echo esc_attr($instance_id); ?>" value="outside">
            <label style="flex:1;cursor:pointer;">
                <strong>ঢাকার বাইরে</strong>
                <div class="rpc-muted">ডেলিভারি চার্জ: ৳ <?php echo esc_html($atts['delivery_outside']); ?></div>
            </label>
        </div>
    </div>

    <form class="rpc-order-form">
        <div class="rpc-form-group">
            <label class="rpc-form-label">পূর্ণ নাম *</label>
            <input type="text" class="rpc-form-input rpc-first-name" name="first_name" placeholder="আপনার পূর্ণ নাম লিখুন" required>
        </div>

        <div class="rpc-form-group">
            <label class="rpc-form-label">মোবাইল নাম্বার *</label>
            <input type="tel" class="rpc-form-input rpc-phone" name="phone" placeholder="০১XXXXXXXXX" required>
        </div>

        <div class="rpc-form-group">
            <label class="rpc-form-label">ইমেইল (ঐচ্ছিক)</label>
            <input type="email" class="rpc-form-input rpc-email" name="email" placeholder="আপনার ইমেইল">
        </div>

        <div class="rpc-form-group">
            <label class="rpc-form-label">সম্পূর্ণ ঠিকানা *</label>
            <textarea class="rpc-form-input rpc-address" name="address" rows="3" placeholder="বাড়ি নং, রাস্তা, এলাকা, জেলা" required></textarea>
        </div>

        <button type="submit" class="rpc-submit-btn">
            অর্ডার সম্পন্ন করুন
        </button>
    </form>

    <div class="rpc-message" style="display:none;"></div>

    <div class="rpc-success-actions" style="display:none;"></div>

    <div class="rpc-footer-note">
        <p>💵 Cash on Delivery — পণ্য হাতে পেয়ে টাকা পরিশোধ করুন</p>
        <p>⚡ ২৪ ঘণ্টার মধ্যে কনফার্মেশন কল পাবেন</p>
    </div>
</div>
