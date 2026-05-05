# Security Policy

## Supported versions

| Version | Supported |
|---------|-----------|
| 1.2.x   | Yes       |
| 1.1.x   | No        |
| < 1.1   | No        |

Only the latest minor version receives security fixes. Older versions should be updated.

## Reporting a security issue

Please do not open a public GitHub issue or pull request for security problems.

Send the report privately to:

- Email: contact@revamped.gy
- WhatsApp: +592-720-3747

Include:

- Plugin version
- WordPress version and WooCommerce version
- A clear description of the issue
- Steps to reproduce
- Suggested fix if you have one
- Whether you intend to publish the issue and on what timeline

You should expect an initial response within 5 working days.

## What should not be posted publicly

Do not include any of the following in public issues, pull requests, screenshots, logs or release notes:

- WiPay live account numbers
- WiPay live API keys
- WiPay sandbox API keys for accounts other than the public test account 123
- Customer card numbers, even partial
- Customer email addresses, phone numbers or full addresses
- Order IDs from production sites that would identify a customer
- WordPress admin passwords or database credentials
- Server configuration details that include private hostnames or paths

The plugin masks these values in its own debug logs. Treat any leak of the values above as a security issue and report it privately.

## Responsible disclosure expectations

If you report an issue privately, you can expect:

- Acknowledgement of the report within 5 working days
- An assessment of the impact and a target fix timeline
- A patch released through the normal GitHub Releases channel
- Public credit in the changelog if you would like it, with your preferred name or handle

We ask that you do not publish details of the issue until a fix has been released.

## Safe setup practices

When configuring this plugin:

- Use Sandbox mode for testing. Do not test with live credentials.
- Keep the live API key in the gateway settings only. Do not commit it to source control or paste it into screenshots, logs, support tickets or chat messages.
- Run the WordPress site over HTTPS. The return URL contains a signed token that should not travel over plain HTTP.
- Disable the Debug logging option after troubleshooting. The log path is publicly listed in WooCommerce status, so leaving debug on long term is not recommended.
- Keep WordPress, WooCommerce and the plugin updated. Older versions may contain known issues that have already been fixed.

## Built-in protections

The plugin already enforces several controls on the return endpoint:

- A signed HMAC-SHA256 token with a 4 hour expiry, validated before any payment status is accepted
- A hash check on successful responses using the WiPay API key, required in Live mode
- Order ID, currency and transaction ID matching against the values stored at request time
- Replay protection so a paid order cannot be reprocessed with a different transaction ID
- Per order plus IP rate limiting on the return handler

If you find a way to bypass any of these, please report it privately using the steps above.
