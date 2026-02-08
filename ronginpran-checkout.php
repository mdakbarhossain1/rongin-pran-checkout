<?php
/**
 * Plugin Name: Rongin Pran Variable Product Checkout
 * Plugin URI: https://ronginpran.com
 * Description: Simple variable product checkout system for Elementor
 * Version: 1.1.0
 * Author: Akbar Hossain
 * License: GPL v2 or later
 * Text Domain: ronginpran-checkout
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) exit;

define('RPC_VERSION', '1.1.0');
define('RPC_PLUGIN_FILE', __FILE__);
define('RPC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RPC_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once RPC_PLUGIN_DIR . 'includes/class-rpc-plugin.php';

register_activation_hook(__FILE__, ['RPC_Plugin', 'activate']);
register_deactivation_hook(__FILE__, ['RPC_Plugin', 'deactivate']);

add_action('plugins_loaded', function () {
    RPC_Plugin::instance()->init();
});
