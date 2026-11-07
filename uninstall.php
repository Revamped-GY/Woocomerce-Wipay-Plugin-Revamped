<?php
/**
 * Uninstall handler.
 *
 * Cleans up plugin settings and transients when the plugin is deleted.
 * Order metadata is intentionally preserved for bookkeeping.
 *
 * @package Revamped_WiPay_WooCommerce
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Gateway settings.
delete_option('woocommerce_rgy_wipay_settings');

// Update cache, including the one used before 1.3.0.
delete_transient('rgy_wipay_update_payload');
delete_site_transient('rgy_wipay_update_info_v2');
delete_site_transient('rgy_wipay_update_check_failed');

// Clean up FX rate transients, fallback options, and rate limit transients.
global $wpdb;
$wpdb->query(
    "DELETE FROM {$wpdb->options}
     WHERE option_name LIKE '_transient_rgy_wipay_fx_%'
        OR option_name LIKE '_transient_timeout_rgy_wipay_fx_%'
        OR option_name LIKE '_transient_rgy_wipay_rl_%'
        OR option_name LIKE '_transient_timeout_rgy_wipay_rl_%'
        OR option_name LIKE 'rgy_wipay_fx_fallback_%'
        OR option_name LIKE 'rgy_wipay_lock_%'"
);

// Remove scheduled cron event.
wp_clear_scheduled_hook('rgy_wipay_cleanup_pending_orders');
