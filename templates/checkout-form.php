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