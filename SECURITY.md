# Security policy

Report a suspected vulnerability privately to the Safety Host Unit site owner through an established private contact channel. Include affected version, reproduction steps, and impact; do not disclose visitor information or publish a working exploit before the maintainer has had a chance to investigate. This repository does not supply a dedicated security mailbox.

Administrator credentials, WordPress auth salts, provider keys, the database, mail server, backups, and HTTPS termination remain part of the deployment's security boundary. API keys are encrypted in `wp_options`, but chat and lead content is stored unencrypted in WordPress tables and sent to the selected AI provider. Configure the site's privacy policy and retention settings accordingly. Guest chat requests are public and rate limited; admin actions require WordPress access controls. Keep WordPress, PHP, and the plugin updated.
