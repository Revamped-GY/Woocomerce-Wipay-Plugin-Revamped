# Contributing

Thanks for your interest in improving this plugin. Pull requests and issue reports are welcome.

## Reporting issues

Open a GitHub issue with:

- Plugin version
- WordPress version
- WooCommerce version
- A clear description of the problem
- Steps to reproduce
- Any messages from `WooCommerce > Status > Logs > rgy-wipay-*`

For payment flow problems, include the WiPay transaction ID from the order notes if you have one.

Do not post live API keys, account numbers or customer data in public issues. See [SECURITY.md](SECURITY.md) for what to keep private and how to report security issues.

## Suggesting a change

Open a GitHub issue first to discuss the idea before writing code. This avoids wasted work if the change is out of scope.

Good candidates:

- Bug fixes with reproducible steps
- WiPay API features that this plugin does not yet support
- Improvements to the admin UX or logging
- Translations
- Test coverage

Out of scope:

- Changes that break the plugin slug or main file name
- Changes to the gateway ID `rgy_wipay`
- Changes to the option key `woocommerce_rgy_wipay_settings`
- Custom UI for plugin updates that bypasses the GitHub Releases flow

These are intentional invariants. Breaking them would orphan existing installs.

## Pull request guidelines

Branch from `main`. Keep one logical change per pull request.

Each pull request should explain:

- What changed
- Why it changed
- How it was tested
- Any security impact
- Any user facing change that needs documenting

If your change touches the gateway code, run a sandbox transaction end to end before opening the pull request. Sandbox credentials are account number `1234567890` and API key `123`. WiPay sandbox test cards are listed in the Help page and in `WiPay-API-Documentation.pdf`.

If your change is user facing, update the readme and the changelog.

## Code style

Follow the existing style in the codebase.

- Plain procedural OOP. No autoloaders. New classes go in `includes/` and are required where needed.
- Class names use the prefix `RGY_WiPay_*`.
- Hooks, options and transients use the prefix `rgy_wipay_*`.
- All user input from `$_GET`, `$_POST` or external APIs must be sanitised before use.
- All output must be escaped. `esc_html`, `esc_attr`, `esc_url` and `wp_kses_post` are the common helpers used here.
- All user facing strings must be translatable using the text domain `revamped-wipay-woocommerce`.
- Never compare against translated strings in logic. Use a non translated key for branching, then translate only for display.
- Order metadata access must use the WooCommerce CRUD methods, not direct postmeta. The plugin declares HPOS compatibility.

Comments should explain the why, not the what. Most code should be readable without comments.

## Testing checklist

Before opening a pull request, confirm:

- All PHP files lint cleanly. Run `php -l` on each file or use the project lint script if available.
- The plugin activates without warnings on a fresh WordPress install with WooCommerce.
- Sandbox payments still complete end to end.
- The Overview and Help pages render without warnings on PHP 7.4 and PHP 8.x.
- WooCommerce Blocks Checkout still works.
- If the change touches the return handler, a tampered return URL is still rejected.

## Releasing a new version

Releases are published on GitHub Releases. The plugin checks the latest release tag and uses the attached ZIP asset as the update package.

The release process is documented in the maintainer notes. The short version:

1. Update the version in the main plugin file, the version constant, the readme and the changelog.
2. Tag the commit as `vX.Y.Z`.
3. The release workflow builds the ZIP and attaches it to the release.

## Code of conduct

Be respectful. Stay focused on the technical issue. We do not have a formal code of conduct document yet. Common sense applies.

## Credits

Built and maintained by [Revamped GY](https://revamped.gy). If you contribute a change that ships in a release, you can opt in to a credit in the changelog.
