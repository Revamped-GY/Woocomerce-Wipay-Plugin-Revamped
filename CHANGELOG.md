# Changelog

All notable changes to this plugin are documented in this file. Versions follow semantic versioning.

## [1.3.0] - 2026-11-07

### Security
- A successful WiPay return now always needs a valid hash. Before, a return was accepted without one when no API key was saved for the environment.
- One WiPay transaction can pay only one order. A transaction already recorded on another order puts the order on hold with a note instead of marking it paid, and a short database lock stops two returns completing at the same time.
- The return handler only accepts orders that use WiPay.
- The failure message in the return URL is no longer shown on the store. It is kept in the order note and customers see a fixed message.
- Updates are now downloaded and checked against the SHA-256 value published with each release before WordPress installs them. A package with a missing or wrong checksum is refused.
- Added the `Update URI` header so WordPress never offers a wordpress.org plugin with the same slug as an update.
- The API base URL override and the payment page URL returned by WiPay must use HTTPS.

### Fixed
- Updates did not work for copies installed from GitHub. The updater pointed at a repository that does not exist and the 1.2.0 release had no ZIP attached. It now reads releases from `Revamped-GY/Woocomerce-Wipay-Plugin-Revamped` and falls back to the update file on revamped.gy.
- A failed update check was repeated on every admin page load. It is now remembered for an hour.
- An update no longer moves the plugin into a different folder, which could leave it deactivated.
- The stale pending order clean-up was only scheduled on activation, so sites that updated to 1.2.0 never ran it. It is scheduled on load when missing, and it compares order times to the second.
- The Plugin URI pointed at a page that does not exist.

### Changed
- WiPay is hidden at checkout when the store currency cannot be charged and Charge currency is set to the store currency. The Overview page explains how to switch to USD.
- Removed the WooCommerce refund button, which always returned an error. Refund in the WiPay dashboard, then use Refund manually in WooCommerce.
- A stored exchange rate older than seven days is no longer used when the live rate cannot be fetched. Change this with the `rgy_wipay_fx_fallback_max_age` filter.
- Sandbox payments add a note to the order saying no money was collected.
- The Plugins screen uses WordPress's own View details link and auto-update toggle.
- Tested with WordPress 7.1 and WooCommerce 11.2.

## [1.2.0] - 2026-04-15

### Added
- API key requirement for Live mode so hash validation cannot be skipped at the gateway level.
- Currency validation that blocks unsupported store currencies at checkout with a clear message.
- Exchange rate fallback that uses the last known good rate when the live FX API is unavailable.
- Plugin version sent on every WiPay request to help with support and troubleshooting.
- Optional `data` parameter support via the `rgy_wipay_request_data` filter.
- Refund support with a helpful message directing admins to the WiPay dashboard.
- Uninstall handler that cleans up settings and transients when the plugin is deleted.
- Daily cron that auto-cancels stale pending WiPay orders after 4 hours.
- Currency and API key warnings on the Overview page.
- Refund and GY country code guidance on the Help page.
- `wp-html-entities` dependency and a redirect notice in the Blocks Checkout payment method.
- Translatable copy button text on the admin pages.
- `rgy-notice-info` CSS class for admin notices.
- GitHub Releases as the new update source. The plugin now checks GitHub for new versions.

### Changed
- Billing prefill field names now match the WiPay API. Sends `fname`, `lname`, `addr1`, `addr2` and `zipcode` instead of WooCommerce style names that WiPay was ignoring.
- Overview page now reads the correct settings keys for country and account number.
- Exchange rate status comparison no longer uses translated strings, so the status colours are correct on translated sites.
- USD store currencies are no longer rounded up unnecessarily. Rounding only applies when an actual conversion happens.
- Order ID sent to WiPay is now capped at 16 characters so it works for all WiPay backends including FGB in Jamaica.
- Classic Checkout icon now passes through the `woocommerce_gateway_icon` filter so themes can adjust it.
- Process payment uses `set_status` plus a single `save` instead of saving twice.
- Return token TTL increased from 2 hours to 4 hours to handle slow 3DS flows.

### Fixed
- Removed a dead code branch in the sensitive value masking helper.

### Security
- Live mode now refuses to appear at checkout when the API key is empty. Without an API key the hash validation step would be skipped, which weakened the return verification chain. The gateway is now hidden until an API key is saved.

## [1.1.7] - 2026-03-01

### Fixed
- Fixed a parse error in the Overview and Help admin pages caused by duplicated content outside the class methods.
- Aligned the internal version constant with the plugin version.

## [1.1.6] - 2026-02-15

### Added
- Improved Overview and Help pages with status cards, quick links and currency conversion guidance.

## [1.1.5] - 2026-02-01

### Added
- Option to charge in USD. The plugin converts the store currency to USD using a live exchange rate, then rounds up to the next whole USD before redirecting to WiPay.
- Sent currency, original store currency, exchange rate and fetch time stored on the order to help with troubleshooting.

## [1.1.4] - 2026-01-15

### Fixed
- Fixed a fatal error caused by an invalid WooCommerce FeaturesUtil reference in the main plugin loader.
- Removed the duplicate Blocks compatibility declaration. Blocks compatibility is now declared only inside the Blocks integration class.

## [1.1.3] - 2026-01-05

### Fixed
- Fixed fatal errors caused by malformed admin and updater class files.

### Changed
- Kept only the native WordPress updater flow using the Update JSON URL.
- Declared WooCommerce Blocks Checkout compatibility at the correct time.

## [1.1.2] - 2025-12-20

### Changed
- Switched the updater to use a fixed Update JSON URL so updates work without an Updates admin page.

## [1.1.1] - 2025-12-10

### Added
- View details link on the Plugins page that opens the WordPress plugin details popup with the description and changelog.

## [1.1.0] - 2025-12-01

### Added
- Self hosted updater that reads a JSON metadata file and shows updates inside WordPress.
- Updates admin page for setting the JSON URL, forcing an update check and confirming the remote version.

## [1.0.5] - 2025-11-15

### Added
- Stronger return validation including order ID matching, currency matching, transaction ID matching and stricter hash checks.
- Replay protection so completed orders cannot be reprocessed with a different transaction ID.
- Signed return token with expiry to reduce return URL tampering and random endpoint probing.
- Lightweight rate limiting on the return endpoint.

### Changed
- Improved sanitised logging.

## [1.0.4] - 2025-11-01

### Fixed
- Fixed WooCommerce Blocks Checkout registration timing so the gateway no longer shows as incompatible on block based checkout.

### Changed
- Switched the Blocks checkout icon rendering to use the native icon slot so the logo stays small beside the payment method label.
- Updated the default logo URL to the new WiPay transparent logo.

## [1.0.3] - 2025-10-20

### Fixed
- Fixed block based Cart and Checkout support by adding the required Blocks integration class and frontend registration script.

### Changed
- Adjusted the WiPay logo to display as a small icon next to the payment method label.

## [1.0.2] - 2025-10-10

### Added
- Official WiPay logo icon shown next to the payment method in both Classic and Blocks checkout.

### Changed
- Improved WooCommerce Blocks Checkout support by loading the Blocks integration only when Blocks is available.

## [1.0.1] - 2025-09-25

### Added
- Sanitised debug log of the WiPay request payload to help with troubleshooting.
- Help section with sandbox test card guidance to reduce failed test attempts.

## [1.0.0] - 2025-09-15

### Added
- First release with the WiPay hosted checkout redirect flow.
- Sandbox and Live settings, fee structure options, optional AVS and card type selection.
- Branded Overview and Help page with copy buttons and setup guidance.
- WooCommerce Blocks Checkout compatibility.
