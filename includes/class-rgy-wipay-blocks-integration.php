<?php
/**
 * WooCommerce Blocks payment method integration
 *
 * @package Revamped_WiPay_WooCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

class RGY_WiPay_Blocks_Integration extends AbstractPaymentMethodType {

    /**
     * Payment method name.
     *
     * Must match the gateway ID.
     *
     * @var string
     */
    protected $name = 'rgy_wipay';

    /**
     * Gateway settings.
     *
     * @var array
     */
    protected $settings = array();

    /**
     * Initialise the payment method type.
     */
    public function initialize() {
        $this->settings = (array) get_option('woocommerce_rgy_wipay_settings', array());
    }

    /**
     * Determine if the payment method is active.
     *
     * @return bool
     */
    public function is_active() {
        return isset($this->settings['enabled']) && $this->settings['enabled'] === 'yes';
    }

    /**
     * Register and return the JS handles for Blocks.
     *
     * @return string[]
     */
    public function get_payment_method_script_handles() {
        $handle = 'rgy-wipay-blocks';

        if (!wp_script_is($handle, 'registered')) {
            wp_register_script(
                $handle,
                (defined('RGY_WIPAY_WC_URL') ? RGY_WIPAY_WC_URL : plugin_dir_url(__FILE__)) . 'assets/js/blocks.js',
                array(
                    'wc-blocks-registry',
                    'wc-settings',
                    'wp-element',
                    'wp-html-entities',
                    'wp-i18n',
                ),
                defined('RGY_WIPAY_WC_VERSION') ? RGY_WIPAY_WC_VERSION : '1.0.0',
                true
            );
        }

        return array($handle);
    }

    /**
     * Data exposed to the Blocks frontend JS.
     *
     * @return array
     */
    public function get_payment_method_data() {
        $title = isset($this->settings['title']) ? (string) $this->settings['title'] : __('Credit or Debit Card (WiPay)', 'revamped-wipay-woocommerce');
        $desc  = isset($this->settings['description']) ? (string) $this->settings['description'] : __('Pay securely via WiPay.', 'revamped-wipay-woocommerce');

        $charge_currency = isset($this->settings['charge_currency']) ? (string) $this->settings['charge_currency'] : 'store';
        $charge_currency = in_array($charge_currency, array('store', 'usd'), true) ? $charge_currency : 'store';
        if ('usd' === $charge_currency) {
            $desc = trim($desc . ' ' . __('WiPay will charge you in USD. The final USD amount is calculated when you place the order.', 'revamped-wipay-woocommerce'));
        }

        $logo = defined('RGY_WIPAY_LOGO_URL') ? (string) RGY_WIPAY_LOGO_URL : '';

        return array(
            'title'       => $title,
            'description' => $desc,
            'logo_url'    => $logo,
            // Use Blocks native icon rendering so the logo is consistently sized beside the label.
            'icons'       => !empty($logo) ? array(
                array(
                    'id'  => 'wipay',
                    'src' => $logo,
                    'alt' => __('WiPay', 'revamped-wipay-woocommerce'),
                ),
            ) : array(),
            // Match gateway supports so Blocks can assess compatibility correctly.
            'supports'    => array('products'),
        );
    }
}
