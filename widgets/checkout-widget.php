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