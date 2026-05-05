<?php
/**
 * Plugin Name: Revamped WiPay Gateway for WooCommerce
 * Plugin URI: https://revamped.gy/plugins/revamped-wipay-gateway-for-woocommerce/
 * Description: Accept card payments via WiPay by redirecting customers to WiPay's hosted payment page, then confirming payment back in WooCommerce.
 * Version: 1.2.0
 * Author: Revamped GY
 * Author URI: https://revamped.gy
 * Text Domain: revamped-wipay-woocommerce
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 *
 * WC requires at least: 6.0
 * WC tested up to: 9.0
 *
 * @package Revamped_WiPay_WooCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('RGY_WIPAY_WC_VERSION')) {
    define('RGY_WIPAY_WC_VERSION', '1.2.0');
}
if (!defined('RGY_WIPAY_WC_FILE')) {
    define('RGY_WIPAY_WC_FILE', __FILE__);
}
if (!defined('RGY_WIPAY_WC_PATH')) {
    define('RGY_WIPAY_WC_PATH', plugin_dir_path(__FILE__));
}
if (!defined('RGY_WIPAY_WC_URL')) {
    define('RGY_WIPAY_WC_URL', plugin_dir_url(__FILE__));
}

if (!defined('RGY_WIPAY_LOGO_URL')) {
    // Default logo used for both Classic and Blocks checkout.
    define('RGY_WIPAY_LOGO_URL', 'https://revamped.gy/revamped-uploads/2026/02/WI-Pay-Transparent-Logo-1.webp');
}

if (!defined('RGY_WIPAY_GITHUB_REPO')) {
    // GitHub repo in owner/repo format. Releases published on this repo
    // become available updates inside WordPress automatically.
    // Override at runtime with the rgy_wipay_github_repo filter.
    define('RGY_WIPAY_GITHUB_REPO', 'RevampedGY/revamped-wipay-woocommerce');
}

require_once RGY_WIPAY_WC_PATH . 'includes/class-rgy-wipay-admin.php';
require_once RGY_WIPAY_WC_PATH . 'includes/class-rgy-wipay-updater.php';


final class RGY_WiPay_WooCommerce_Plugin {

    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Run early so we can register Blocks hooks before WooCommerce Blocks fires woocommerce_blocks_loaded.
        add_action('plugins_loaded', array($this, 'init'), 1);
        add_action('init', array($this, 'load_textdomain'));
        add_action('admin_notices', array($this, 'maybe_show_woocommerce_notice'));
    }


    public function load_textdomain() {
        load_plugin_textdomain('revamped-wipay-woocommerce', false, dirname(plugin_basename(RGY_WIPAY_WC_FILE)) . '/languages');
    }

    public function init() {
        // Updater should run even if WooCommerce is inactive.
        if (class_exists('RGY_WiPay_Updater')) {
            RGY_WiPay_Updater::instance();
        }

        // Admin pages always load, even if WooCommerce is inactive.
        RGY_WiPay_Admin::instance();

        if (!$this->is_woocommerce_active()) {
            return;
        }

        // Load WooCommerce dependent modules safely.
        $gateway_file = RGY_WIPAY_WC_PATH . 'includes/class-rgy-wipay-gateway.php';
        $blocks_file  = RGY_WIPAY_WC_PATH . 'includes/class-rgy-wipay-blocks.php';

        if (file_exists($gateway_file)) {
            require_once $gateway_file;
        }

        if (file_exists($blocks_file)) {
            require_once $blocks_file;
        }

        if (class_exists('RGY_WiPay_Blocks')) {
            RGY_WiPay_Blocks::instance();
        }

        add_filter('woocommerce_payment_gateways', array($this, 'register_gateway'));
        add_action('woocommerce_api_rgy_wipay_return', array('RGY_WiPay_Gateway', 'handle_return'));
    }

    public function register_gateway($gateways) {
        if (class_exists('RGY_WiPay_Gateway')) {
            $gateways[] = 'RGY_WiPay_Gateway';
        }
        return $gateways;
    }

    public function is_woocommerce_active() {
        return class_exists('WooCommerce') && function_exists('WC');
    }

    public function maybe_show_woocommerce_notice() {
        if ($this->is_woocommerce_active()) {
            return;
        }

        if (!current_user_can('activate_plugins')) {
            return;
        }

        echo '<div class="notice notice-error"><p>';
        echo esc_html__('Revamped WiPay Gateway for WooCommerce requires WooCommerce to be installed and active.', 'revamped-wipay-woocommerce');
        echo '</p></div>';
    }
}

RGY_WiPay_WooCommerce_Plugin::instance();

// --- Stale pending order cleanup (daily cron) ---

register_activation_hook(RGY_WIPAY_WC_FILE, 'rgy_wipay_activate');
function rgy_wipay_activate() {
    if (!wp_next_scheduled('rgy_wipay_cleanup_pending_orders')) {
        wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'rgy_wipay_cleanup_pending_orders');
    }
}

register_deactivation_hook(RGY_WIPAY_WC_FILE, 'rgy_wipay_deactivate');
function rgy_wipay_deactivate() {
    wp_clear_scheduled_hook('rgy_wipay_cleanup_pending_orders');
}

add_action('rgy_wipay_cleanup_pending_orders', 'rgy_wipay_do_cleanup_pending_orders');
function rgy_wipay_do_cleanup_pending_orders() {
    if (!function_exists('wc_get_orders')) {
        return;
    }

    $cutoff = absint(apply_filters('rgy_wipay_pending_order_max_age', 4 * HOUR_IN_SECONDS));
    $cutoff_date = gmdate('Y-m-d H:i:s', time() - $cutoff);

    $orders = wc_get_orders(array(
        'status'         => 'pending',
        'payment_method' => 'rgy_wipay',
        'date_created'   => '<' . $cutoff_date,
        'limit'          => 50,
        'return'         => 'ids',
    ));

    foreach ($orders as $order_id) {
        $order = wc_get_order($order_id);
        if (!$order || $order->is_paid()) {
            continue;
        }
        $order->update_status('cancelled', __('Auto-cancelled: WiPay payment was not completed within the allowed window.', 'revamped-wipay-woocommerce'));
    }
}
