<?php
/**
 * WooCommerce Blocks integration
 *
 * @package Revamped_WiPay_WooCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

final class RGY_WiPay_Blocks {

    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('before_woocommerce_init', array($this, 'declare_compatibility'));
        add_action('woocommerce_blocks_loaded', array($this, 'maybe_register_blocks'));
    }

    public function declare_compatibility() {
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', RGY_WIPAY_WC_FILE, true);
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', RGY_WIPAY_WC_FILE, true);
        }
    }

    public function maybe_register_blocks() {
        if (!class_exists('\Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
            return;
        }

        $integration_file = defined('RGY_WIPAY_WC_PATH') ? RGY_WIPAY_WC_PATH . 'includes/class-rgy-wipay-blocks-integration.php' : '';
        if ($integration_file && file_exists($integration_file)) {
            require_once $integration_file;
        }

        add_action(
            'woocommerce_blocks_payment_method_type_registration',
            function($payment_method_registry) {
                if (class_exists('RGY_WiPay_Blocks_Integration')) {
                    $payment_method_registry->register(new RGY_WiPay_Blocks_Integration());
                }
            }
        );
    }
}
