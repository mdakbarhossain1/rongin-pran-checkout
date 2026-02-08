<?php
if (!defined('ABSPATH')) exit;

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
                'default' => 0,
            ]
        );

        $this->add_control(
            'title',
            [
                'label' => __('Title', 'ronginpran-checkout'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('অর্ডার করুন', 'ronginpran-checkout'),
            ]
        );

        $this->add_control(
            'delivery_dhaka',
            [
                'label' => __('Dhaka Delivery Charge', 'ronginpran-checkout'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => 0,
            ]
        );

        $this->add_control(
            'delivery_outside',
            [
                'label' => __('Outside Dhaka Delivery Charge', 'ronginpran-checkout'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => 0,
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();

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
