# Contributing

1. Open an issue describing the behavior, WordPress/PHP versions, and safe reproduction steps. Remove contact details, visitor transcripts, and API keys from screenshots and logs.
2. Make a focused branch and keep the WordPress plugin installable without Composer or an asset build. Follow the existing WordPress-style PHP formatting; sanitize on input, escape on output, prepare query values, and check capabilities/nonces for admin actions.
3. Run `php -l` over every PHP file, `php tests/smoke.php`, and `node --check assets/js/chat-widget.js`. Test the widget, admin pages, schema upgrade, and mail on a staging WordPress installation. CI runs PHP 7.4, 8.2, and 8.4 syntax/domain checks.
4. Explain data migrations, operational effects, and a manual verification path in the pull request. Update README/design docs when storage, provider behavior, or settings change.

Do not commit credentials, customer data, generated ZIPs, database exports, or development logs. Vulnerabilities should be reported privately as described in [SECURITY.md](SECURITY.md).
