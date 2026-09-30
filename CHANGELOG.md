# Changelog

## 2.1.0 — 2026-09-24

- Fixed Lead Scoring saves resetting the selected provider and disabling the widget.
- Moved CSV/TXT exports before page output, streamed lead CSV in batches, and protected formula-like CSV cells.
- Replaced shared IP/day conversation IDs with hashed per-browser session tokens, deduplicated leads per session, and added a browseable/exportable Conversations tab.
- Checked prior explicit confirmation and required lead fields before saving; lead transcripts now come from server-observed turns.
- Reworked the widget's focus, keyboard, localization, privacy notice, mobile safe areas, styling scope, and error behavior; removed invalid placeholder font files.
- Replaced unauthenticated CBC encryption writes with AES-256-GCM; migrate readable old keys without exposing plaintext after a failed encryption attempt.
- Updated the original Anthropic default model to an available Haiku model; custom model settings remain editable.
- Allowed cosine ranking of a bounded set of recent KB chunks when literal keyword matches fail; documented lexical search limits.
- Made log retention configurable, corrected Monday digest scheduling across daylight-saving changes, and enforced a real configured HTTPS review link.
- Added GitHub checks, domain smoke tests, architecture and design documentation, and a transparent cost model.

## 2.0.0 — prior release

Introduced service-specific lead fields, Hot/Warm/Cold scoring, analytics, weekly digest, Spanish facts, an existing-client route, three provider clients, multiple keys, lexical vector ranking, review requests, transcript export, and appearance controls. The 2.1 release corrects defects in those features.
