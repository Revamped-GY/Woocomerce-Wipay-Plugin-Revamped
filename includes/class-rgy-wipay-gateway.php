<?php
/**
 * WiPay Gateway
 *
 * @package Revamped_WiPay_WooCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

class RGY_WiPay_Gateway extends WC_Payment_Gateway {

    const META_WIPAY_ORDER_ID = '_rgy_wipay_order_id';
    const META_ORIGINAL_TOTAL = '_rgy_wipay_original_total';
    const META_ENVIRONMENT = '_rgy_wipay_environment';
    const META_TRANSACTION_ID = '_rgy_wipay_transaction_id';
    const META_CHARGED_TOTAL = '_rgy_wipay_charged_total';
    const META_RESPONSE_MESSAGE = '_rgy_wipay_response_message';

    const META_REQUEST_TRANSACTION_ID = '_rgy_wipay_request_transaction_id';
    const META_PAID_TRANSACTION_ID = '_rgy_wipay_paid_transaction_id';
    const META_RETURN_TS = '_rgy_wipay_return_ts';
    const META_RETURN_TOKEN = '_rgy_wipay_return_token';

    // Currency conversion metadata.
    const META_SENT_CURRENCY = '_rgy_wipay_sent_currency';
    const META_STORE_CURRENCY = '_rgy_wipay_store_currency';
    const META_STORE_TOTAL = '_rgy_wipay_store_total';
    const META_FX_PROVIDER = '_rgy_wipay_fx_provider';
    const META_FX_RATE = '_rgy_wipay_fx_rate';
    const META_FX_FETCHED_AT = '_rgy_wipay_fx_fetched_at';

    public function __construct() {
        $this->id                 = 'rgy_wipay';
        $this->method_title       = __('WiPay', 'revamped-wipay-woocommerce');
        $this->method_description = __('Redirect customers to WiPay to pay by card, then confirm payment back in WooCommerce.', 'revamped-wipay-woocommerce');
        $this->has_fields         = false;
        // Classic checkout icon is rendered via get_icon() to control size.
        $this->icon = '';

        // WiPay has no refund API. Without 'refunds' WooCommerce offers only
        // "Refund manually", which is the step merchants need after refunding
        // in the WiPay dashboard.
        $this->supports = array(
            'products',
        );

        $this->init_form_fields();
        $this->init_settings();

        $this->title       = (string) $this->get_option('title', __('Credit or Debit Card (WiPay)', 'revamped-wipay-woocommerce'));
        $this->description = (string) $this->get_option('description', __('Pay securely via WiPay.', 'revamped-wipay-woocommerce'));

        // Add a clear note when the gateway is configured to charge in USD.
        $charge_currency = (string) $this->get_option('charge_currency', 'store');
        $charge_currency = in_array($charge_currency, array('store', 'usd'), true) ? $charge_currency : 'store';
        if ('usd' === $charge_currency) {
            $this->description = trim($this->description . ' ' . __('WiPay will charge you in USD. The final USD amount is calculated when you place the order.', 'revamped-wipay-woocommerce'));
        }

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    }

    /**
     * Return a small, consistent icon for Classic Checkout.
     *
     * Applies the woocommerce_gateway_icon filter so themes and
     * other plugins can modify the icon output.
     *
     * @return string
     */
    public function get_icon() {
        $logo = defined('RGY_WIPAY_LOGO_URL') ? (string) RGY_WIPAY_LOGO_URL : '';
        $logo = (string) apply_filters('rgy_wipay_gateway_icon_url', $logo);

        if (empty($logo)) {
            return '';
        }

        $html  = '<img src="' . esc_url($logo) . '" alt="' . esc_attr__('WiPay', 'revamped-wipay-woocommerce') . '"';
        $html .= ' style="height:16px;width:auto;max-height:16px;vertical-align:middle;" />';

        return apply_filters('woocommerce_gateway_icon', $html, $this->id);
    }

    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => __('Enable/Disable', 'revamped-wipay-woocommerce'),
                'type'    => 'checkbox',
                'label'   => __('Enable WiPay payments', 'revamped-wipay-woocommerce'),
                'default' => 'no',
            ),
            'title' => array(
                'title'       => __('Title', 'revamped-wipay-woocommerce'),
                'type'        => 'text',
                'description' => __('Payment method title shown to customers at checkout.', 'revamped-wipay-woocommerce'),
                'default'     => __('Credit or Debit Card (WiPay)', 'revamped-wipay-woocommerce'),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __('Description', 'revamped-wipay-woocommerce'),
                'type'        => 'textarea',
                'description' => __('Payment method description shown to customers at checkout.', 'revamped-wipay-woocommerce'),
                'default'     => __('Pay securely via WiPay.', 'revamped-wipay-woocommerce'),
            ),
            'environment' => array(
                'title'       => __('Environment', 'revamped-wipay-woocommerce'),
                'type'        => 'select',
                'description' => __('Choose sandbox for testing or live for production.', 'revamped-wipay-woocommerce'),
                'default'     => 'sandbox',
                'options'     => array(
                    'sandbox' => __('Sandbox', 'revamped-wipay-woocommerce'),
                    'live'    => __('Live', 'revamped-wipay-woocommerce'),
                ),
                'desc_tip'    => true,
            ),
            'country' => array(
                'title'       => __('API Country', 'revamped-wipay-woocommerce'),
                'type'        => 'select',
                'description' => __('Select the WiPay platform country for your merchant account.', 'revamped-wipay-woocommerce'),
                'default'     => 'gy',
                'options'     => array(
                    'gy' => __('Guyana (gy)', 'revamped-wipay-woocommerce'),
                    'tt' => __('Trinidad and Tobago (tt)', 'revamped-wipay-woocommerce'),
                    'jm' => __('Jamaica (jm)', 'revamped-wipay-woocommerce'),
                    'bb' => __('Barbados (bb)', 'revamped-wipay-woocommerce'),
                ),
                'desc_tip'    => true,
            ),
            'api_base_url' => array(
                'title'       => __('API Base URL override', 'revamped-wipay-woocommerce'),
                'type'        => 'text',
                'description' => __('Optional. Leave blank to use the default country URL. Example: https://gy.wipayfinancial.com', 'revamped-wipay-woocommerce'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'account_number_live' => array(
                'title'       => __('Live Account Number', 'revamped-wipay-woocommerce'),
                'type'        => 'text',
                'description' => __('Your WiPay account number for live mode.', 'revamped-wipay-woocommerce'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'api_key_live' => array(
                'title'       => __('Live API Key', 'revamped-wipay-woocommerce'),
                'type'        => 'password',
                'description' => __('Used to validate successful payment redirects. Keep this secret.', 'revamped-wipay-woocommerce'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'account_number_sandbox' => array(
                'title'       => __('Sandbox Account Number', 'revamped-wipay-woocommerce'),
                'type'        => 'text',
                'description' => __('Default sandbox account number is 1234567890.', 'revamped-wipay-woocommerce'),
                'default'     => '1234567890',
                'desc_tip'    => true,
            ),
            'api_key_sandbox' => array(
                'title'       => __('Sandbox API Key', 'revamped-wipay-woocommerce'),
                'type'        => 'password',
                'description' => __('Default sandbox API key is 123.', 'revamped-wipay-woocommerce'),
                'default'     => '123',
                'desc_tip'    => true,
            ),
            'fee_structure' => array(
                'title'       => __('Fee structure', 'revamped-wipay-woocommerce'),
                'type'        => 'select',
                'description' => __('Choose how WiPay fees are handled.', 'revamped-wipay-woocommerce'),
                'default'     => 'merchant_absorb',
                'options'     => array(
                    'merchant_absorb' => __('Merchant absorbs', 'revamped-wipay-woocommerce'),
                    'customer_pay'    => __('Customer pays', 'revamped-wipay-woocommerce'),
                    'split'           => __('Split', 'revamped-wipay-woocommerce'),
                ),
                'desc_tip'    => true,
            ),

            'charge_currency' => array(
                'title'       => __('Charge currency', 'revamped-wipay-woocommerce'),
                'type'        => 'select',
                'description' => __('Choose the currency WiPay should receive. If set to USD, the plugin converts the order total from the store currency to USD using a live exchange rate, then rounds up to the next whole USD before redirecting to WiPay.', 'revamped-wipay-woocommerce'),
                'default'     => 'store',
                'options'     => array(
                    'store' => __('Store currency', 'revamped-wipay-woocommerce'),
                    'usd'   => __('USD (auto convert and round up)', 'revamped-wipay-woocommerce'),
                ),
                'desc_tip'    => true,
            ),

            'fx_cache_minutes' => array(
                'title'       => __('Exchange rate cache (minutes)', 'revamped-wipay-woocommerce'),
                'type'        => 'number',
                'description' => __('How long to cache the USD exchange rate for each currency. Lower values use newer rates but may increase external requests.', 'revamped-wipay-woocommerce'),
                'default'     => 60,
                'custom_attributes' => array(
                    'min'  => 5,
                    'step' => 1,
                ),
                'desc_tip'    => true,
            ),
            'avs' => array(
                'title'       => __('AVS', 'revamped-wipay-woocommerce'),
                'type'        => 'checkbox',
                'label'       => __('Enable AVS checks', 'revamped-wipay-woocommerce'),
                'default'     => 'no',
            ),
            'origin' => array(
                'title'       => __('Origin', 'revamped-wipay-woocommerce'),
                'type'        => 'text',
                'description' => __('Identifier sent to WiPay. Leave default unless WiPay requests a specific value.', 'revamped-wipay-woocommerce'),
                'default'     => 'revamped-gy-woocommerce',
                'desc_tip'    => true,
            ),
            'card_type' => array(
                'title'       => __('Card type (optional)', 'revamped-wipay-woocommerce'),
                'type'        => 'select',
                'description' => __('Optionally skip WiPay card type selection screen.', 'revamped-wipay-woocommerce'),
                'default'     => '',
                'options'     => array(
                    ''           => __('Let customer choose', 'revamped-wipay-woocommerce'),
                    'visa'       => __('Visa', 'revamped-wipay-woocommerce'),
                    'mastercard' => __('Mastercard', 'revamped-wipay-woocommerce'),
                ),
                'desc_tip'    => true,
            ),
            'debug_log' => array(
                'title'       => __('Debug logging', 'revamped-wipay-woocommerce'),
                'type'        => 'checkbox',
                'label'       => __('Enable WooCommerce logs for WiPay requests and responses', 'revamped-wipay-woocommerce'),
                'default'     => 'no',
            ),
        );
    }

    /**
     * Fetch an exchange rate from a currency to USD.
     *
     * Uses a transient cache to reduce external requests.
     */
    private function get_rate_to_usd($from_currency) {
        $from_currency = strtoupper(sanitize_text_field((string) $from_currency));
        if (!$from_currency) {
            return null;
        }
        if ('USD' === $from_currency) {
            return 1.0;
        }

        $cache_minutes = absint($this->get_option('fx_cache_minutes', 60));
        if ($cache_minutes < 5) {
            $cache_minutes = 5;
        }

        $cache_key = 'rgy_wipay_fx_' . strtolower($from_currency) . '_usd';
        $cached = get_transient($cache_key);
        if (is_array($cached) && isset($cached['rate']) && is_numeric($cached['rate'])) {
            return (float) $cached['rate'];
        }

        // Public endpoint (no key) used to fetch latest rates.
        // Example: https://open.er-api.com/v6/latest/GYD
        $url = 'https://open.er-api.com/v6/latest/' . rawurlencode($from_currency);

        $response = wp_remote_get($url, array(
            'timeout' => 15,
            'headers' => array('Accept' => 'application/json'),
        ));

        if (is_wp_error($response)) {
            $this->log('FX rate request failed: ' . $response->get_error_message(), 'error');
            return $this->get_fallback_rate($from_currency);
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);

        if (200 !== $code || '' === $body) {
            $this->log('FX rate request failed. code=' . $code, 'error');
            return $this->get_fallback_rate($from_currency);
        }

        $data = json_decode($body, true);
        if (!is_array($data) || empty($data['rates']) || !is_array($data['rates']) || !isset($data['rates']['USD'])) {
            $this->log('FX rate response missing USD rate. from=' . $from_currency, 'error');
            return $this->get_fallback_rate($from_currency);
        }

        $rate = $data['rates']['USD'];
        if (!is_numeric($rate) || (float) $rate <= 0) {
            $this->log('FX rate is invalid. from=' . $from_currency, 'error');
            return $this->get_fallback_rate($from_currency);
        }

        $rate = (float) $rate;
        set_transient($cache_key, array('rate' => $rate, 'fetched_at' => time()), $cache_minutes * MINUTE_IN_SECONDS);

        // Persist a fallback rate so orders can still process if the API goes down.
        $fallback_key = 'rgy_wipay_fx_fallback_' . strtolower($from_currency) . '_usd';
        update_option($fallback_key, array('rate' => $rate, 'fetched_at' => time()), false);

        return $rate;
    }

    /**
     * Returns a previously stored fallback FX rate when the live API is unavailable.
     */
    private function get_fallback_rate($from_currency) {
        $from_currency = strtoupper((string) $from_currency);
        $fallback_key = 'rgy_wipay_fx_fallback_' . strtolower($from_currency) . '_usd';
        $fallback = get_option($fallback_key);
        $max_age = absint(apply_filters('rgy_wipay_fx_fallback_max_age', 7 * DAY_IN_SECONDS));
        $fetched_at = is_array($fallback) && isset($fallback['fetched_at']) ? absint($fallback['fetched_at']) : 0;
        if ($max_age && (!$fetched_at || (time() - $fetched_at) > $max_age)) {
            $this->log('Stored FX rate for ' . $from_currency . ' is too old to use.', 'error');
            return null;
        }
        if (is_array($fallback) && !empty($fallback['rate']) && is_numeric($fallback['rate'])) {
            $this->log('Using fallback FX rate for ' . $from_currency . '. rate=' . $fallback['rate'] . ' fetched_at=' . (isset($fallback['fetched_at']) ? $fallback['fetched_at'] : '?'), 'warning');
            return (float) $fallback['rate'];
        }
        return null;
    }

    /**
     * Determines the currency and total that should be sent to WiPay.
     */
    private function get_charge_amount_for_order($order) {
        $store_currency = $order ? (string) $order->get_currency() : '';
        $store_total    = $order ? (float) $order->get_total() : 0.0;

        $store_total_str = number_format((float) $store_total, 2, '.', '');
        $store_currency  = strtoupper(sanitize_text_field($store_currency));

        $charge_currency = (string) $this->get_option('charge_currency', 'store');
        $charge_currency = in_array($charge_currency, array('store', 'usd'), true) ? $charge_currency : 'store';

        // Default: send store currency.
        if ('store' === $charge_currency) {
            return array(
                'sent_currency' => $store_currency,
                'sent_total'    => $store_total_str,
                'store_currency'=> $store_currency,
                'store_total'   => $store_total_str,
                'fx_provider'   => '',
                'fx_rate'       => '',
                'fx_fetched_at' => '',
            );
        }

        // Store already uses USD: no conversion or rounding needed.
        if ('USD' === $store_currency) {
            return array(
                'sent_currency' => 'USD',
                'sent_total'    => $store_total_str,
                'store_currency'=> $store_currency,
                'store_total'   => $store_total_str,
                'fx_provider'   => '',
                'fx_rate'       => '',
                'fx_fetched_at' => '',
            );
        }

        $rate = $this->get_rate_to_usd($store_currency);
        if (!$rate) {
            return null;
        }

        $converted = (float) $store_total * (float) $rate;
        $usd_total = (float) ceil($converted);
        if ($usd_total < 1) {
            $usd_total = 1;
        }

        return array(
            'sent_currency' => 'USD',
            'sent_total'    => number_format($usd_total, 2, '.', ''),
            'store_currency'=> $store_currency,
            'store_total'   => $store_total_str,
            'fx_provider'   => 'open.er-api.com',
            'fx_rate'       => (string) $rate,
            'fx_fetched_at' => (string) time(),
        );
    }

    private function get_logger() {
        return wc_get_logger();
    }

    private function log($message, $level = 'info') {
        if ('yes' !== $this->get_option('debug_log', 'no')) {
            return;
        }
        $this->get_logger()->log($level, $message, array('source' => 'rgy-wipay'));
    }

    private function mask_sensitive($value, $show_last = 4) {
        $value = (string) $value;
        $value = trim($value);
        if ('' === $value) {
            return '';
        }

        $len = strlen($value);
        $show_last = absint($show_last);

        if ($len <= $show_last) {
            return str_repeat('*', $len);
        }

        return str_repeat('*', $len - $show_last) . substr($value, -$show_last);
    }

    private function mask_email($email) {
        $email = (string) $email;
        $email = trim($email);
        if ('' === $email || false === strpos($email, '@')) {
            return $this->mask_sensitive($email, 3);
        }

        list($user, $domain) = explode('@', $email, 2);
        $user_masked = $this->mask_sensitive($user, 2);

        return $user_masked . '@' . $domain;
    }

    private function get_environment() {
        $env = (string) $this->get_option('environment', 'sandbox');
        return in_array($env, array('sandbox', 'live'), true) ? $env : 'sandbox';
    }

    private function get_account_number($env) {
        if ('live' === $env) {
            return trim((string) $this->get_option('account_number_live', ''));
        }
        $sandbox = trim((string) $this->get_option('account_number_sandbox', '1234567890'));
        return $sandbox ? $sandbox : '1234567890';
    }

    private function get_api_key($env) {
        if ('live' === $env) {
            return (string) $this->get_option('api_key_live', '');
        }
        $sandbox = (string) $this->get_option('api_key_sandbox', '123');
        return $sandbox ? $sandbox : '123';
    }

    private function get_request_endpoint() {
        $override = trim((string) $this->get_option('api_base_url', ''));
        $country  = strtolower(trim((string) $this->get_option('country', 'gy')));

        $base = '';
        if ($override && 'https' === strtolower((string) wp_parse_url($override, PHP_URL_SCHEME))) {
            $base = rtrim($override, '/');
        } else {
            $country = preg_replace('/[^a-z]/', '', $country);
            if (!$country) {
                $country = 'gy';
            }
            $base = 'https://' . $country . '.wipayfinancial.com';
        }

        return $base . '/plugins/payments/request';
    }

    public function is_available() {
        if (!parent::is_available()) {
            return false;
        }

        $env = $this->get_environment();
        $account = $this->get_account_number($env);

        if (empty($account)) {
            return false;
        }

        // Require an API key in live mode so hash validation is never skipped.
        if ('live' === $env && empty($this->get_api_key($env))) {
            return false;
        }

        // Do not offer a method that can only fail at Place order. The Overview
        // page tells the store owner to switch Charge currency to USD.
        if ('store' === $this->get_charge_currency_option() && !self::is_supported_currency($this->get_checkout_currency())) {
            return false;
        }

        return true;
    }

    private function get_charge_currency_option() {
        $value = (string) $this->get_option('charge_currency', 'store');
        return in_array($value, array('store', 'usd'), true) ? $value : 'store';
    }

    private function get_checkout_currency() {
        $currency = '';
        if (is_admin() && !wp_doing_ajax()) {
            return '';
        }
        $order_id = absint(get_query_var('order-pay'));
        if ($order_id) {
            $order = wc_get_order($order_id);
            if ($order) {
                $currency = (string) $order->get_currency();
            }
        }
        if ('' === $currency && function_exists('get_woocommerce_currency')) {
            $currency = (string) get_woocommerce_currency();
        }
        return strtoupper($currency);
    }

    /**
     * Currencies WiPay accepts for card payments.
     */
    public static function supported_currencies() {
        return (array) apply_filters('rgy_wipay_supported_currencies', array('JMD', 'TTD', 'USD'));
    }

    public static function is_supported_currency($currency) {
        $currency = strtoupper((string) $currency);
        return '' === $currency || in_array($currency, self::supported_currencies(), true);
    }

    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            wc_add_notice(__('Payment error. Order not found.', 'revamped-wipay-woocommerce'), 'error');
            return array('result' => 'fail');
        }

        $env = $this->get_environment();

        $account_number = $this->get_account_number($env);
        if (!$account_number) {
            wc_add_notice(__('WiPay is not configured. Please contact the store owner.', 'revamped-wipay-woocommerce'), 'error');
            return array('result' => 'fail');
        }

        $order_key = $order->get_order_key();
        // WiPay order_id max length is 16 chars (FGB) or 48 (FAC).
        // Use 16 universally for safety. Adjust key suffix to fit.
        $id_str = (string) $order->get_id();
        $key_len = min(8, max(2, 12 - strlen($id_str)));
        $wipay_order_id = 'wc_' . $id_str . '_' . substr($order_key, 0, $key_len);

        // Validate store currency is supported when sending as-is.
        if ('store' === $this->get_charge_currency_option()) {
            $order_currency = strtoupper((string) $order->get_currency());
            if ($order_currency && !self::is_supported_currency($order_currency)) {
                wc_add_notice(
                    sprintf(
                        /* translators: %s: store currency code */
                        __('Your store currency (%s) is not supported by WiPay. Please contact the store owner.', 'revamped-wipay-woocommerce'),
                        $order_currency
                    ),
                    'error'
                );
                $this->log('Unsupported currency for WiPay store-mode: ' . $order_currency, 'error');
                return array('result' => 'fail');
            }
        }

        $charge = $this->get_charge_amount_for_order($order);
        if (!$charge || empty($charge['sent_currency']) || empty($charge['sent_total'])) {
            wc_add_notice(__('WiPay could not calculate the USD total. Please try again or contact the store owner.', 'revamped-wipay-woocommerce'), 'error');
            $this->log('Failed to calculate charge amount for order #' . $order->get_id() . ' currency=' . $order->get_currency(), 'error');
            return array('result' => 'fail');
        }

        // The amount and currency sent to WiPay (may differ from store currency).
        $original_total = (string) $charge['sent_total'];
        $currency = (string) $charge['sent_currency'];

        $ts = time();

        $token = self::build_return_token($order->get_id(), $order_key, $ts);

        $response_url = WC()->api_request_url('rgy_wipay_return');
        $response_url = add_query_arg(array(
            'wc_order' => $order->get_id(),
            'ts'       => $ts,
            'token'    => $token,
        ), $response_url);

        $body = array(
            'account_number' => $account_number,
            'country_code'   => strtoupper(substr((string) $this->get_option('country', 'gy'), 0, 2)),
            'currency'       => $currency,
            'environment'    => $env,
            'fee_structure'  => (string) $this->get_option('fee_structure', 'merchant_absorb'),
            'method'         => 'credit_card',
            'order_id'       => $wipay_order_id,
            'origin'         => (string) $this->get_option('origin', 'revamped-gy-woocommerce'),
            'response_url'   => $response_url,
            'total'          => $original_total,
        );

        // Send plugin version to help WiPay identify the integration.
        $body['version'] = defined('RGY_WIPAY_WC_VERSION') ? RGY_WIPAY_WC_VERSION : '1.0.0';

        if ('yes' === $this->get_option('avs', 'no')) {
            $body['avs'] = '1';
        }

        $card_type = (string) $this->get_option('card_type', '');
        if ($card_type) {
            $body['card_type'] = $card_type;
        }

        // Allow other plugins to attach custom data that WiPay echoes back.
        $custom_data = apply_filters('rgy_wipay_request_data', '', $order);
        if (is_string($custom_data) && '' !== $custom_data) {
            $body['data'] = $custom_data;
        }

        // Optional billing prefill fields.
        // Field names match WiPay API: fname, lname, addr1, addr2, zipcode.
        $billing_first = $order->get_billing_first_name();
        $billing_last  = $order->get_billing_last_name();
        if ($billing_first) {
            $body['fname'] = $billing_first;
        }
        if ($billing_last) {
            $body['lname'] = $billing_last;
        }
        $billing_email = $order->get_billing_email();
        if ($billing_email) {
            $body['email'] = $billing_email;
        }
        $billing_phone = $order->get_billing_phone();
        if ($billing_phone) {
            $body['phone'] = $billing_phone;
        }

        $address_1 = $order->get_billing_address_1();
        $address_2 = $order->get_billing_address_2();
        $city      = $order->get_billing_city();
        $state     = $order->get_billing_state();
        $postcode  = $order->get_billing_postcode();
        $country   = $order->get_billing_country();

        if ($address_1) { $body['addr1'] = $address_1; }
        if ($address_2) { $body['addr2'] = $address_2; }
        if ($city)      { $body['city'] = $city; }
        if ($state)     { $body['state'] = $state; }
        if ($postcode)  { $body['zipcode'] = $postcode; }
        if ($country)   { $body['country'] = $country; }

        $endpoint = $this->get_request_endpoint();

        $log_body = $body;
        if (isset($log_body['account_number'])) {
            $log_body['account_number'] = $this->mask_sensitive($log_body['account_number'], 4);
        }
        if (isset($log_body['email'])) {
            $log_body['email'] = $this->mask_email($log_body['email']);
        }
        if (isset($log_body['phone'])) {
            $log_body['phone'] = $this->mask_sensitive($log_body['phone'], 3);
        }

        $this->log('WiPay request payload=' . wp_json_encode($log_body));
        $this->log('Requesting WiPay URL for order #' . $order->get_id() . ' endpoint=' . $endpoint);

        $response = wp_remote_post($endpoint, array(
            'timeout' => 30,
            'headers' => array(
                'Accept'       => 'application/json',
                'Content-Type' => 'application/x-www-form-urlencoded',
            ),
            'body' => $body,
        ));

        if (is_wp_error($response)) {
            $this->log('WiPay request error: ' . $response->get_error_message(), 'error');
            wc_add_notice(__('WiPay is currently unavailable. Please try again.', 'revamped-wipay-woocommerce'), 'error');
            return array('result' => 'fail');
        }

        $code = wp_remote_retrieve_response_code($response);
        $raw  = wp_remote_retrieve_body($response);

        $raw_log = $raw;
        if (is_string($raw_log) && strlen($raw_log) > 2000) {
            $raw_log = substr($raw_log, 0, 2000) . '... (truncated)';
        }

        // Redact sensitive parameters from logs.
        if (is_string($raw_log)) {
            $raw_log = preg_replace('~(_data=)[^&"\s]+~i', '$1REDACTED', $raw_log);
            $raw_log = preg_replace('~(signature=)[^&"\s]+~i', '$1REDACTED', $raw_log);
        }

        $this->log('WiPay response code=' . $code . ' body=' . $raw_log);

        $data = json_decode($raw, true);
        if (!is_array($data) || empty($data['url']) || !is_string($data['url'])) {
            wc_add_notice(__('WiPay did not return a payment URL. Please try again.', 'revamped-wipay-woocommerce'), 'error');
            return array('result' => 'fail');
        }

        $wipay_url = esc_url_raw($data['url'], array('https'));
        if ('' === $wipay_url) {
            $this->log('WiPay returned a payment URL that is not HTTPS for order #' . $order->get_id(), 'error');
            wc_add_notice(__('WiPay did not return a payment URL. Please try again.', 'revamped-wipay-woocommerce'), 'error');
            return array('result' => 'fail');
        }
        $request_transaction_id = isset($data['transaction_id']) ? sanitize_text_field($data['transaction_id']) : '';

        $order->update_meta_data(self::META_WIPAY_ORDER_ID, $wipay_order_id);
        $order->update_meta_data(self::META_ORIGINAL_TOTAL, $original_total);
        $order->update_meta_data(self::META_SENT_CURRENCY, $currency);
        $order->update_meta_data(self::META_STORE_CURRENCY, isset($charge['store_currency']) ? (string) $charge['store_currency'] : (string) $order->get_currency());
        $order->update_meta_data(self::META_STORE_TOTAL, isset($charge['store_total']) ? (string) $charge['store_total'] : number_format((float) $order->get_total(), 2, '.', ''));
        if (!empty($charge['fx_provider'])) {
            $order->update_meta_data(self::META_FX_PROVIDER, (string) $charge['fx_provider']);
        }
        if (!empty($charge['fx_rate'])) {
            $order->update_meta_data(self::META_FX_RATE, (string) $charge['fx_rate']);
        }
        if (!empty($charge['fx_fetched_at'])) {
            $order->update_meta_data(self::META_FX_FETCHED_AT, (string) $charge['fx_fetched_at']);
        }
        $order->update_meta_data(self::META_ENVIRONMENT, $env);
        $order->update_meta_data(self::META_REQUEST_TRANSACTION_ID, $request_transaction_id);
        $order->update_meta_data(self::META_RETURN_TS, $ts);
        $order->update_meta_data(self::META_RETURN_TOKEN, $token);

        $order->set_status('pending', __('Awaiting WiPay payment.', 'revamped-wipay-woocommerce'));
        $order->save();

        return array(
            'result'   => 'success',
            'redirect' => $wipay_url,
        );
    }

    /**
     * WiPay does not expose a refund API. Return an error so the admin
     * can process the refund manually through the WiPay dashboard.
     */
    public function process_refund($order_id, $amount = null, $reason = '') {
        return new \WP_Error(
            'rgy_wipay_no_auto_refund',
            __('Automatic refunds are not supported by WiPay. Please process the refund through the WiPay Business dashboard, then use "Refund manually" in WooCommerce.', 'revamped-wipay-woocommerce')
        );
    }

    /**
     * Static helpers below exist because handle_return() is static.
     * A future release could refactor the return handler to use a
     * gateway instance, which would eliminate this duplication.
     */
    private static function get_settings_for_env($env) {
        $settings = get_option('woocommerce_rgy_wipay_settings', array());
        if (!is_array($settings)) {
            $settings = array();
        }

        if ('live' === $env) {
            return array(
                'api_key' => isset($settings['api_key_live']) ? (string) $settings['api_key_live'] : '',
            );
        }

        // Same default as get_api_key(): the public sandbox key.
        $sandbox = isset($settings['api_key_sandbox']) ? (string) $settings['api_key_sandbox'] : '';
        return array(
            'api_key' => '' !== $sandbox ? $sandbox : '123',
        );
    }

    /**
     * Short lock in the options table. INSERT IGNORE succeeds for one request
     * only, so two returns for the same transaction cannot both complete an
     * order.
     */
    private static function acquire_lock($name, $ttl = 120) {
        global $wpdb;
        $key = 'rgy_wipay_lock_' . hash('sha256', (string) $name);
        $value = time() . '|' . wp_generate_password(20, false, false);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $inserted = $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
            $key,
            $value
        ));
        if (1 !== (int) $inserted) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $current = (string) $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $key));
            $created = (int) strtok($current, '|');
            if ('' === $current || ($created > 0 && (time() - $created) <= $ttl)) {
                return array();
            }
            // Take over a lock left behind by a request that died.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $updated = $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s",
                $value,
                $key,
                $current
            ));
            if (1 !== (int) $updated) {
                return array();
            }
        }
        wp_cache_delete($key, 'options');
        wp_cache_delete('notoptions', 'options');
        return array('key' => $key, 'value' => $value);
    }

    private static function release_lock($lock) {
        global $wpdb;
        if (empty($lock['key']) || empty($lock['value'])) {
            return;
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s",
            $lock['key'],
            $lock['value']
        ));
        wp_cache_delete($lock['key'], 'options');
    }

    /**
     * IDs of other orders already paid with this WiPay transaction.
     */
    private static function orders_paid_with_transaction($transaction_id, $exclude_order_id) {
        $ids = array();
        $by_transaction = wc_get_orders(array(
            'transaction_id' => $transaction_id,
            'limit'          => 5,
            'return'         => 'ids',
            'status'         => array_keys(wc_get_order_statuses()),
        ));
        $by_meta = wc_get_orders(array(
            'meta_key'   => self::META_PAID_TRANSACTION_ID,
            'meta_value' => $transaction_id,
            'limit'      => 5,
            'return'     => 'ids',
            'status'     => array_keys(wc_get_order_statuses()),
        ));
        foreach (array_merge((array) $by_transaction, (array) $by_meta) as $id) {
            $id = absint($id);
            if ($id && $id !== absint($exclude_order_id)) {
                $ids[$id] = $id;
            }
        }
        return array_values($ids);
    }


    private static function debug_enabled() {
        $settings = get_option('woocommerce_rgy_wipay_settings', array());
        if (!is_array($settings)) {
            $settings = array();
        }
        return isset($settings['debug_log']) && $settings['debug_log'] === 'yes';
    }

    private static function log_static($message, $level = 'info') {
        if (!self::debug_enabled() || !function_exists('wc_get_logger')) {
            return;
        }

        $logger = wc_get_logger();
        $logger->log($level, (string) $message, array('source' => 'rgy-wipay'));
    }

    private static function get_client_ip() {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
        $ip = sanitize_text_field($ip);
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
    }

    private static function mask_id($value, $show_last = 6) {
        $value = (string) $value;
        $value = preg_replace('/[^A-Za-z0-9_-]/', '', $value);
        if (!$value) {
            return '';
        }

        $len = strlen($value);
        $show_last = max(0, (int) $show_last);
        if ($len <= $show_last) {
            return $value;
        }

        return str_repeat('*', $len - $show_last) . substr($value, -$show_last);
    }

    /**
     * Lightweight rate limiting per order + IP using transients.
     *
     * Note: transient-based rate limiting is best-effort. Object cache
     * eviction can reset the counter. For a payment gateway this is
     * acceptable as other checks (token, hash) provide the real security.
     */
    private static function rate_limit_ok($order_id) {
        $limit = absint(apply_filters('rgy_wipay_return_rate_limit', 20));
        $window = absint(apply_filters('rgy_wipay_return_rate_window', 600));

        if ($limit < 1 || $window < 30) {
            return true;
        }

        $ip = self::get_client_ip();
        $key = 'rgy_wipay_rl_' . absint($order_id) . '_' . md5($ip ? $ip : 'unknown');

        $data = get_transient($key);
        if (!is_array($data)) {
            $data = array('count' => 0, 'started' => time());
        }

        $now = time();
        $started = isset($data['started']) ? absint($data['started']) : $now;

        if (($now - $started) > $window) {
            $data = array('count' => 0, 'started' => $now);
        }

        $count = isset($data['count']) ? absint($data['count']) : 0;
        $count++;

        $data['count'] = $count;
        $data['started'] = $started;

        set_transient($key, $data, $window);

        return $count <= $limit;
    }

    private static function build_return_token($order_id, $order_key, $ts) {
        $salt = function_exists('wp_salt') ? wp_salt('auth') : (defined('AUTH_SALT') ? AUTH_SALT : 'rgy_wipay');
        $payload = absint($order_id) . '|' . (string) $order_key . '|' . absint($ts);
        return hash_hmac('sha256', $payload, $salt);
    }

    private static function validate_return_token($order, $ts, $token) {
        $token = (string) $token;
        $ts = absint($ts);

        if (!$order || !$ts || !$token) {
            return false;
        }

        // Default 4 hours to accommodate slow 3DS flows and customer delays.
        $ttl = absint(apply_filters('rgy_wipay_return_token_ttl', 14400));
        $future_drift = absint(apply_filters('rgy_wipay_return_token_future_drift', 300));

        $now = time();
        if ($ts > ($now + $future_drift)) {
            return false;
        }
        if ($ttl && ($now - $ts) > $ttl) {
            return false;
        }

        $expected = self::build_return_token($order->get_id(), $order->get_order_key(), $ts);
        return hash_equals($expected, $token);
    }

    /**
     * Handle the WiPay return redirect.
     *
     * WiPay uses browser-redirect (GET query parameters) for payment
     * confirmation. There is no server-to-server webhook/IPN. Security
     * relies on: signed token with expiry, hash validation (MD5 per
     * WiPay API spec), order/currency/transaction matching, replay
     * protection, and rate limiting.
     */
    public static function handle_return() {
        if (!function_exists('wc_get_order')) {
            wp_safe_redirect(home_url('/'));
            exit;
        }

        $order_id = isset($_GET['wc_order']) ? absint($_GET['wc_order']) : 0;
        $ts = isset($_GET['ts']) ? absint($_GET['ts']) : 0;
        $token = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';

        $order = $order_id ? wc_get_order($order_id) : false;
        if (!$order || 'rgy_wipay' !== $order->get_payment_method()) {
            wc_add_notice(__('Payment error. Order not found.', 'revamped-wipay-woocommerce'), 'error');
            wp_safe_redirect(wc_get_checkout_url());
            exit;
        }

        if (!self::rate_limit_ok($order_id)) {
            self::log_static('WiPay return rate limited. order=' . $order_id . ' ip=' . self::get_client_ip(), 'warning');
            wc_add_notice(__('Too many payment verification attempts. Please try again.', 'revamped-wipay-woocommerce'), 'error');
            wp_safe_redirect(wc_get_checkout_url());
            exit;
        }

        if (!self::validate_return_token($order, $ts, $token)) {
            self::log_static('WiPay return token validation failed. order=' . $order_id . ' ip=' . self::get_client_ip(), 'warning');
            $order->add_order_note(__('WiPay return token validation failed or expired.', 'revamped-wipay-woocommerce'));
            wc_add_notice(__('Payment verification failed. Please try again or contact the store owner.', 'revamped-wipay-woocommerce'), 'error');
            wp_safe_redirect(wc_get_checkout_url());
            exit;
        }

        $status = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : '';
        $message = isset($_GET['message']) ? sanitize_text_field(wp_unslash($_GET['message'])) : '';
        $transaction_id = isset($_GET['transaction_id']) ? sanitize_text_field(wp_unslash($_GET['transaction_id'])) : '';
        $hash = isset($_GET['hash']) ? sanitize_text_field(wp_unslash($_GET['hash'])) : '';
        $returned_total = isset($_GET['total']) ? sanitize_text_field(wp_unslash($_GET['total'])) : '';
        $returned_currency = isset($_GET['currency']) ? sanitize_text_field(wp_unslash($_GET['currency'])) : '';
        $returned_order_id = isset($_GET['order_id']) ? sanitize_text_field(wp_unslash($_GET['order_id'])) : '';

        $status_lower = strtolower($status);

        self::log_static(
            'WiPay return received. order=' . $order_id .
            ' status=' . $status_lower .
            ' tx=' . self::mask_id($transaction_id) .
            ' ip=' . self::get_client_ip(),
            'info'
        );

        // Replay protection.
        if ($order->is_paid()) {
            $paid_tx = (string) $order->get_meta(self::META_PAID_TRANSACTION_ID);
            if ($transaction_id && $paid_tx && hash_equals($paid_tx, $transaction_id)) {
                wp_safe_redirect($order->get_checkout_order_received_url());
                exit;
            }

            if ($transaction_id && $paid_tx && !hash_equals($paid_tx, $transaction_id)) {
                $order->add_order_note(sprintf(
                    /* translators: 1: transaction id */
                    __('WiPay return ignored because the order is already paid. Returned transaction ID: %1$s', 'revamped-wipay-woocommerce'),
                    $transaction_id
                ));

                self::log_static('WiPay replay blocked. order=' . $order_id . ' tx=' . self::mask_id($transaction_id) . ' paid_tx=' . self::mask_id($paid_tx), 'warning');
            }

            wp_safe_redirect($order->get_checkout_order_received_url());
            exit;
        }

        $stored_wipay_order_id = (string) $order->get_meta(self::META_WIPAY_ORDER_ID);
        if ($returned_order_id && $stored_wipay_order_id && $returned_order_id !== $stored_wipay_order_id) {
            $order->add_order_note(sprintf(
                /* translators: 1: returned order id, 2: expected order id */
                __('WiPay returned order_id %1$s but expected %2$s.', 'revamped-wipay-woocommerce'),
                $returned_order_id,
                $stored_wipay_order_id
            ));
            wc_add_notice(__('Payment verification failed. Please contact the store owner.', 'revamped-wipay-woocommerce'), 'error');
            wp_safe_redirect(wc_get_checkout_url());
            exit;
        }

        $expected_currency = (string) $order->get_meta(self::META_SENT_CURRENCY);
        if (!$expected_currency) {
            $expected_currency = (string) $order->get_currency();
        }

        if ($returned_currency && $returned_currency !== $expected_currency) {
            $order->add_order_note(sprintf(
                /* translators: 1: returned currency, 2: order currency */
                __('WiPay returned currency %1$s but order currency is %2$s.', 'revamped-wipay-woocommerce'),
                $returned_currency,
                $expected_currency
            ));
            wc_add_notice(__('Payment verification failed. Please contact the store owner.', 'revamped-wipay-woocommerce'), 'error');
            wp_safe_redirect(wc_get_checkout_url());
            exit;
        }

        $env = (string) $order->get_meta(self::META_ENVIRONMENT);
        $env = in_array($env, array('sandbox', 'live'), true) ? $env : 'sandbox';

        $original_total = (string) $order->get_meta(self::META_ORIGINAL_TOTAL);
        $requested_transaction_id = (string) $order->get_meta(self::META_REQUEST_TRANSACTION_ID);

        // Record return data for troubleshooting.
        $order->update_meta_data(self::META_TRANSACTION_ID, $transaction_id);
        $order->update_meta_data(self::META_CHARGED_TOTAL, $returned_total);
        $order->update_meta_data(self::META_RESPONSE_MESSAGE, $message);
        $order->save();

        if ('success' === $status_lower) {
            if (!$transaction_id) {
                $order->add_order_note(__('WiPay returned success but no transaction_id was provided.', 'revamped-wipay-woocommerce'));
                wc_add_notice(__('Payment verification failed. Please contact the store owner.', 'revamped-wipay-woocommerce'), 'error');
                wp_safe_redirect(wc_get_checkout_url());
                exit;
            }

            if ($requested_transaction_id && $transaction_id && $requested_transaction_id !== $transaction_id) {
                $order->add_order_note(sprintf(
                    /* translators: 1: returned transaction id, 2: expected transaction id */
                    __('WiPay returned transaction_id %1$s but expected %2$s.', 'revamped-wipay-woocommerce'),
                    $transaction_id,
                    $requested_transaction_id
                ));
                wc_add_notice(__('Payment verification failed. Please contact the store owner.', 'revamped-wipay-woocommerce'), 'error');
                wp_safe_redirect(wc_get_checkout_url());
                exit;
            }

            // Total sanity check: returned total should not be less than the original order total.
            $rt = is_numeric($returned_total) ? (float) $returned_total : null;
            $ot = is_numeric($original_total) ? (float) $original_total : null;
            if (null !== $rt && null !== $ot && ($rt + 0.009) < $ot) {
                $order->add_order_note(sprintf(
                    /* translators: 1: returned total, 2: original total */
                    __('WiPay returned total %1$s which is less than the original order total %2$s.', 'revamped-wipay-woocommerce'),
                    $returned_total,
                    $original_total
                ));
                wc_add_notice(__('Payment verification failed. Please contact the store owner.', 'revamped-wipay-woocommerce'), 'error');
                wp_safe_redirect(wc_get_checkout_url());
                exit;
            }

            // The hash is the only proof that WiPay produced this response, so a
            // success without a key or a hash is never accepted.
            $settings = self::get_settings_for_env($env);
            $api_key = isset($settings['api_key']) ? (string) $settings['api_key'] : '';

            if ('' === $api_key) {
                $order->add_order_note(__('WiPay returned success but no API key is saved for this environment, so the response could not be checked. The order was not marked paid.', 'revamped-wipay-woocommerce'));
                wc_add_notice(__('Payment verification failed. Please contact the store owner.', 'revamped-wipay-woocommerce'), 'error');
                wp_safe_redirect(wc_get_checkout_url());
                exit;
            }

            if (!$hash) {
                $order->update_status('failed', __('WiPay hash was missing on a successful response.', 'revamped-wipay-woocommerce'));
                wc_add_notice(__('Payment verification failed. Please contact the store owner.', 'revamped-wipay-woocommerce'), 'error');
                wp_safe_redirect(wc_get_checkout_url());
                exit;
            }

            // MD5 hash algorithm is specified by the WiPay API.
            $expected = md5($transaction_id . $original_total . $api_key);
            if (!hash_equals($expected, strtolower($hash))) {
                $order->update_status('failed', __('WiPay hash validation failed.', 'revamped-wipay-woocommerce'));
                wc_add_notice(__('Payment verification failed. Please contact the store owner.', 'revamped-wipay-woocommerce'), 'error');
                wp_safe_redirect(wc_get_checkout_url());
                exit;
            }

            // One WiPay transaction pays one order. The lock stops two returns
            // carrying the same transaction from completing two orders at once.
            $lock = self::acquire_lock('tx|' . $transaction_id);
            if (empty($lock)) {
                wc_add_notice(__('Your payment is being confirmed. Please wait a moment, then check your order.', 'revamped-wipay-woocommerce'), 'notice');
                wp_safe_redirect($order->get_checkout_order_received_url());
                exit;
            }

            // exit inside try would skip finally, so the redirect happens after
            // the lock is released.
            $redirect = '';
            try {
                $order = wc_get_order($order_id);
                if (!$order) {
                    $redirect = wc_get_checkout_url();
                } elseif ($order->is_paid()) {
                    $redirect = $order->get_checkout_order_received_url();
                } else {
                    $other_orders = self::orders_paid_with_transaction($transaction_id, $order_id);
                    if (!empty($other_orders)) {
                        $order->update_status('on-hold', sprintf(
                            /* translators: 1: transaction id, 2: order numbers */
                            __('WiPay transaction %1$s was already used to pay order(s) %2$s. This order was not marked paid. Check the WiPay dashboard.', 'revamped-wipay-woocommerce'),
                            $transaction_id,
                            '#' . implode(', #', $other_orders)
                        ));
                        self::log_static('WiPay transaction reuse blocked. order=' . $order_id . ' tx=' . self::mask_id($transaction_id), 'warning');
                        wc_add_notice(__('Payment verification failed. Please contact the store owner.', 'revamped-wipay-woocommerce'), 'error');
                        $redirect = wc_get_checkout_url();
                    } else {
                        $order->update_meta_data(self::META_PAID_TRANSACTION_ID, $transaction_id);
                        $order->save();
                        $order->payment_complete($transaction_id);

                        $note = sprintf(
                            /* translators: 1: transaction id, 2: message */
                            __('WiPay payment completed. Transaction ID: %1$s. Message: %2$s', 'revamped-wipay-woocommerce'),
                            $transaction_id,
                            $message ? $message : '-'
                        );
                        if ('sandbox' === $env) {
                            $note .= ' ' . __('This was a Sandbox test payment. No money was collected.', 'revamped-wipay-woocommerce');
                        }
                        $order->add_order_note($note);
                        $redirect = $order->get_checkout_order_received_url();
                    }
                }
            } finally {
                self::release_lock($lock);
            }

            wp_safe_redirect($redirect);
            exit;
        }

        $order->update_status('failed', $message
            ? sprintf(
                /* translators: %s: message returned by WiPay */
                __('WiPay payment failed: %s', 'revamped-wipay-woocommerce'),
                $message
            )
            : __('WiPay payment failed.', 'revamped-wipay-woocommerce'));
        // The message comes from the return URL, so it stays in the order note
        // and is not shown on the store's pages.
        wc_add_notice(__('Your card payment was not completed. Please try again or choose another payment method.', 'revamped-wipay-woocommerce'), 'error');
        wp_safe_redirect(wc_get_checkout_url());
        exit;
    }
}
