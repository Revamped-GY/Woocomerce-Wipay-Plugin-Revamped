=== Revamped WiPay Gateway for WooCommerce ===
Contributors: revampedgy
Tags: wipay, payments, credit card, woocommerce, checkout
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept card payments via WiPay by redirecting customers to WiPay's hosted payment page, then confirming payment back in WooCommerce.

== Description ==

Revamped WiPay Gateway for WooCommerce adds WiPay as a payment method in WooCommerce.

Your customer selects WiPay at checkout, is redirected to WiPay to enter card details, then returns to your site. WooCommerce confirms the payment and updates the order status.

Features:
* WiPay hosted payment redirect checkout flow
* Sandbox and Live environment support
* Fee structure options (merchant absorbs, customer pays, split)
* Optional AVS flag
* Optional card type selection to skip WiPay card type screen
* WooCommerce Blocks Checkout compatibility
* Optional debug logging in WooCommerce logs
* Self hosted updates via native WordPress updater using an Update JSON file

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install the ZIP via WordPress Plugins.
2. Activate the plugin.
3. Go to WooCommerce > Settings > Payments.
4. Enable "WiPay" and open its settings.
5. Set Environment to Sandbox for testing, then set it to Live for production.

== Frequently Asked Questions ==

= How do I enable self hosted updates? =
Upload the update JSON file to /updates/revamped-wipay-woocommerce.json on your site. The plugin uses the native WordPress updater and checks that JSON for new versions. When you release an update, upload the new ZIP then overwrite the JSON file with the new version, download_url and changelog.

= Where do I enter my WiPay credentials? =
Go to WooCommerce > Settings > Payments > WiPay. Enter your account number and API key for the selected environment.

= What is the response URL? =
The plugin sends a response_url in every payment request. You can also copy the base return URL from the plugin Overview page if WiPay asks for it.

= Why can a customer pay more than the WooCommerce order total? =
If fee structure is set to "Customer pays" or "Split", WiPay may add fees to the customer payment total. If you want the charged total to match the WooCommerce order total, set fee structure to "Merchant absorbs".

= Does this work with WooCommerce Blocks Checkout? =
Yes. The plugin registers a Blocks payment method so it can be used in the block based Cart and Checkout.

= Where are logs stored? =
WooCommerce > Status > Logs. Look for the log source "rgy-wipay" if debug logging is enabled.

== Screenshots ==

1. WooCommerce payment gateway settings
2. Plugin Overview page
3. Help page with setup checklist

== Changelog ==

= 1.2.0 =
* Fixed billing prefill field names to match WiPay API (fname, lname, addr1, addr2, zipcode)
* Fixed Overview page reading wrong settings keys for country and account number
* Fixed exchange rate status card comparison that broke on translated sites
* Fixed USD rounding: store-currency USD orders are no longer rounded up unnecessarily
* Fixed order_id length to stay within WiPay 16-character limit for all order sizes
* Fixed Classic Checkout icon to pass through the woocommerce_gateway_icon filter
* Fixed double order save in process_payment by using set_status then a single save
* Fixed admin page indentation and capability check
* Added API key requirement for live mode so hash validation cannot be skipped
* Added currency validation: unsupported store currencies are blocked at checkout with a clear message
* Added exchange rate fallback: last known good rate is used when the FX API is unavailable
* Added plugin version and optional data parameter to WiPay requests
* Added refund support declaration with a helpful message directing admins to the WiPay dashboard
* Added uninstall handler to clean up settings and transients when the plugin is deleted
* Added daily cron to auto-cancel stale pending WiPay orders after 4 hours
* Added currency and API key warnings on the Overview page
* Added refund and GY country code guidance to the Help page
* Added wp-html-entities support and redirect notice to Blocks checkout
* Added translatable copy button text on admin pages
* Added rgy-notice-info CSS class for admin notices
* Increased return token TTL from 2 hours to 4 hours to accommodate slow 3DS flows
* Removed dead code branch in mask_sensitive

= 1.1.7 =
* I fixed a parse error in the Overview and Help admin pages caused by duplicated content outside the class methods
* I aligned the internal version constant with the plugin version


= 1.1.6 =
* Added improved Overview and Help pages with status cards, quick links and currency conversion guidance.


= 1.1.5 =
* I added an option to charge in USD. The plugin converts the store currency to USD using a live exchange rate then rounds up to the next whole USD before redirecting to WiPay
* I stored the sent currency, original store currency, exchange rate and fetch time on the order to help troubleshooting


= 1.1.4 =
* I fixed a fatal error caused by an invalid WooCommerce FeaturesUtil reference in the main plugin loader
* I removed the duplicate Blocks compatibility declaration and kept Blocks compatibility declared only inside the Blocks integration class

= 1.1.3 =
* I fixed fatal errors caused by malformed admin and updater class files
* I kept only the native WordPress updater flow using the Update JSON URL
* I declared WooCommerce Blocks Checkout compatibility at the correct time

= 1.1.2 =
* I switched the updater to use a fixed Update JSON URL so updates work without an Updates admin page

= 1.1.1 =
* I added a neat "View details" link on the Plugins page that opens the WordPress plugin details popup with the description and changelog

= 1.1.0 =
* I added a self hosted updater that reads a JSON metadata file and shows updates inside WordPress
* I added an Updates admin page so I can set the JSON URL, force an update check and confirm the remote version

= 1.0.5 =
* I added stronger return validation including order ID and currency matching, transaction ID matching and stricter hash checks
* I added replay protection so completed orders cannot be reprocessed with a different transaction ID
* I added a signed return token with expiry to reduce return URL tampering and random endpoint probing
* I added lightweight rate limiting on the return endpoint and improved sanitised logging

= 1.0.4 =
* I fixed WooCommerce Blocks Checkout registration timing so the gateway no longer shows as incompatible on block based checkout
* I switched the Blocks checkout icon rendering to use the native icon slot so the logo stays small beside the payment method label
* I updated the default logo URL to the new WiPay transparent logo

= 1.0.3 =
* I fixed block based Cart and Checkout support by adding the required Blocks integration class and frontend registration script
* I adjusted the WiPay logo to display as a small icon next to the payment method label, similar to other gateways

= 1.0.2 =
* I improved WooCommerce Blocks Checkout support by loading the Blocks integration only when Blocks is available
* I added the official WiPay logo icon so it appears next to the payment method in both Classic and Blocks checkout

= 1.0.1 =
* I improved debug logs by recording a sanitised version of the WiPay request payload to help troubleshooting
* I added a Help section with sandbox test card guidance to reduce failed test attempts

= 1.0.0 =
* I released the first version with the WiPay hosted checkout redirect flow
* I added sandbox and live settings, fee structure options, optional AVS and card type selection
* I added a branded Overview and Help page with copy buttons and setup guidance
* I added WooCommerce Blocks Checkout compatibility

