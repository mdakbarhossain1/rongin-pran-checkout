<?php
/**
 * Template: Checkout form
 * Variables available: $atts, $instance_id
 */
if (!defined('ABSPATH')) exit;
?>
<div class="rpc-checkout-container"
     id="<?php echo esc_attr($instance_id); ?>"
     data-instance-id="<?php echo esc_attr($instance_id); ?>"
     data-product-id="<?php echo esc_attr($atts['product_id']); ?>"
     data-delivery-dhaka="<?php echo esc_attr($atts['delivery_dhaka']); ?>"
     data-delivery-outside="<?php echo esc_attr($atts['delivery_outside']); ?>"
>
    <h2 class="rpc-checkout-title"><?php echo esc_html($atts['title']); ?></h2>

    <div class="rpc-price-box">
        <span class="rpc-price-label">প্রোডাক্ট মূল্য</span>
        <span class="rpc-price-value">৳ 0</span>

        <div class="rpc-divider">
            <span class="rpc-price-label">ডেলিভারি চার্জ</span>
            <span class="rpc-price-label rpc-delivery-charge">৳ <?php echo esc_html($atts['delivery_dhaka']); ?></span>
        </div>

        <div class="rpc-total">
            <span class="rpc-price-label">মোট পরিশোধ</span>
            <span class="rpc-total-price">৳ 0</span>
        </div>
    </div>

    <div class="rpc-select-wrapper">
        <div class="rpc-form-group">
            <label class="rpc-form-label">রং নির্বাচন করুন</label>
            <select class="rpc-form-input rpc-color-select" name="color" required>
                <option value="">লোড হচ্ছে...</option>
            </select>
        </div>

        <div class="rpc-form-group">
            <label class="rpc-form-label">সাইজ নির্বাচন করুন</label>
            <select class="rpc-form-input rpc-size-select" name="size" required>
                <option value="">লোড হচ্ছে...</option>
            </select>
        </div>
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

    <div class="rpc-footer-note">
        <p>💵 Cash on Delivery — পণ্য হাতে পেয়ে টাকা পরিশোধ করুন</p>
        <p>⚡ ২৪ ঘণ্টার মধ্যে কনফার্মেশন কল পাবেন</p>
    </div>
</div>
