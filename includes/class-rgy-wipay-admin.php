<?php
/**
 * Admin pages
 *
 * @package Revamped_WiPay_WooCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

final class RGY_WiPay_Admin {

    private static $instance = null;

    /**
     * Singleton.
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'register_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    /**
     * Register menu only. Do not render UI here.
     */
    public function register_menu() {
        $cap = 'manage_woocommerce';

        add_menu_page(
            __('WiPay Gateway', 'revamped-wipay-woocommerce'),
            __('WiPay Gateway', 'revamped-wipay-woocommerce'),
            $cap,
            'rgy-wipay',
            array($this, 'render_overview'),
            'dashicons-admin-generic',
            56
        );

        add_submenu_page(
            'rgy-wipay',
            __('Overview', 'revamped-wipay-woocommerce'),
            __('Overview', 'revamped-wipay-woocommerce'),
            $cap,
            'rgy-wipay',
            array($this, 'render_overview')
        );

        add_submenu_page(
            'rgy-wipay',
            __('Help', 'revamped-wipay-woocommerce'),
            __('Help', 'revamped-wipay-woocommerce'),
            $cap,
            'rgy-wipay-help',
            array($this, 'render_help')
        );
    }

    /**
     * Only load assets on our pages.
     */
    public function enqueue_assets($hook) {
        if (empty($_GET['page'])) {
            return;
        }

        $page = sanitize_text_field(wp_unslash($_GET['page']));
        if (0 !== strpos($page, 'rgy-wipay')) {
            return;
        }

        wp_enqueue_style(
            'rgy-wipay-admin',
            RGY_WIPAY_WC_URL . 'assets/admin/admin.css',
            array(),
            defined('RGY_WIPAY_WC_VERSION') ? RGY_WIPAY_WC_VERSION : '1.0.0'
        );

        wp_enqueue_script(
            'rgy-wipay-admin',
            RGY_WIPAY_WC_URL . 'assets/admin/admin.js',
            array(),
            defined('RGY_WIPAY_WC_VERSION') ? RGY_WIPAY_WC_VERSION : '1.0.0',
            true
        );

        wp_localize_script('rgy-wipay-admin', 'rgyWipayAdmin', array(
            'copied' => __('Copied', 'revamped-wipay-woocommerce'),
            'copy'   => __('Copy', 'revamped-wipay-woocommerce'),
        ));
    }

    private function header($title) {
        $logo = 'https://revamped.gy/revamped-uploads/2025/12/Revamped-GY-Landscape-White-Logo.webp';
        $brand_url = 'https://revamped.gy';

        echo '<div class="rgy-wrap">';
        echo '<div class="rgy-header">';
        echo '<a class="rgy-brand" href="' . esc_url($brand_url) . '" target="_blank" rel="noopener noreferrer">';
        echo '<img src="' . esc_url($logo) . '" alt="' . esc_attr__('Revamped GY', 'revamped-wipay-woocommerce') . '">';
        echo '</a>';
        echo '<div class="rgy-header-title"><h1>' . esc_html($title) . '</h1></div>';
        echo '</div>';
        echo '<div class="rgy-content">';
    }

    private function footer() {
        echo '</div></div>';
    }

    
    private function status_card($label, $value, $status = 'ok', $icon = '', $meta = '') {
        $status = in_array($status, array('ok', 'warn', 'err'), true) ? $status : 'ok';
        $icon = (string) $icon;
        $meta = (string) $meta;

        echo '<div class="rgy-status-card rgy-status-' . esc_attr($status) . '">';
        echo '<div class="rgy-status-icon" aria-hidden="true">' . esc_html($icon) . '</div>';
        echo '<div class="rgy-status-body">';
        echo '<div class="rgy-status-label">' . esc_html($label) . '</div>';
        echo '<div class="rgy-status-value">' . esc_html($value) . '</div>';
        if ($meta) {
            echo '<div class="rgy-status-meta">' . esc_html($meta) . '</div>';
        }
        echo '</div>';
        echo '</div>';
    }

public function render_overview() {

        if (!current_user_can('manage_woocommerce') && !current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'revamped-wipay-woocommerce'));
        }

        $this->header(__('WiPay Gateway Overview', 'revamped-wipay-woocommerce'));

        $settings = get_option('woocommerce_rgy_wipay_settings', array());
        if (!is_array($settings)) {
            $settings = array();
        }

        $enabled     = !empty($settings['enabled']) && 'yes' === $settings['enabled'];
        $environment = !empty($settings['environment']) ? sanitize_text_field((string) $settings['environment']) : 'sandbox';
        $country     = !empty($settings['country']) ? strtoupper(sanitize_text_field((string) $settings['country'])) : 'GY';
        $fee_mode    = !empty($settings['fee_structure']) ? sanitize_text_field((string) $settings['fee_structure']) : 'merchant_absorb';

        // Read the correct account number key based on environment.
        $account_key = ('live' === $environment) ? 'account_number_live' : 'account_number_sandbox';
        $account     = !empty($settings[$account_key]) ? sanitize_text_field((string) $settings[$account_key]) : '';

        $charge_currency = !empty($settings['charge_currency']) ? sanitize_text_field((string) $settings['charge_currency']) : 'store';
        $charge_currency = in_array($charge_currency, array('store', 'usd'), true) ? $charge_currency : 'store';

        $fx_cache_minutes = !empty($settings['fx_cache_minutes']) ? absint($settings['fx_cache_minutes']) : 60;
        if ($fx_cache_minutes < 5) { $fx_cache_minutes = 5; }

        $wc_active   = class_exists('WooCommerce');
        $store_cur   = function_exists('get_woocommerce_currency') ? (string) get_woocommerce_currency() : '';
        $store_cur   = strtoupper(sanitize_text_field($store_cur));
        $is_sandbox  = ('sandbox' === $environment);

        $masked_account = $account ? (str_repeat('•', max(0, strlen($account) - 4)) . substr($account, -4)) : '';

        $return_base = home_url('/wc-api/rgy_wipay_return');
        $wc_gateway_settings_url = admin_url('admin.php?page=wc-settings&tab=checkout&section=rgy_wipay');
        $wc_payments_list_url    = admin_url('admin.php?page=wc-settings&tab=checkout');
        $wc_logs_url             = admin_url('admin.php?page=wc-status&tab=logs');

        $blocks_available = class_exists('\Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType');
        $blocks_status = $blocks_available ? __('Available', 'revamped-wipay-woocommerce') : __('Not detected', 'revamped-wipay-woocommerce');

        // Use internal keys for comparisons so translations don't break logic.
        $rate_status_key = 'not_applicable';
        $rate_status = __('Not applicable', 'revamped-wipay-woocommerce');
        $rate_meta   = '';
        if ('usd' === $charge_currency && $store_cur && 'USD' !== $store_cur) {
            $cache_key = 'rgy_wipay_fx_' . strtolower($store_cur) . '_usd';
            $cached = get_transient($cache_key);
            if (is_array($cached) && !empty($cached['rate']) && is_numeric($cached['rate'])) {
                $rate_status_key = 'cached';
                $rate_status = __('Cached', 'revamped-wipay-woocommerce');
                $fetched_at = !empty($cached['fetched_at']) ? absint($cached['fetched_at']) : 0;
                if ($fetched_at) {
                    $rate_meta = sprintf(
                        /* translators: 1: rate 2: time */
                        __('Rate: %1$s. Fetched: %2$s', 'revamped-wipay-woocommerce'),
                        esc_html(number_format((float) $cached['rate'], 6, '.', '')),
                        esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $fetched_at))
                    );
                }
            } else {
                $rate_status_key = 'not_cached';
                $rate_status = __('Not cached yet', 'revamped-wipay-woocommerce');
                $rate_meta = sprintf(
                    /* translators: 1: minutes */
                    __('Will fetch on the next WiPay checkout, then cache for about %1$s minutes.', 'revamped-wipay-woocommerce'),
                    esc_html((string) $fx_cache_minutes)
                );
            }
        }

        // Currency warning when store currency is unsupported and charge mode is store.
        $wipay_currencies = class_exists('RGY_WiPay_Gateway') ? RGY_WiPay_Gateway::supported_currencies() : array('JMD', 'TTD', 'USD');
        if ($store_cur && 'store' === $charge_currency && !in_array($store_cur, $wipay_currencies, true)) {
            echo '<div class="rgy-notice rgy-notice-error">';
            echo '<strong>' . esc_html__('Currency warning:', 'revamped-wipay-woocommerce') . '</strong> ';
            echo esc_html(sprintf(
                /* translators: 1: currency code, 2: supported currency codes */
                __('Your store currency (%1$s) is not one WiPay can charge (%2$s). WiPay is hidden at checkout until you set Charge currency to USD in the gateway settings.', 'revamped-wipay-woocommerce'),
                $store_cur,
                implode(', ', $wipay_currencies)
            ));
            echo '</div>';
        }

        if ($is_sandbox) {
            echo '<div class="rgy-notice rgy-notice-warning">';
            echo '<strong>' . esc_html__('Sandbox mode is active.', 'revamped-wipay-woocommerce') . '</strong> ';
            echo esc_html__('No real payments will be processed. Switch to Live before going live.', 'revamped-wipay-woocommerce');
            echo '</div>';
        }

        // Warn if live mode but API key is missing.
        if ('live' === $environment) {
            $api_key_live = !empty($settings['api_key_live']) ? (string) $settings['api_key_live'] : '';
            if ('' === $api_key_live) {
                echo '<div class="rgy-notice rgy-notice-error">';
                echo '<strong>' . esc_html__('API key missing:', 'revamped-wipay-woocommerce') . '</strong> ';
                echo esc_html__('Live mode requires an API key for hash validation. The gateway will not appear at checkout until a live API key is saved.', 'revamped-wipay-woocommerce');
                echo '</div>';
            }
        }

        echo '<div class="rgy-status-grid">';

        $this->status_card(
            __('Gateway', 'revamped-wipay-woocommerce'),
            $enabled ? __('Enabled', 'revamped-wipay-woocommerce') : __('Disabled', 'revamped-wipay-woocommerce'),
            $enabled ? 'ok' : 'warn',
            '🔌'
        );

        $this->status_card(
            __('Environment', 'revamped-wipay-woocommerce'),
            $is_sandbox ? __('Sandbox', 'revamped-wipay-woocommerce') : __('Live', 'revamped-wipay-woocommerce'),
            $is_sandbox ? 'warn' : 'ok',
            '🧪'
        );

        $this->status_card(
            __('Country', 'revamped-wipay-woocommerce'),
            $country,
            'ok',
            '🌍'
        );

        $this->status_card(
            __('Store currency', 'revamped-wipay-woocommerce'),
            $store_cur ? $store_cur : __('Unknown', 'revamped-wipay-woocommerce'),
            $store_cur ? 'ok' : 'warn',
            '💱'
        );

        $this->status_card(
            __('Charge currency', 'revamped-wipay-woocommerce'),
            ('usd' === $charge_currency) ? 'USD' : ($store_cur ? $store_cur : __('Store', 'revamped-wipay-woocommerce')),
            'ok',
            '💳'
        );

        $this->status_card(
            __('Exchange rate cache', 'revamped-wipay-woocommerce'),
            $rate_status,
            ('cached' === $rate_status_key) ? 'ok' : (('not_applicable' === $rate_status_key) ? 'ok' : 'warn'),
            '📡',
            $rate_meta
        );

        $this->status_card(
            __('Blocks checkout', 'revamped-wipay-woocommerce'),
            $blocks_status,
            $blocks_available ? 'ok' : 'warn',
            '🧩'
        );

        $this->status_card(
            __('Account', 'revamped-wipay-woocommerce'),
            $masked_account ? $masked_account : __('Not set', 'revamped-wipay-woocommerce'),
            $masked_account ? 'ok' : 'warn',
            '🏦'
        );

        echo '</div>';

        echo '<div class="rgy-grid">';

        echo '<div class="rgy-card">';
        echo '<h2>' . esc_html__('What this does', 'revamped-wipay-woocommerce') . '</h2>';
        echo '<p>' . esc_html__('This plugin adds WiPay as a WooCommerce payment method. Customers are redirected to WiPay to enter card details, then return to your site where the order is updated.', 'revamped-wipay-woocommerce') . '</p>';

        echo '<h2 class="rgy-section-title">' . esc_html__('Quick setup steps', 'revamped-wipay-woocommerce') . '</h2>';
        echo '<ol class="rgy-steps rgy-steps-boxed">';
        echo '<li class="' . ($wc_active ? 'is-done' : 'is-todo') . '"><span class="rgy-step-num">1</span><div><strong>' . esc_html__('WooCommerce active', 'revamped-wipay-woocommerce') . '</strong><br><small>' . esc_html($wc_active ? __('WooCommerce is active.', 'revamped-wipay-woocommerce') : __('WooCommerce not detected.', 'revamped-wipay-woocommerce')) . '</small></div></li>';
        echo '<li class="' . (!empty($account) ? 'is-done' : 'is-todo') . '"><span class="rgy-step-num">2</span><div><strong>' . esc_html__('Enter your WiPay details', 'revamped-wipay-woocommerce') . '</strong><br><small>' . esc_html__('Set account number and API key in the WiPay gateway settings.', 'revamped-wipay-woocommerce') . '</small></div></li>';
        echo '<li><span class="rgy-step-num">3</span><div><strong>' . esc_html__('Test end-to-end', 'revamped-wipay-woocommerce') . '</strong><br><small>' . esc_html__('Place a test order, complete payment on WiPay, then confirm the order updates in WooCommerce.', 'revamped-wipay-woocommerce') . '</small></div></li>';
        echo '</ol>';

        echo '<div class="rgy-actions">';
        echo '<a class="button rgy-btn rgy-btn-primary" href="' . esc_url($wc_gateway_settings_url) . '">' . esc_html__('Open WiPay settings', 'revamped-wipay-woocommerce') . '</a>';
        echo '<a class="button rgy-btn rgy-btn-secondary" href="' . esc_url(admin_url('admin.php?page=rgy-wipay-help')) . '">' . esc_html__('Open Help', 'revamped-wipay-woocommerce') . '</a>';
        echo '</div>';

        echo '</div>';

        echo '<div class="rgy-card">';
        echo '<h2>' . esc_html__('Return URL', 'revamped-wipay-woocommerce') . '</h2>';
        echo '<p>' . esc_html__('The plugin automatically sends a secure response URL to WiPay for each order. If WiPay asks for a base return URL, copy this:', 'revamped-wipay-woocommerce') . '</p>';

        echo '<div class="rgy-copy-row">';
        echo '<input type="text" readonly value="' . esc_attr($return_base) . '">';
        echo '<button class="button rgy-btn rgy-btn-secondary rgy-copy-btn" data-copy="' . esc_attr($return_base) . '">' . esc_html__('Copy', 'revamped-wipay-woocommerce') . '</button>';
        echo '</div>';

        echo '<h2 class="rgy-section-title">' . esc_html__('Recommended next actions', 'revamped-wipay-woocommerce') . '</h2>';
        echo '<ul class="rgy-bullets">';
        echo '<li>' . esc_html__('Use Sandbox first, then switch to Live after a successful end-to-end test.', 'revamped-wipay-woocommerce') . '</li>';
        echo '<li>' . esc_html__('If WiPay shows a currency unsupported message, set Charge currency to USD in the gateway settings.', 'revamped-wipay-woocommerce') . '</li>';
        echo '<li>' . esc_html__('If you want WooCommerce totals to match what the customer pays, use fee structure “Merchant absorbs”.', 'revamped-wipay-woocommerce') . '</li>';
        echo '<li>' . esc_html__('Enable debug logs only while testing, then disable for production.', 'revamped-wipay-woocommerce') . '</li>';
        echo '</ul>';

        echo '<div class="rgy-actions">';
        echo '<a class="button rgy-btn rgy-btn-secondary" href="' . esc_url($wc_payments_list_url) . '">' . esc_html__('Open Payments list', 'revamped-wipay-woocommerce') . '</a>';
        echo '<a class="button rgy-btn rgy-btn-secondary" href="' . esc_url($wc_logs_url) . '">' . esc_html__('Open WooCommerce logs', 'revamped-wipay-woocommerce') . '</a>';
        echo '</div>';

        echo '</div>';

        echo '</div>';

        $this->footer();
    }

    public function render_help() {

        if (!current_user_can('manage_woocommerce') && !current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'revamped-wipay-woocommerce'));
        }

        $this->header(__('WiPay Gateway Help', 'revamped-wipay-woocommerce'));

        $settings = get_option('woocommerce_rgy_wipay_settings', array());
        if (!is_array($settings)) {
            $settings = array();
        }

        $environment = !empty($settings['environment']) ? sanitize_text_field((string) $settings['environment']) : 'sandbox';
        $is_sandbox  = ('sandbox' === $environment);

        $charge_currency = !empty($settings['charge_currency']) ? sanitize_text_field((string) $settings['charge_currency']) : 'store';
        $charge_currency = in_array($charge_currency, array('store', 'usd'), true) ? $charge_currency : 'store';

        echo '<div class="rgy-grid">';

        echo '<div class="rgy-card">';
        echo '<h2>' . esc_html__('Step-by-step setup flow', 'revamped-wipay-woocommerce') . '</h2>';
        echo '<ol class="rgy-steps rgy-steps-boxed">';
        echo '<li><span class="rgy-step-num">1</span><div><strong>' . esc_html__('Get your WiPay credentials', 'revamped-wipay-woocommerce') . '</strong><br><small>' . esc_html__('From your WiPay Business dashboard, copy your Account Number and API Key for the environment you will use.', 'revamped-wipay-woocommerce') . '</small></div></li>';
        echo '<li><span class="rgy-step-num">2</span><div><strong>' . esc_html__('Enable WiPay in WooCommerce', 'revamped-wipay-woocommerce') . '</strong><br><small>' . esc_html__('Go to WooCommerce > Settings > Payments, enable WiPay, then open the gateway settings section.', 'revamped-wipay-woocommerce') . '</small></div></li>';
        echo '<li><span class="rgy-step-num">3</span><div><strong>' . esc_html__('Set currency behaviour', 'revamped-wipay-woocommerce') . '</strong><br><small>' . esc_html__('If your store currency is not supported by WiPay, set Charge currency to USD. The plugin converts using a live FX rate and rounds up to the next whole USD at checkout.', 'revamped-wipay-woocommerce') . '</small></div></li>';
        echo '<li><span class="rgy-step-num">4</span><div><strong>' . esc_html__('Choose your fee structure', 'revamped-wipay-woocommerce') . '</strong><br><small>' . esc_html__('Merchant absorbs is recommended if you want WooCommerce totals to match what the customer pays.', 'revamped-wipay-woocommerce') . '</small></div></li>';
        echo '<li><span class="rgy-step-num">5</span><div><strong>' . esc_html__('Run a sandbox test, then go live', 'revamped-wipay-woocommerce') . '</strong><br><small>' . esc_html__('Confirm the customer can pay on WiPay and the order updates on your site. Then switch to Live and repeat a real test.', 'revamped-wipay-woocommerce') . '</small></div></li>';
        echo '</ol>';

        echo '<div class="rgy-actions">';
        echo '<a class="button rgy-btn rgy-btn-primary" href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=rgy_wipay')) . '">' . esc_html__('Open WiPay settings', 'revamped-wipay-woocommerce') . '</a>';
        echo '</div>';

        echo '</div>';

        echo '<div class="rgy-card">';
        echo '<h2>' . esc_html__('Sandbox test card details', 'revamped-wipay-woocommerce') . '</h2>';
        echo '<p>' . esc_html__('Use a test card that matches the card type selected on the WiPay page.', 'revamped-wipay-woocommerce') . '</p>';
        echo '<div class="rgy-kv-row">';
        echo '<div class="rgy-kv"><div class="rgy-kv-k">' . esc_html__('Visa', 'revamped-wipay-woocommerce') . '</div><div class="rgy-kv-v">4111 1111 1111 1111</div></div>';
        echo '<div class="rgy-kv"><div class="rgy-kv-k">' . esc_html__('Mastercard', 'revamped-wipay-woocommerce') . '</div><div class="rgy-kv-v">5111 1111 1111 1111</div></div>';
        echo '</div>';
        echo '<p><small>' . esc_html__('Expiry and CVV values are typically accepted in sandbox. If you see an internal error, retry with a matching card type.', 'revamped-wipay-woocommerce') . '</small></p>';

        if ($is_sandbox) {
            echo '<div class="rgy-notice rgy-notice-warning">';
            echo '<strong>' . esc_html__('Sandbox mode is currently enabled in your gateway settings.', 'revamped-wipay-woocommerce') . '</strong>';
            echo '</div>';
        }

        echo '</div>';

        echo '</div>';

        echo '<div class="rgy-grid">';

        echo '<div class="rgy-card">';
        echo '<h2>' . esc_html__('Common issues and fixes', 'revamped-wipay-woocommerce') . '</h2>';
        echo '<ul class="rgy-bullets">';
        echo '<li>' . esc_html__('Currency not supported (for example GYD): set Charge currency to USD in the gateway settings.', 'revamped-wipay-woocommerce') . '</li>';
        echo '<li>' . esc_html__('Order stays Pending: confirm the customer returned to your site after payment. Check WooCommerce logs for WiPay validation messages.', 'revamped-wipay-woocommerce') . '</li>';
        echo '<li>' . esc_html__('Hash validation failed: confirm you are using the correct API key for the selected environment.', 'revamped-wipay-woocommerce') . '</li>';
        echo '<li>' . esc_html__('Blocks checkout shows incompatible: update to the latest plugin version and ensure WiPay is enabled in WooCommerce payments.', 'revamped-wipay-woocommerce') . '</li>';
        echo '<li>' . esc_html__('Refunds: WiPay does not support automatic refunds through the API. Process refunds through your WiPay Business dashboard, then record the refund manually in WooCommerce.', 'revamped-wipay-woocommerce') . '</li>';
        echo '<li>' . esc_html__('GY country code: the Guyana endpoint is supported by WiPay but may not appear in older WiPay API documentation.', 'revamped-wipay-woocommerce') . '</li>';
        echo '</ul>';
        echo '</div>';

        echo '<div class="rgy-card">';
        echo '<h2>' . esc_html__('Go live checklist', 'revamped-wipay-woocommerce') . '</h2>';
        echo '<ul class="rgy-checklist">';
        echo '<li>' . esc_html__('Site is on HTTPS and checkout pages do not redirect to HTTP.', 'revamped-wipay-woocommerce') . '</li>';
        echo '<li>' . esc_html__('Live account number and live API key are saved.', 'revamped-wipay-woocommerce') . '</li>';
        echo '<li>' . esc_html__('At least one successful live transaction test was completed end-to-end.', 'revamped-wipay-woocommerce') . '</li>';
        echo '<li>' . esc_html__('Debug logging is disabled unless you are troubleshooting.', 'revamped-wipay-woocommerce') . '</li>';
        echo '</ul>';
        echo '</div>';

        echo '</div>';

        echo '<div class="rgy-card">';
        echo '<h2>' . esc_html__('Support', 'revamped-wipay-woocommerce') . '</h2>';
        echo '<p>' . esc_html__('If you need help, include the order number, timestamp and the WiPay transaction ID from the order notes or logs.', 'revamped-wipay-woocommerce') . '</p>';

        echo '<div class="rgy-help-footer">';
        echo '<div>';
        echo wp_kses_post('Built, powered and updated by <a href="https://revamped.gy" target="_blank" rel="noopener noreferrer">Revamped GY</a>');
        echo '<span class="rgy-sep">|</span>';
        echo wp_kses_post('<a href="mailto:contact@revamped.gy">contact@revamped.gy</a>');
        echo '<span class="rgy-sep">|</span>';
        echo wp_kses_post('<a href="https://wa.me/5927203747" target="_blank" rel="noopener noreferrer">WhatsApp: +592-720-3747</a>');
        echo '</div>';
        echo '</div>';

        echo '</div>';

        $this->footer();
    }
}
