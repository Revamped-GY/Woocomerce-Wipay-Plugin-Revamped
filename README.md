# Revamped WiPay Gateway for WooCommerce

A WooCommerce payment gateway that accepts card payments through WiPay's hosted payment page.

Customers select WiPay at checkout, are redirected to WiPay to enter their card details, then return to your site where WooCommerce confirms the payment and updates the order.

## Main features

- WiPay hosted redirect checkout flow with Sandbox and Live environments
- WooCommerce Classic Checkout and Blocks Checkout support
- Optional USD charging with live FX conversion when your store currency is not supported by WiPay
- Security hardened return handling with signed return token, transaction matching and replay protection
- Native WordPress updates through GitHub Releases
- Branded admin Overview and Help pages with status cards and setup guidance
- Optional debug logging for troubleshooting

## Tech stack

- PHP 7.4 or later
- WordPress 6.0 or later
- WooCommerce 6.0 or later
- WooCommerce Blocks (optional, for Blocks Checkout support)

## Requirements

- A WordPress site running WooCommerce
- A verified WiPay Business Account for live transactions
- A WiPay API Key for hash validation in live mode
- HTTPS on the checkout pages

## Installation

1. Download the plugin ZIP from the [latest release](https://github.com/RevampedGY/revamped-wipay-woocommerce/releases/latest).
2. In WordPress, go to Plugins, then Add New, then Upload Plugin.
3. Upload the ZIP and activate the plugin.
4. Go to WooCommerce, then Settings, then Payments.
5. Enable WiPay and open the gateway settings.

## Configuration

In the WiPay gateway settings, set:

- Environment: Sandbox for testing or Live for production
- API Country: the country your WiPay account is registered in
- Account Number: your live account number
- API Key: your live API key
- Charge currency: store currency, or USD if your store currency is not supported by WiPay
- Fee structure: choose how WiPay fees are handled

The plugin includes sandbox defaults so you can test before entering live credentials.

## Usage

### Checkout flow

1. Customer adds items and goes to checkout.
2. Customer selects "Credit or Debit Card (WiPay)" and clicks Place Order.
3. Customer is redirected to the WiPay hosted page.
4. Customer enters their card details and completes payment.
5. Customer is redirected back to your site.
6. The plugin validates the response and marks the order paid or failed.

### Charging in USD

If your store currency is not supported by WiPay, set Charge currency to USD. The plugin converts the order total from your store currency to USD using a live exchange rate, then rounds up to the next whole USD before redirecting to WiPay.

The exchange rate is cached for the duration set in the gateway settings.

## Security notes

- API keys must be kept private. Do not commit them to source control.
- The plugin does not appear at checkout in Live mode unless an API key is saved. This protects against accidentally running without hash validation.
- The return URL is signed with an HMAC token that expires after 4 hours.
- Repeated return attempts are rate limited per order and IP.
- For more details see [SECURITY.md](SECURITY.md).

## Update process

From version 1.2.0 onwards, the plugin checks GitHub Releases for new versions automatically.

When a new release is published on GitHub, WordPress shows the update on the Plugins page and in Dashboard, then Updates. The plugin downloads the release asset and installs it like any other WordPress plugin update.

To override the GitHub repo at runtime, use the `rgy_wipay_github_repo` filter:

```php
add_filter('rgy_wipay_github_repo', function() {
    return 'YourGitHubOrg/your-fork-name';
});
```

## Troubleshooting

**Order stays Pending after payment**
Check WooCommerce, then Status, then Logs. Filter by source `rgy-wipay`. The log shows why the return was rejected.

**Hash validation failed**
Confirm the API key in the gateway settings matches the environment you are using.

**Currency unsupported by WiPay**
Set Charge currency to USD in the gateway settings.

**Customer charged but order failed**
The signed return token expired. Check the order notes for the reason. Increase the token TTL with the `rgy_wipay_return_token_ttl` filter if needed.

**Blocks Checkout shows incompatible**
Update to the latest plugin version and ensure WiPay is enabled in WooCommerce Payments.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for the full version history.

## Licence

GPLv2 or later. See [LICENSE](LICENSE) for the full text.

## Author

Built and maintained by [Revamped GY](https://revamped.gy).

For support, include the order number, timestamp and the WiPay transaction ID from the order notes or logs.
