# SHU AI Assistant

**A private-security website assistant that answers questions, qualifies inquiries, and gives the team a readable record of each conversation.** Built for [Safety Host Unit](https://safetyhostunit.com/), the WordPress plugin combines a configurable chat widget with site-content search, a choice of AI providers, lead management, and an admin conversation archive. Version **2.1.0**.

It runs inside your WordPress installation. You provide an API key for Groq, Anthropic, or OpenAI; the plugin does not require a separate chatbot SaaS subscription, hosted vector database, or paid CRM integration. Provider usage, hosting, and upkeep still cost money.

## Why own the assistant?

A visitor looking for event security on a Friday evening should get an answer and a clear next step. The assistant searches published site content and administrator-written facts, answers in the visitor's language, and can ask for the details a security team needs to follow up: service, location, timing, contact information, and service-specific requirements. After the visitor confirms, the lead appears in WordPress with Hot/Warm/Cold priority, an email alert if enabled, and a transcript. Existing clients get the two office numbers without entering a sales questionnaire.

The **Conversations** tab keeps server-recorded visitor and assistant turns even if the visitor never becomes a lead. Staff can read and download retained conversations, spot missed questions in Analytics, improve the knowledge base, and follow up with more context. Lead snapshots remain attached to leads. Default chat-log retention is 180 days; setting retention to `0` keeps logs until the administrator removes them. Backups and WordPress hosting determine actual durability.

### The subscription-cost case, with transparent assumptions

The plugin can replace the *chatbot software layer* of a purchased stack when its feature set meets your needs. It does not replace a live dispatcher, a CRM, or a licensed security professional. Here is a **hypothetical budget**, not a quote for any vendor or a prediction of results:

| Monthly line item | Example amount |
| --- | ---: |
| Displaced managed assistant / lead workflow subscription | $3,000 |
| AI provider API usage | −$120 |
| Incremental hosting and email allocation | −$40 |
| Security, content, and WordPress maintenance allocation | −$250 |
| **Illustrative monthly difference** | **$2,590** |
| **Illustrative annual difference** | **$31,080** |

Formula: **subscription fees avoided − actual provider usage − incremental hosting/email − maintenance = net software cost difference**. If the subscription being replaced costs $850/month under the same $410/month operating assumptions, the difference is $440/month. A claim of “thousands saved every month” is therefore plausible only when the actual displaced subscription or combined software spend is high enough. Compare like-for-like capabilities, traffic, provider rates, and staff time before using the number publicly.

## What is included

| Area | Implemented behavior |
| --- | --- |
| Visitor experience | Responsive chat panel, configurable palette and labels, keyboard focus handling, reduced-motion support, optional Spanish greeting and header, visible privacy notice |
| Knowledge | Published WordPress Pages/Posts indexed on save or via manual rebuild; keyword/synonym candidates blended with term-frequency cosine scoring; optional English/Spanish Key Facts |
| AI delivery | Groq, Anthropic, or OpenAI selectable in settings; multiple keys per provider; round-robin selection and short cooldown on 401/403/429 responses |
| Lead capture | Model-guided six core questions plus service-specific qualifiers; explicit confirmation check; server-side required-field validation and scoring; one lead per browser session |
| Operations | Leads and Conversations tabs, search/filter/export, status changes, optional lead emails, manual closed-lead review request, analytics and weekly digest |
| Data controls | Authenticated encryption for provider keys, admin capability checks and nonces for administrative actions, request throttling, configurable log retention, optional deletion on uninstall |

**Service-specific qualifiers:** Event Security (date, guests, setting, alcohol); Residential (property, gate, existing measures); Executive & Personal Protection (duration, principals, visitor-reported threat); Mobile Patrol (property, locations, frequency); Fire Watch (reason, site, duration); Warehouse & Commercial (size, coverage, hours). The AI asks these conversationally; a saved lead may still lack an extra answer if the visitor skips it. Staff should confirm operational requirements directly.

## Install or upgrade

1. Back up the WordPress database and existing plugin folder. Test upgrades on staging first.
2. Upload the `shu-ai-assistant` folder to `wp-content/plugins/`, or upload a ZIP containing that single folder through **Plugins → Add New → Upload Plugin**. Activate it.
3. Open **Settings → SHU AI Assistant → General & Appearance**. Select Groq, Anthropic, or OpenAI; enter at least one provider API key, one per line; set the model supported by your account. Check the current [Groq](https://console.groq.com/docs/models), [Anthropic](https://platform.claude.com/docs/en/models/overview), or [OpenAI](https://developers.openai.com/api/docs/models) model list before changing it. API keys are never included in this repository.
4. Visit **Knowledge Base**. Add authoritative Key Facts and FAQs, including Spanish text if useful, then click **Rebuild Knowledge Base**. Rebuild after upgrading from a previous version to populate missing vectors. Subsequent Page/Post saves refresh the affected content.
5. In **Notifications & Reviews**, verify recipient addresses and set your actual HTTPS Google review link before using review requests. Test `wp_mail` delivery on the site.
6. Check the widget on desktop and mobile, send a test inquiry through explicit confirmation, and verify the Leads, Conversations, and Analytics tabs. Check that the correct office numbers and facts reflect current operations.

**Requirements:** WordPress 6.0+, PHP 7.4+, PHP OpenSSL, a working WordPress database, outbound HTTPS access to the chosen AI provider, and a provider account/key. A reliable server cron is recommended: WordPress WP-Cron only runs when the site receives traffic unless a system scheduler invokes it.

**Upgrade notes:** `dbDelta()` adds the `session_id` lead column and index without deleting old leads. The DB schema version is 2.1. On first load, plaintext and the earlier CBC-formatted provider keys are migrated to authenticated AES-256-GCM encryption when the original WordPress `AUTH_KEY` and `AUTH_SALT` are present. Keep the same WordPress salts when moving a database, or re-enter the provider keys. The three original 37-byte “Inter” files were invalid placeholders; the widget now uses a dependable system-font stack, with an admin font-family override. Existing saved color and text settings remain intact.
The previous default Anthropic model ID is updated to `claude-haiku-4-5-20251001` on upgrade; review the model choice if you configured Anthropic.

## Daily use

- **Leads & Scoring:** sort/filter by priority and status; inspect service answers and the snapshot transcript; export CSV or individual TXT. Changing to Closed does **not** send a review email. Review Request is a separate manual action; use it only for an actual customer.
- **Conversations:** browse chat sessions, including those without a lead, and download TXT records. Server logging starts on the first message sent to the API; the local greeting and unsent quick-reply prompt are UI only. A provider error may leave a visitor turn without an assistant turn.
- **Analytics:** 30/90-day conversation counts, lead count/conversion, frequent keyword themes, and low-relevance KB questions. These are operational indicators; low KB relevance does not prove the model failed to answer, and conversion is leads divided by sessions in the selected time window.
- **Weekly digest:** separately enable or disable it under Notifications. A single cron event schedules the next Monday at 08:00 in the site's timezone after each run. Delayed WP-Cron traffic can delay delivery.
- **Appearance:** tune trigger, header, bubbles, send button, radius, labels, and greeting. The preview updates with the principal colors and text. Spanish labels appear when the browser language begins with `es`; in-chat replies also follow visitor text.
- **Retention:** daily cleanup deletes conversation-log turns older than the configured number of days. `0` disables cleanup. Lead transcript snapshots remain until leads are removed. Deactivation preserves data; deletion preserves data unless **Delete all data on uninstall** was enabled.

## Security and privacy notes

The visitor chat endpoint is public by design and throttled by the web server's `REMOTE_ADDR`; a guest WordPress nonce is neither authentication nor reliable on long-lived cached pages. Administrative changes and exports require `manage_options` plus WordPress nonces. Browser-generated random IDs are HMAC-hashed before storage, so conversation grouping does not use IP addresses. Messages and lead records **are stored as plaintext in your WordPress database**, and messages are sent to the configured AI provider. API keys alone are encrypted at rest; database access, backups, mail delivery, site privacy disclosures, and credential rotation are the site operator's responsibility. Keys in this repository or in support tickets must never be committed.

The search engine uses **term-frequency vectors and a small synonym map**, not external embeddings or a full TF-IDF corpus model. It examines up to 200 literal candidates plus 300 recent chunks and cannot guarantee retrieval of an older paraphrased article on a large site. The model can make mistakes. Keep Key Facts current and verify quotes, coverage, licensing, emergencies, and promises with the human team. The prompt directs immediate danger to 911; it does not provide emergency dispatch.

## Repository guide

- [`SYSTEM_ARCHITECTURE.md`](SYSTEM_ARCHITECTURE.md) — boundaries, components, runtime path, and deployment.
- [`SYSTEM_DESIGN.md`](SYSTEM_DESIGN.md) — storage, contracts, session lifecycle, failure handling, and scaling constraints.
- [`CHANGELOG.md`](CHANGELOG.md) — release history.
- [`SECURITY.md`](SECURITY.md) — private vulnerability reporting and operational cautions.
- `tests/smoke.php` — dependency-free domain checks; `.github/workflows/checks.yml` lints PHP 7.4/8.2/8.4, runs those checks, and checks JavaScript syntax.

The WordPress plugin entry point is `shu-ai-assistant.php`. `includes/` holds provider clients, lead/KB/analytics/domain logic; `admin/` holds settings and reporting screens; `assets/` contains the public widget. No build step or Composer dependency is required.

## Contributing and licensing

See [`CONTRIBUTING.md`](CONTRIBUTING.md) for review expectations. GPL-2.0-or-later; see [`LICENSE`](LICENSE). Company names and site-specific copy identify the intended installation and should be reviewed before reuse on another business site.
