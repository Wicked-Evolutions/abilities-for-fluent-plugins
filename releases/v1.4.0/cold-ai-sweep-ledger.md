# Fresh-context exhaustive cold-AI operator sweep — ledger

> **Phase C Step 10 — release gate.** All new abilities live-tested via cold Claude + GPT 5.5 sessions before v1.4.0 release. Release blocked until J accepts the live evidence.
>
> **Sprint:** [Fluent Suite Registrar Bundle Sprint 2026-05-13](../../../00%20Influencentricity%20OS/Plans/Alpha%20Release%20Gate/Fluent%20Suite%20Registrar%20Bundle%20Sprint%202026-05-13.md)

## Sweep protocol

1. **J installs pre-release zip** on `wicked-community` (network root, multisite) + `helenawillow` (production data — testclient discipline §1–6 binds).
2. **Fresh Claude session** + **fresh GPT 5.5 session** boot cold (no prior context).
3. Each client discovers the live ability surface via the MCP adapter, executes per the per-plugin batch order, captures input/output and classifies any failure.
4. **Testclient discipline:** `[SPRINT-V2-TEST]` markers on all created fixtures, in-run cleanup paired with each create, per-batch audit confirming zero residue.
5. Ledger row marked `Result: ✅ pass` only when the call returned a valid response (or a typed `WP_Error` that the test consciously expected).
6. **Release blocked** until every row has a `Result` value and J accepts the evidence.

## Failure classification taxonomy

| Bucket | Meaning | Disposition |
|---|---|---|
| **product bug** | Our v1.4.0 ability code is wrong (registration shape, callback logic, schema mismatch) | Fix in integration → rebuild → re-sweep that ability before release |
| **vendor precondition** | Vendor module / table / credentials absent on probe site (Pro plugin not configured, integration disabled, etc.) | Acceptable; classify and continue |
| **permission gate** | `permission_callback` correctly denied; expected behavior for the auth context | Acceptable; classify and continue |
| **adapter scope** | MCP adapter scope/grant prevents execution | Track under [adapter #116](https://github.com/Wicked-Evolutions/abilities-mcp-adapter/issues/116) or a new adapter issue; J decides whether this blocks release |
| **client limitation** | Client cannot handle, form, or display the call/result | Triage as client constraint, schema/description improvement, or operator-doc need |
| **operator-pattern issue** | Cold AI made a semantically-wrong call (wrong IDs, wrong order) | Coaching opportunity for prompt patterns; not a release blocker but worth capturing |

## Per-plugin batch assignment

| Batch | Plugin | New abilities | Probe site | Suggested AI client |
|---|---|---|---|---|
| 1 | FluentCRM | 225 | wicked-community | Claude (largest surface) |
| 2 | FluentCart | 108 | wicked-community | GPT 5.5 |
| 3 | Fluent Forms | 88 | wicked-community or helenawillow | Claude |
| 4 | Fluent Bookings | 78 | helenawillow ONLY | GPT 5.5 |
| 5 | Fluent Boards | 161 | helenawillow ONLY | Claude |
| 6 | FluentCommunity + Messaging | 61 | wicked-community + helenawillow parity | GPT 5.5 |
| 7 | FluentPlayer + Pro | 103 | helenawillow primary, wicked-community parity | Claude |

(Assignments are suggested — swap per cold-AI availability. Alternation between Claude and GPT 5.5 across plugins gives more independent coverage.)

## Pre-sweep checklist (orchestrator)

- [ ] Pre-release zip built from main with `Version: 1.4.0` header + `FLUENT_ABILITIES_VERSION = '1.4.0'`
- [ ] Zip installed on `wicked-community` (network-active)
- [ ] Zip installed on `helenawillow`
- [ ] Central testclient contact `sprint-test+v2@wickedevolutions.com` present on both sites
- [ ] EDD auto-updater bypass active (Version-bump-before-zip mitigation OR `fluent_abilities_disable_updater` option)
- [ ] Adapter scope grants topped up if Mode C realism is wanted (otherwise classify any rejections as `adapter scope` per taxonomy)

## Post-sweep audit (orchestrator + J)

- [ ] Zero `[SPRINT-V2-TEST]` residuals across all 7 plugin primary tables on both probe sites
- [ ] Central test contact removed
- [ ] Plugin restored to v1.4.0 release artifact (not the bumped-version dev build)
- [ ] Ledger 100% filled (every row has a `Result` value)
- [ ] Failure classification ledger appended to integration → main release notes for Reviewer final verdict

---

# Ledger — all 808 new abilities

> Note on count: grep extraction of slug literals from the merged code surfaces 808 unique new slugs. The sprint summary cites 824 (per per-PR ratified counts). The 16-slug delta may be from variable-interpolated registrations or helper-mode patterns that don't extract as literals. The cold-AI sweep should reconcile against the LIVE deployed registry (via `mcp-adapter-discover-abilities` post-install) — that's the ground truth and may surface the missing 16.

## FluentCRM — 225 new abilities

**Probe site:** wicked-community

### extended-campaigns.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-crm/advance-campaign-step` | | | | | | | |
| `fluent-crm/delete-campaign-emails` | | | | | | | |
| `fluent-crm/do-bulk-action-campaigns` | | | | | | | |
| `fluent-crm/do-bulk-action-tags` | | | | | | | |
| `fluent-crm/draft-campaign-recipients` | | | | | | | |
| `fluent-crm/duplicate-campaign` | | | | | | | |
| `fluent-crm/estimate-campaign-contacts` | | | | | | | |
| `fluent-crm/get-campaign-contacts-by-segment` | | | | | | | |
| `fluent-crm/get-campaign-estimated-recipient-count` | | | | | | | |
| `fluent-crm/get-campaign-link-report` | | | | | | | |
| `fluent-crm/get-campaign-overview-stats` | | | | | | | |
| `fluent-crm/get-campaign-processing-stat` | | | | | | | |
| `fluent-crm/get-campaign-revenues` | | | | | | | |
| `fluent-crm/get-campaign-share-url` | | | | | | | |
| `fluent-crm/get-campaign-status` | | | | | | | |
| `fluent-crm/list-campaign-emails` | | | | | | | |
| `fluent-crm/list-campaign-unsubscribers` | | | | | | | |
| `fluent-crm/pause-campaign` | | | | | | | |
| `fluent-crm/preview-campaign-email-html` | | | | | | | |
| `fluent-crm/preview-campaign-recipient-email` | | | | | | | |
| `fluent-crm/resume-campaign` | | | | | | | |
| `fluent-crm/resync-campaign-revenues` | | | | | | | |
| `fluent-crm/schedule-campaign` | | | | | | | |
| `fluent-crm/send-test-email-campaign` | | | | | | | |
| `fluent-crm/unschedule-campaign` | | | | | | | |
| `fluent-crm/update-campaign-labels` | | | | | | | |
| `fluent-crm/update-campaign-title` | | | | | | | |
| `fluent-crm/update-single-campaign-property` | | | | | | | |

### extended-funnels.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-crm/advance-funnel-subscriber` | | | | | | | |
| `fluent-crm/change-funnel-trigger` | | | | | | | |
| `fluent-crm/clone-funnel` | | | | | | | |
| `fluent-crm/create-funnel-from-template` | | | | | | | |
| `fluent-crm/delete-funnel-subscribers` | | | | | | | |
| `fluent-crm/do-bulk-action-funnels` | | | | | | | |
| `fluent-crm/get-funnel-all-activities` | | | | | | | |
| `fluent-crm/get-funnel-email-reports` | | | | | | | |
| `fluent-crm/get-funnel-report` | | | | | | | |
| `fluent-crm/get-funnel-subscriber-detail` | | | | | | | |
| `fluent-crm/get-funnel-syncable-counts` | | | | | | | |
| `fluent-crm/import-funnel` | | | | | | | |
| `fluent-crm/list-funnel-subscribers` | | | | | | | |
| `fluent-crm/list-funnel-templates` | | | | | | | |
| `fluent-crm/list-funnel-triggers` | | | | | | | |
| `fluent-crm/list-subscriber-automations` | | | | | | | |
| `fluent-crm/remove-bulk-funnel-subscribers` | | | | | | | |
| `fluent-crm/save-funnel-email-action` | | | | | | | |
| `fluent-crm/save-funnel-email-action-fallback` | | | | | | | |
| `fluent-crm/save-funnel-sequences` | | | | | | | |
| `fluent-crm/save-funnel-sequences-step` | | | | | | | |
| `fluent-crm/send-test-funnel-webhook` | | | | | | | |
| `fluent-crm/sync-funnel-new-steps` | | | | | | | |
| `fluent-crm/update-funnel-labels` | | | | | | | |
| `fluent-crm/update-funnel-subscriber-status` | | | | | | | |
| `fluent-crm/update-funnel-title` | | | | | | | |

### extended-misc-medium.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-crm/bulk-delete-abandon-carts` | | | | | | | |
| `fluent-crm/generate-ai-content` | | | | | | | |
| `fluent-crm/get-abandon-cart-report-summary` | | | | | | | |
| `fluent-crm/get-ai-settings` | | | | | | | |
| `fluent-crm/get-contact-custom-fields` | | | | | | | |
| `fluent-crm/import-from-wp-users` | | | | | | | |
| `fluent-crm/list-abandon-carts` | | | | | | | |
| `fluent-crm/list-import-drivers` | | | | | | | |
| `fluent-crm/run-import-csv` | | | | | | | |
| `fluent-crm/run-import-driver` | | | | | | | |
| `fluent-crm/test-ai-connection` | | | | | | | |
| `fluent-crm/update-ai-settings` | | | | | | | |
| `fluent-crm/update-contact-custom-fields` | | | | | | | |
| `fluent-crm/update-contact-custom-fields-group-name` | | | | | | | |
| `fluent-crm/upload-import-csv` | | | | | | | |

### extended-misc-small.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-crm/create-label` | | | | | | | |
| `fluent-crm/create-webhook` | | | | | | | |
| `fluent-crm/delete-label` | | | | | | | |
| `fluent-crm/delete-webhook` | | | | | | | |
| `fluent-crm/get-doc` | | | | | | | |
| `fluent-crm/get-form-entry-detail` | | | | | | | |
| `fluent-crm/global-search` | | | | | | | |
| `fluent-crm/list-docs` | | | | | | | |
| `fluent-crm/list-docs-addons` | | | | | | | |
| `fluent-crm/list-fluent-forms-templates` | | | | | | | |
| `fluent-crm/list-form-entries` | | | | | | | |
| `fluent-crm/list-labels` | | | | | | | |
| `fluent-crm/list-user-roles` | | | | | | | |
| `fluent-crm/list-users-for-fluent-crm` | | | | | | | |
| `fluent-crm/list-webhooks` | | | | | | | |
| `fluent-crm/update-label` | | | | | | | |
| `fluent-crm/update-webhook` | | | | | | | |

### extended-pro-companies.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-crm/attach-subscribers-to-company` | | | | | | | |
| `fluent-crm/create-company` | | | | | | | |
| `fluent-crm/create-company-note` | | | | | | | |
| `fluent-crm/delete-company` | | | | | | | |
| `fluent-crm/delete-company-note` | | | | | | | |
| `fluent-crm/detach-subscribers-from-company` | | | | | | | |
| `fluent-crm/do-bulk-action-companies` | | | | | | | |
| `fluent-crm/get-company` | | | | | | | |
| `fluent-crm/get-company-custom-fields` | | | | | | | |
| `fluent-crm/get-company-custom-tab-view` | | | | | | | |
| `fluent-crm/import-companies-csv` | | | | | | | |
| `fluent-crm/list-company-notes` | | | | | | | |
| `fluent-crm/search-companies` | | | | | | | |
| `fluent-crm/search-unattached-contacts-for-company` | | | | | | | |
| `fluent-crm/update-companies-property` | | | | | | | |
| `fluent-crm/update-company` | | | | | | | |
| `fluent-crm/update-company-note` | | | | | | | |

### extended-pro-marketing.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-crm/activate-smart-link` | | | | | | | |
| `fluent-crm/bulk-delete-recurring-campaigns` | | | | | | | |
| `fluent-crm/change-recurring-campaign-status` | | | | | | | |
| `fluent-crm/create-dynamic-segment` | | | | | | | |
| `fluent-crm/create-recurring-campaign` | | | | | | | |
| `fluent-crm/delete-dynamic-segment` | | | | | | | |
| `fluent-crm/do-bulk-action-recurring-campaigns` | | | | | | | |
| `fluent-crm/do-bulk-action-sequences` | | | | | | | |
| `fluent-crm/duplicate-dynamic-segment` | | | | | | | |
| `fluent-crm/duplicate-recurring-campaign` | | | | | | | |
| `fluent-crm/duplicate-sequence` | | | | | | | |
| `fluent-crm/duplicate-sequence-email` | | | | | | | |
| `fluent-crm/estimate-dynamic-segment-contacts` | | | | | | | |
| `fluent-crm/get-dynamic-segment-stats` | | | | | | | |
| `fluent-crm/get-dynamic-segment-subscriber` | | | | | | | |
| `fluent-crm/get-recurring-campaign` | | | | | | | |
| `fluent-crm/get-recurring-campaign-email` | | | | | | | |
| `fluent-crm/list-campaigns-pro-post-taxonomies` | | | | | | | |
| `fluent-crm/list-campaigns-pro-posts` | | | | | | | |
| `fluent-crm/list-campaigns-pro-products` | | | | | | | |
| `fluent-crm/list-dynamic-segment-custom-fields` | | | | | | | |
| `fluent-crm/list-dynamic-segments` | | | | | | | |
| `fluent-crm/list-recurring-campaign-emails` | | | | | | | |
| `fluent-crm/list-recurring-campaigns` | | | | | | | |
| `fluent-crm/list-sequences-for-subscriber` | | | | | | | |
| `fluent-crm/manage-sequence-subscribers` | | | | | | | |
| `fluent-crm/reapply-sequence` | | | | | | | |
| `fluent-crm/resend-campaign-emails` | | | | | | | |
| `fluent-crm/resend-failed-campaign-emails` | | | | | | | |
| `fluent-crm/resend-unopened-campaign-emails` | | | | | | | |
| `fluent-crm/sequence-email-update-create` | | | | | | | |
| `fluent-crm/tag-actions-on-campaign` | | | | | | | |
| `fluent-crm/update-dynamic-segment` | | | | | | | |
| `fluent-crm/update-recurring-campaign-data` | | | | | | | |
| `fluent-crm/update-recurring-campaign-email` | | | | | | | |
| `fluent-crm/update-recurring-campaign-labels` | | | | | | | |
| `fluent-crm/update-recurring-campaign-settings` | | | | | | | |
| `fluent-crm/update-sequence-email-delay` | | | | | | | |

### extended-pro-settings-and-commerce.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-crm/create-pro-manager` | | | | | | | |
| `fluent-crm/delete-pro-manager` | | | | | | | |
| `fluent-crm/disable-sms-provider` | | | | | | | |
| `fluent-crm/get-commerce-report` | | | | | | | |
| `fluent-crm/get-sms-settings` | | | | | | | |
| `fluent-crm/list-commerce-reports-for-provider` | | | | | | | |
| `fluent-crm/list-pro-managers` | | | | | | | |
| `fluent-crm/update-pro-manager` | | | | | | | |
| `fluent-crm/update-sms-settings` | | | | | | | |

### extended-reports.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-crm/delete-report-emails` | | | | | | | |
| `fluent-crm/get-report-advanced-providers` | | | | | | | |
| `fluent-crm/get-report-automation-steps` | | | | | | | |
| `fluent-crm/get-report-automations` | | | | | | | |
| `fluent-crm/get-report-contacts-by-country` | | | | | | | |
| `fluent-crm/get-report-contacts-by-lists` | | | | | | | |
| `fluent-crm/get-report-contacts-by-status` | | | | | | | |
| `fluent-crm/get-report-contacts-by-tags` | | | | | | | |
| `fluent-crm/get-report-email-clicks` | | | | | | | |
| `fluent-crm/get-report-email-opens` | | | | | | | |
| `fluent-crm/get-report-email-performance` | | | | | | | |
| `fluent-crm/get-report-email-sents` | | | | | | | |
| `fluent-crm/get-report-email-unsubs` | | | | | | | |
| `fluent-crm/get-report-recent-tags` | | | | | | | |
| `fluent-crm/get-report-subscribers` | | | | | | | |
| `fluent-crm/get-report-taxonomy-terms` | | | | | | | |
| `fluent-crm/get-report-top-campaigns` | | | | | | | |
| `fluent-crm/list-report-emails` | | | | | | | |

### extended-settings.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-crm/get-abandon-cart-settings` | | | | | | | |
| `fluent-crm/get-auto-subscribe-settings` | | | | | | | |
| `fluent-crm/get-bounce-configs` | | | | | | | |
| `fluent-crm/get-compliance-settings` | | | | | | | |
| `fluent-crm/get-cron-status` | | | | | | | |
| `fluent-crm/get-double-optin-config` | | | | | | | |
| `fluent-crm/get-experiments-config` | | | | | | | |
| `fluent-crm/get-integrations-config` | | | | | | | |
| `fluent-crm/get-old-logs` | | | | | | | |
| `fluent-crm/get-settings` | | | | | | | |
| `fluent-crm/get-system-logs` | | | | | | | |
| `fluent-crm/list-experiments-campaigns` | | | | | | | |
| `fluent-crm/update-abandon-cart-settings` | | | | | | | |
| `fluent-crm/update-auto-subscribe-settings` | | | | | | | |
| `fluent-crm/update-compliance-settings` | | | | | | | |
| `fluent-crm/update-double-optin-config` | | | | | | | |
| `fluent-crm/update-experiments-config` | | | | | | | |
| `fluent-crm/update-integrations-config` | | | | | | | |
| `fluent-crm/update-settings` | | | | | | | |

### extended-subscribers.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-crm/bulk-add-update-contacts` | | | | | | | |
| `fluent-crm/do-bulk-action-contacts` | | | | | | | |
| `fluent-crm/export-subscribers` | | | | | | | |
| `fluent-crm/get-contact-dynamic-item-view` | | | | | | | |
| `fluent-crm/get-contact-external-view` | | | | | | | |
| `fluent-crm/get-contact-form-submissions` | | | | | | | |
| `fluent-crm/get-contact-info-widgets` | | | | | | | |
| `fluent-crm/get-contact-purchase-history` | | | | | | | |
| `fluent-crm/get-contact-support-tickets` | | | | | | | |
| `fluent-crm/get-contact-url-metrics` | | | | | | | |
| `fluent-crm/list-subscriber-tracking-events` | | | | | | | |
| `fluent-crm/list-subscribers-prev-next-ids` | | | | | | | |
| `fluent-crm/search-contacts-fast` | | | | | | | |
| `fluent-crm/sync-subscribers-segments` | | | | | | | |
| `fluent-crm/update-subscribers-property` | | | | | | | |

### extended-templates-and-patterns.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-crm/create-editor-pattern` | | | | | | | |
| `fluent-crm/create-email-pattern` | | | | | | | |
| `fluent-crm/create-template` | | | | | | | |
| `fluent-crm/delete-email-pattern` | | | | | | | |
| `fluent-crm/delete-email-pattern-category` | | | | | | | |
| `fluent-crm/delete-template` | | | | | | | |
| `fluent-crm/do-bulk-action-templates` | | | | | | | |
| `fluent-crm/duplicate-template` | | | | | | | |
| `fluent-crm/get-email-pattern` | | | | | | | |
| `fluent-crm/get-email-pattern-wp-format` | | | | | | | |
| `fluent-crm/get-template` | | | | | | | |
| `fluent-crm/list-built-in-templates` | | | | | | | |
| `fluent-crm/list-editor-cart-products` | | | | | | | |
| `fluent-crm/list-editor-pattern-categories` | | | | | | | |
| `fluent-crm/list-editor-patterns` | | | | | | | |
| `fluent-crm/list-email-pattern-categories` | | | | | | | |
| `fluent-crm/list-email-patterns` | | | | | | | |
| `fluent-crm/list-smart-codes` | | | | | | | |
| `fluent-crm/list-templates-all` | | | | | | | |
| `fluent-crm/manage-editor-pattern` | | | | | | | |
| `fluent-crm/set-global-email-style` | | | | | | | |
| `fluent-crm/update-email-pattern` | | | | | | | |
| `fluent-crm/update-template` | | | | | | | |

## FluentCart — 100 new abilities

**Probe site:** wicked-community

### activity-abilities.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-cart/delete-activity` | | | | | | | |
| `fluent-cart/list-activities` | | | | | | | |
| `fluent-cart/mark-activity-read` | | | | | | | |

### attribute-abilities.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-cart/create-attribute-group` | | | | | | | |
| `fluent-cart/create-attribute-term` | | | | | | | |
| `fluent-cart/delete-attribute-group` | | | | | | | |
| `fluent-cart/get-attribute-group` | | | | | | | |
| `fluent-cart/list-attribute-groups` | | | | | | | |
| `fluent-cart/list-attribute-terms` | | | | | | | |
| `fluent-cart/reorder-attribute-term` | | | | | | | |
| `fluent-cart/update-attribute-group` | | | | | | | |

### coupon-extended-abilities.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-cart/apply-coupon` | | | | | | | |
| `fluent-cart/cancel-coupon` | | | | | | | |
| `fluent-cart/check-coupon-product-eligibility` | | | | | | | |
| `fluent-cart/get-coupon-settings` | | | | | | | |
| `fluent-cart/reapply-coupon` | | | | | | | |

### customer-extended-abilities.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-cart/attach-wp-user-to-customer` | | | | | | | |
| `fluent-cart/create-customer` | | | | | | | |
| `fluent-cart/create-customer-address` | | | | | | | |
| `fluent-cart/delete-customer` | | | | | | | |
| `fluent-cart/delete-customer-address` | | | | | | | |
| `fluent-cart/detach-wp-user-from-customer` | | | | | | | |
| `fluent-cart/get-customer-address` | | | | | | | |
| `fluent-cart/get-customer-stats` | | | | | | | |
| `fluent-cart/list-attachable-users` | | | | | | | |
| `fluent-cart/list-customer-orders` | | | | | | | |
| `fluent-cart/make-address-primary` | | | | | | | |
| `fluent-cart/recalculate-customer-ltv` | | | | | | | |
| `fluent-cart/update-customer-address` | | | | | | | |

### license-extended-abilities.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-cart/activate-license-site` | | | | | | | |
| `fluent-cart/deactivate-license-site` | | | | | | | |
| `fluent-cart/delete-license` | | | | | | | |
| `fluent-cart/extend-license-validity` | | | | | | | |
| `fluent-cart/get-customer-licenses` | | | | | | | |
| `fluent-cart/get-license` | | | | | | | |
| `fluent-cart/get-product-license-settings` | | | | | | | |
| `fluent-cart/list-license-activations` | | | | | | | |
| `fluent-cart/regenerate-license-key` | | | | | | | |
| `fluent-cart/update-license-limit` | | | | | | | |
| `fluent-cart/update-license-status` | | | | | | | |

### order-management-abilities.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-cart/accept-dispute` | | | | | | | |
| `fluent-cart/create-and-attach-customer-to-order` | | | | | | | |
| `fluent-cart/create-custom-order` | | | | | | | |
| `fluent-cart/create-order` | | | | | | | |
| `fluent-cart/delete-order-item` | | | | | | | |
| `fluent-cart/get-order-addresses` | | | | | | | |
| `fluent-cart/get-order-transaction` | | | | | | | |
| `fluent-cart/list-order-transactions` | | | | | | | |
| `fluent-cart/mark-order-paid` | | | | | | | |
| `fluent-cart/sync-order-statuses` | | | | | | | |
| `fluent-cart/update-order-address-id` | | | | | | | |
| `fluent-cart/update-order-customer` | | | | | | | |
| `fluent-cart/update-order-item` | | | | | | | |
| `fluent-cart/update-transaction-status` | | | | | | | |

### product-extended-abilities.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-cart/bulk-edit-data` | | | | | | | |
| `fluent-cart/bulk-insert-products` | | | | | | | |
| `fluent-cart/bulk-update-products` | | | | | | | |
| `fluent-cart/create-dummy-products` | | | | | | | |
| `fluent-cart/do-product-bulk-action` | | | | | | | |
| `fluent-cart/fetch-products-by-ids` | | | | | | | |
| `fluent-cart/fetch-variations-by-ids` | | | | | | | |
| `fluent-cart/get-product-pricing` | | | | | | | |
| `fluent-cart/get-related-products` | | | | | | | |
| `fluent-cart/search-products-by-name` | | | | | | | |
| `fluent-cart/search-variants-by-name` | | | | | | | |
| `fluent-cart/sync-product-downloadable-files` | | | | | | | |
| `fluent-cart/update-product-manage-stock` | | | | | | | |
| `fluent-cart/update-product-pricing` | | | | | | | |
| `fluent-cart/update-variant-inventory` | | | | | | | |

### reports-abilities.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-cart/generate-retention-snapshots` | | | | | | | |
| `fluent-cart/get-dashboard-summary` | | | | | | | |
| `fluent-cart/get-product-performance` | | | | | | | |
| `fluent-cart/get-revenue-report` | | | | | | | |
| `fluent-cart/get-sales-growth` | | | | | | | |
| `fluent-cart/get-subscription-cohorts` | | | | | | | |
| `fluent-cart/get-top-products-sold` | | | | | | | |

### settings-abilities.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-cart/list-payment-methods` | | | | | | | |
| `fluent-cart/list-storage-drivers` | | | | | | | |
| `fluent-cart/update-payment-method` | | | | | | | |
| `fluent-cart/update-storage-driver` | | | | | | | |

### shipping-abilities.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-cart/create-shipping-class` | | | | | | | |
| `fluent-cart/create-shipping-method` | | | | | | | |
| `fluent-cart/create-shipping-zone` | | | | | | | |
| `fluent-cart/delete-shipping-zone` | | | | | | | |
| `fluent-cart/list-shipping-classes` | | | | | | | |
| `fluent-cart/list-shipping-zones` | | | | | | | |
| `fluent-cart/update-shipping-method` | | | | | | | |
| `fluent-cart/update-shipping-zone` | | | | | | | |

### subscription-extended-abilities.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-cart/cancel-subscription-auto-renew` | | | | | | | |
| `fluent-cart/fetch-subscription-from-vendor` | | | | | | | |
| `fluent-cart/generate-subscription-early-payment-link` | | | | | | | |
| `fluent-cart/reactivate-subscription` | | | | | | | |

### tax-abilities.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-cart/create-tax-class` | | | | | | | |
| `fluent-cart/create-tax-rate` | | | | | | | |
| `fluent-cart/delete-tax-class` | | | | | | | |
| `fluent-cart/delete-tax-rate` | | | | | | | |
| `fluent-cart/get-eu-vat-rates` | | | | | | | |
| `fluent-cart/list-tax-classes` | | | | | | | |
| `fluent-cart/update-eu-vat-config` | | | | | | | |
| `fluent-cart/update-tax-class` | | | | | | | |

## Fluent Forms — 80 new abilities

**Probe site:** wicked-community or helenawillow

### pro-abilities.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-forms/cancel-scheduled-action` | | | | | | | |
| `fluent-forms/export-entries` | | | | | | | |
| `fluent-forms/get-completion-rate` | | | | | | | |
| `fluent-forms/get-conversational-design` | | | | | | | |
| `fluent-forms/get-country-heatmap` | | | | | | | |
| `fluent-forms/get-form-preset` | | | | | | | |
| `fluent-forms/get-form-stats` | | | | | | | |
| `fluent-forms/get-overview-chart` | | | | | | | |
| `fluent-forms/get-quiz-attempt` | | | | | | | |
| `fluent-forms/get-quiz-config` | | | | | | | |
| `fluent-forms/get-revenue-chart` | | | | | | | |
| `fluent-forms/get-submissions-analysis` | | | | | | | |
| `fluent-forms/get-subscription` | | | | | | | |
| `fluent-forms/get-survey-html` | | | | | | | |
| `fluent-forms/get-survey-results` | | | | | | | |
| `fluent-forms/get-top-performing-forms` | | | | | | | |
| `fluent-forms/get-transaction` | | | | | | | |
| `fluent-forms/import-entries` | | | | | | | |
| `fluent-forms/list-order-items` | | | | | | | |
| `fluent-forms/list-payment-types` | | | | | | | |
| `fluent-forms/list-quiz-attempts` | | | | | | | |
| `fluent-forms/list-scheduled-actions` | | | | | | | |
| `fluent-forms/list-subscriptions` | | | | | | | |
| `fluent-forms/list-transactions` | | | | | | | |
| `fluent-forms/retry-scheduled-action` | | | | | | | |
| `fluent-forms/save-form-preset` | | | | | | | |
| `fluent-forms/update-conversational-design` | | | | | | | |

### write-abilities.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-forms/add-manager` | | | | | | | |
| `fluent-forms/add-submission-note` | | | | | | | |
| `fluent-forms/bulk-update-submissions` | | | | | | | |
| `fluent-forms/convert-form` | | | | | | | |
| `fluent-forms/create-form` | | | | | | | |
| `fluent-forms/create-form-confirmation` | | | | | | | |
| `fluent-forms/create-form-integration` | | | | | | | |
| `fluent-forms/create-form-notification` | | | | | | | |
| `fluent-forms/delete-form` | | | | | | | |
| `fluent-forms/delete-form-confirmation` | | | | | | | |
| `fluent-forms/delete-form-integration` | | | | | | | |
| `fluent-forms/delete-form-notification` | | | | | | | |
| `fluent-forms/delete-logs` | | | | | | | |
| `fluent-forms/delete-submission` | | | | | | | |
| `fluent-forms/delete-submission-logs` | | | | | | | |
| `fluent-forms/delete-submission-note` | | | | | | | |
| `fluent-forms/duplicate-form` | | | | | | | |
| `fluent-forms/export-form` | | | | | | | |
| `fluent-forms/get-form-conversion-stats` | | | | | | | |
| `fluent-forms/get-form-integration` | | | | | | | |
| `fluent-forms/get-form-notification` | | | | | | | |
| `fluent-forms/get-form-shortcodes` | | | | | | | |
| `fluent-forms/get-global-settings` | | | | | | | |
| `fluent-forms/get-integration-global-settings` | | | | | | | |
| `fluent-forms/get-integration-list-ids` | | | | | | | |
| `fluent-forms/get-integration-merge-fields` | | | | | | | |
| `fluent-forms/get-log-filters` | | | | | | | |
| `fluent-forms/global-search` | | | | | | | |
| `fluent-forms/import-form` | | | | | | | |
| `fluent-forms/list-all-submissions` | | | | | | | |
| `fluent-forms/list-available-integrations` | | | | | | | |
| `fluent-forms/list-form-confirmations` | | | | | | | |
| `fluent-forms/list-form-integrations` | | | | | | | |
| `fluent-forms/list-form-notifications` | | | | | | | |
| `fluent-forms/list-form-templates` | | | | | | | |
| `fluent-forms/list-form-views` | | | | | | | |
| `fluent-forms/list-logs` | | | | | | | |
| `fluent-forms/list-managers` | | | | | | | |
| `fluent-forms/list-role-capabilities` | | | | | | | |
| `fluent-forms/list-submission-logs` | | | | | | | |
| `fluent-forms/list-submission-notes` | | | | | | | |
| `fluent-forms/remove-manager` | | | | | | | |
| `fluent-forms/reset-form-analytics` | | | | | | | |
| `fluent-forms/set-role-capability` | | | | | | | |
| `fluent-forms/toggle-integration-status` | | | | | | | |
| `fluent-forms/toggle-submission-favorite` | | | | | | | |
| `fluent-forms/update-form` | | | | | | | |
| `fluent-forms/update-form-confirmation` | | | | | | | |
| `fluent-forms/update-form-integration` | | | | | | | |
| `fluent-forms/update-form-notification` | | | | | | | |
| `fluent-forms/update-global-settings` | | | | | | | |
| `fluent-forms/update-submission-status` | | | | | | | |
| `fluent-forms/update-submission-user` | | | | | | | |

## Fluent Bookings — 78 new abilities

**Probe site:** helenawillow ONLY

### abilities-booking-meta.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-booking/delete-booking-meta` | | | | | | | |
| `fluent-booking/set-booking-meta` | | | | | | | |

### abilities-calendar-integrations.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-booking/disconnect-calendar-integration` | | | | | | | |
| `fluent-booking/get-calendar-integration` | | | | | | | |
| `fluent-booking/list-calendar-conflicts` | | | | | | | |
| `fluent-booking/list-calendar-integrations` | | | | | | | |
| `fluent-booking/list-remote-calendars` | | | | | | | |

### abilities-calendar-meta.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-booking/delete-calendar-meta` | | | | | | | |
| `fluent-booking/get-calendar-landing-url` | | | | | | | |
| `fluent-booking/get-calendar-meta` | | | | | | | |
| `fluent-booking/set-calendar-meta` | | | | | | | |

### abilities-coupons.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-booking/create-coupon` | | | | | | | |
| `fluent-booking/delete-coupon` | | | | | | | |
| `fluent-booking/get-coupon` | | | | | | | |
| `fluent-booking/list-coupons` | | | | | | | |
| `fluent-booking/update-coupon` | | | | | | | |

### abilities-event-config.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-booking/get-event-buffers` | | | | | | | |
| `fluent-booking/get-event-notifications` | | | | | | | |
| `fluent-booking/get-event-redirect` | | | | | | | |
| `fluent-booking/update-event-buffers` | | | | | | | |
| `fluent-booking/update-event-notifications` | | | | | | | |
| `fluent-booking/update-event-redirect` | | | | | | | |

### abilities-event-location.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-booking/get-event-location-config` | | | | | | | |
| `fluent-booking/update-event-location-config` | | | | | | | |

### abilities-global-settings.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-booking/get-global-settings` | | | | | | | |
| `fluent-booking/get-onboarding-state` | | | | | | | |
| `fluent-booking/update-global-settings` | | | | | | | |
| `fluent-booking/update-onboarding-state` | | | | | | | |

### abilities-import.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-booking/import-bookings` | | | | | | | |

### abilities-license.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-booking/activate-license` | | | | | | | |
| `fluent-booking/deactivate-license` | | | | | | | |
| `fluent-booking/get-license-info` | | | | | | | |

### abilities-multi-host.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-booking/add-booking-host` | | | | | | | |
| `fluent-booking/get-booking-host` | | | | | | | |
| `fluent-booking/list-booking-hosts` | | | | | | | |
| `fluent-booking/remove-booking-host` | | | | | | | |
| `fluent-booking/update-booking-host-status` | | | | | | | |

### abilities-orders.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-booking/disable-payment-method` | | | | | | | |
| `fluent-booking/enable-payment-method` | | | | | | | |
| `fluent-booking/get-order` | | | | | | | |
| `fluent-booking/get-payment-method` | | | | | | | |
| `fluent-booking/get-transaction` | | | | | | | |
| `fluent-booking/list-orders` | | | | | | | |
| `fluent-booking/list-payment-methods` | | | | | | | |
| `fluent-booking/list-transactions` | | | | | | | |
| `fluent-booking/refund-transaction` | | | | | | | |
| `fluent-booking/update-payment-method-config` | | | | | | | |

### abilities-permissions.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-booking/get-current-user-permissions` | | | | | | | |
| `fluent-booking/get-user-permissions` | | | | | | | |
| `fluent-booking/list-permission-sets` | | | | | | | |
| `fluent-booking/revoke-user-permissions` | | | | | | | |
| `fluent-booking/set-user-permissions` | | | | | | | |

### abilities-reports.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-booking/get-event-conversion-report` | | | | | | | |
| `fluent-booking/get-host-report` | | | | | | | |
| `fluent-booking/get-revenue-report` | | | | | | | |
| `fluent-booking/get-time-distribution-report` | | | | | | | |

### abilities-reschedule.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-booking/reschedule-booking` | | | | | | | |

### abilities-slots.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-booking/check-slot-availability` | | | | | | | |
| `fluent-booking/get-available-slots` | | | | | | | |
| `fluent-booking/get-event-slot-config` | | | | | | | |

### abilities-team.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-booking/add-event-team-member` | | | | | | | |
| `fluent-booking/list-event-team-members` | | | | | | | |
| `fluent-booking/list-team-calendars` | | | | | | | |
| `fluent-booking/list-team-events` | | | | | | | |
| `fluent-booking/remove-event-team-member` | | | | | | | |
| `fluent-booking/update-team-calendar-members` | | | | | | | |

### abilities-webhooks.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-booking/create-webhook` | | | | | | | |
| `fluent-booking/delete-webhook` | | | | | | | |
| `fluent-booking/get-webhook` | | | | | | | |
| `fluent-booking/list-webhooks` | | | | | | | |
| `fluent-booking/test-webhook` | | | | | | | |
| `fluent-booking/update-webhook` | | | | | | | |

### abilities-zoom-twilio.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-booking/disconnect-zoom-account` | | | | | | | |
| `fluent-booking/get-twilio-config` | | | | | | | |
| `fluent-booking/get-zoom-account` | | | | | | | |
| `fluent-booking/list-zoom-accounts` | | | | | | | |
| `fluent-booking/send-booking-sms` | | | | | | | |
| `fluent-booking/update-twilio-config` | | | | | | | |

## Fluent Boards — 161 new abilities

**Probe site:** helenawillow ONLY

### abilities-admin.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-boards/activate-license` | | | | | | | |
| `fluent-boards/deactivate-license` | | | | | | | |
| `fluent-boards/get-board-menu-items` | | | | | | | |
| `fluent-boards/get-dashboard-view-settings` | | | | | | | |
| `fluent-boards/get-feature-modules` | | | | | | | |
| `fluent-boards/get-general-settings` | | | | | | | |
| `fluent-boards/get-license-status` | | | | | | | |
| `fluent-boards/get-storage-settings` | | | | | | | |
| `fluent-boards/list-admin-pages` | | | | | | | |
| `fluent-boards/onboard-first-board` | | | | | | | |
| `fluent-boards/save-feature-modules` | | | | | | | |
| `fluent-boards/save-general-settings` | | | | | | | |
| `fluent-boards/skip-onboarding` | | | | | | | |
| `fluent-boards/update-dashboard-view-settings` | | | | | | | |
| `fluent-boards/update-storage-settings` | | | | | | | |

### abilities-attachments.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-boards/add-task-attachment` | | | | | | | |
| `fluent-boards/delete-task-attachment` | | | | | | | |
| `fluent-boards/get-attachment-download-url` | | | | | | | |
| `fluent-boards/list-task-attachments` | | | | | | | |
| `fluent-boards/update-attachment-visibility` | | | | | | | |

### abilities-comments-replies.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-boards/create-task-comment-reply` | | | | | | | |
| `fluent-boards/delete-task-comment-reply` | | | | | | | |
| `fluent-boards/list-comments-and-activities` | | | | | | | |
| `fluent-boards/update-comment-privacy` | | | | | | | |
| `fluent-boards/update-task-comment-reply` | | | | | | | |
| `fluent-boards/upload-comment-image` | | | | | | | |

### abilities-crm.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-boards/associate-crm-contact-to-board` | | | | | | | |
| `fluent-boards/disassociate-crm-contact-from-board` | | | | | | | |
| `fluent-boards/get-crm-contact-on-board` | | | | | | | |
| `fluent-boards/list-crm-associated-boards` | | | | | | | |
| `fluent-boards/list-crm-associated-tasks` | | | | | | | |

### abilities-custom-fields.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-boards/create-custom-field` | | | | | | | |
| `fluent-boards/delete-custom-field` | | | | | | | |
| `fluent-boards/get-task-custom-field-values` | | | | | | | |
| `fluent-boards/list-custom-fields` | | | | | | | |
| `fluent-boards/save-task-custom-field-values` | | | | | | | |
| `fluent-boards/update-custom-field` | | | | | | | |
| `fluent-boards/update-custom-field-position` | | | | | | | |

### abilities-discovery.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-boards/duplicate-board` | | | | | | | |
| `fluent-boards/get-board-currencies` | | | | | | | |
| `fluent-boards/get-default-board-colors` | | | | | | | |
| `fluent-boards/has-data-changed` | | | | | | | |
| `fluent-boards/import-from-board` | | | | | | | |
| `fluent-boards/list-boards-by-type` | | | | | | | |
| `fluent-boards/list-boards-summary` | | | | | | | |
| `fluent-boards/list-pinned-boards` | | | | | | | |
| `fluent-boards/list-recent-boards` | | | | | | | |
| `fluent-boards/list-user-accessible-boards` | | | | | | | |
| `fluent-boards/list-user-admin-boards` | | | | | | | |
| `fluent-boards/pin-board` | | | | | | | |
| `fluent-boards/set-board-background` | | | | | | | |
| `fluent-boards/unpin-board` | | | | | | | |
| `fluent-boards/update-board-properties` | | | | | | | |
| `fluent-boards/upload-board-background-image` | | | | | | | |

### abilities-folders.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-boards/add-board-to-folder` | | | | | | | |
| `fluent-boards/create-board-invitation` | | | | | | | |
| `fluent-boards/create-folder` | | | | | | | |
| `fluent-boards/delete-board-invitation` | | | | | | | |
| `fluent-boards/delete-folder` | | | | | | | |
| `fluent-boards/list-board-invitations` | | | | | | | |
| `fluent-boards/list-folders` | | | | | | | |
| `fluent-boards/remove-board-from-folder` | | | | | | | |
| `fluent-boards/update-folder` | | | | | | | |

### abilities-import.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-boards/import-csv-to-board` | | | | | | | |
| `fluent-boards/import-fluent-boards-export` | | | | | | | |
| `fluent-boards/upload-csv` | | | | | | | |

### abilities-members-extended.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-boards/add-org-manager` | | | | | | | |
| `fluent-boards/bulk-add-board-members` | | | | | | | |
| `fluent-boards/get-member-info` | | | | | | | |
| `fluent-boards/get-org-managers` | | | | | | | |
| `fluent-boards/list-board-assignees` | | | | | | | |
| `fluent-boards/list-board-users` | | | | | | | |
| `fluent-boards/list-manager-boards` | | | | | | | |
| `fluent-boards/list-manager-tasks` | | | | | | | |
| `fluent-boards/list-manager-team-users` | | | | | | | |
| `fluent-boards/list-member-activities` | | | | | | | |
| `fluent-boards/list-member-associated-users` | | | | | | | |
| `fluent-boards/list-member-boards` | | | | | | | |
| `fluent-boards/list-member-tasks` | | | | | | | |
| `fluent-boards/list-top-tasks-for-boards` | | | | | | | |
| `fluent-boards/make-board-manager` | | | | | | | |
| `fluent-boards/make-board-member` | | | | | | | |
| `fluent-boards/make-board-viewer` | | | | | | | |
| `fluent-boards/remove-board-manager` | | | | | | | |
| `fluent-boards/remove-org-manager` | | | | | | | |

### abilities-notifications.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-boards/delete-notification` | | | | | | | |
| `fluent-boards/get-board-notification-settings` | | | | | | | |
| `fluent-boards/get-notification-count` | | | | | | | |
| `fluent-boards/get-user-notification-settings` | | | | | | | |
| `fluent-boards/list-unread-notifications` | | | | | | | |
| `fluent-boards/mark-all-notifications-as-read` | | | | | | | |
| `fluent-boards/mark-notification-as-read` | | | | | | | |
| `fluent-boards/save-board-notification-settings` | | | | | | | |
| `fluent-boards/save-user-notification-settings` | | | | | | | |

### abilities-repeat-tasks.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-boards/create-repeat-task-rule` | | | | | | | |
| `fluent-boards/list-repeat-task-rules` | | | | | | | |

### abilities-reports.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-boards/get-custom-report` | | | | | | | |
| `fluent-boards/get-stage-report` | | | | | | | |
| `fluent-boards/list-board-tasks-summary` | | | | | | | |

### abilities-search.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-boards/get-global-options` | | | | | | | |
| `fluent-boards/get-search-filters` | | | | | | | |
| `fluent-boards/get-search-suggestions` | | | | | | | |
| `fluent-boards/search-boards-and-tasks` | | | | | | | |

### abilities-stages-extended.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-boards/drag-stage` | | | | | | | |
| `fluent-boards/list-stage-default-assignees` | | | | | | | |
| `fluent-boards/reposition-stages` | | | | | | | |
| `fluent-boards/stage-archive-all-tasks` | | | | | | | |
| `fluent-boards/unset-stage-default-assignees` | | | | | | | |
| `fluent-boards/update-stage-default-assignees` | | | | | | | |
| `fluent-boards/update-stage-property` | | | | | | | |

### abilities-subtasks.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-boards/clone-subtask` | | | | | | | |
| `fluent-boards/convert-task-to-subtask` | | | | | | | |
| `fluent-boards/create-subtask` | | | | | | | |
| `fluent-boards/create-subtask-group` | | | | | | | |
| `fluent-boards/delete-subtask` | | | | | | | |
| `fluent-boards/delete-subtask-group` | | | | | | | |
| `fluent-boards/list-subtasks` | | | | | | | |
| `fluent-boards/move-subtask-to-board` | | | | | | | |
| `fluent-boards/move-subtask-to-group` | | | | | | | |
| `fluent-boards/update-subtask-group` | | | | | | | |
| `fluent-boards/update-subtask-position` | | | | | | | |

### abilities-tasks-extended.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-boards/add-task-cover-image` | | | | | | | |
| `fluent-boards/archive-task` | | | | | | | |
| `fluent-boards/assign-yourself-to-task` | | | | | | | |
| `fluent-boards/bulk-archive-tasks` | | | | | | | |
| `fluent-boards/bulk-delete-tasks` | | | | | | | |
| `fluent-boards/bulk-restore-tasks` | | | | | | | |
| `fluent-boards/bulk-task-actions` | | | | | | | |
| `fluent-boards/clone-task` | | | | | | | |
| `fluent-boards/detach-yourself-from-task` | | | | | | | |
| `fluent-boards/get-board-image-templates` | | | | | | | |
| `fluent-boards/list-archived-tasks` | | | | | | | |
| `fluent-boards/list-tasks-by-stage` | | | | | | | |
| `fluent-boards/move-task-to-next-stage` | | | | | | | |
| `fluent-boards/remove-task-cover-image` | | | | | | | |
| `fluent-boards/restore-task` | | | | | | | |
| `fluent-boards/update-task-dates` | | | | | | | |
| `fluent-boards/update-task-status` | | | | | | | |

### abilities-templates.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-boards/create-board-from-template` | | | | | | | |
| `fluent-boards/duplicate-board-as-template` | | | | | | | |
| `fluent-boards/get-template-detail` | | | | | | | |
| `fluent-boards/list-templates` | | | | | | | |

### abilities-time-tracking.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-boards/commit-time-track` | | | | | | | |
| `fluent-boards/get-active-time-track` | | | | | | | |
| `fluent-boards/get-task-duration-stats` | | | | | | | |
| `fluent-boards/get-task-time-report` | | | | | | | |
| `fluent-boards/get-user-time-report` | | | | | | | |
| `fluent-boards/list-task-duration` | | | | | | | |
| `fluent-boards/list-time-tracks` | | | | | | | |
| `fluent-boards/list-user-time-tracks` | | | | | | | |
| `fluent-boards/pause-time-track` | | | | | | | |
| `fluent-boards/resume-time-track` | | | | | | | |
| `fluent-boards/start-time-track` | | | | | | | |

### abilities-webhooks.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-boards/create-incoming-webhook` | | | | | | | |
| `fluent-boards/create-outgoing-webhook` | | | | | | | |
| `fluent-boards/delete-incoming-webhook` | | | | | | | |
| `fluent-boards/delete-outgoing-webhook` | | | | | | | |
| `fluent-boards/list-incoming-webhooks` | | | | | | | |
| `fluent-boards/list-outgoing-webhooks` | | | | | | | |
| `fluent-boards/update-incoming-webhook` | | | | | | | |
| `fluent-boards/update-outgoing-webhook` | | | | | | | |

## FluentCommunity — 53 new abilities

**Probe site:** wicked-community primary, helenawillow parity

### abilities-v2.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-community/add-reaction` | | | | | | | |
| `fluent-community/add-space-member` | | | | | | | |
| `fluent-community/bulk-add-space-members` | | | | | | | |
| `fluent-community/bulk-remove-space-members` | | | | | | | |
| `fluent-community/cast-survey-vote` | | | | | | | |
| `fluent-community/check-is-following` | | | | | | | |
| `fluent-community/create-member` | | | | | | | |
| `fluent-community/create-space-group` | | | | | | | |
| `fluent-community/create-topic` | | | | | | | |
| `fluent-community/delete-member` | | | | | | | |
| `fluent-community/delete-space-group` | | | | | | | |
| `fluent-community/delete-topic` | | | | | | | |
| `fluent-community/emit-event` | | | | | | | |
| `fluent-community/enroll-user-in-course` | | | | | | | |
| `fluent-community/get-course-enrollment` | | | | | | | |
| `fluent-community/get-crm-tagging-config` | | | | | | | |
| `fluent-community/get-customization-settings` | | | | | | | |
| `fluent-community/get-features-settings` | | | | | | | |
| `fluent-community/get-menu-settings` | | | | | | | |
| `fluent-community/get-notification-prefs` | | | | | | | |
| `fluent-community/get-privacy-settings` | | | | | | | |
| `fluent-community/get-profile-custom-fields` | | | | | | | |
| `fluent-community/get-quiz-results` | | | | | | | |
| `fluent-community/get-space-member` | | | | | | | |
| `fluent-community/get-survey-results` | | | | | | | |
| `fluent-community/get-survey-voters` | | | | | | | |
| `fluent-community/get-topic` | | | | | | | |
| `fluent-community/list-course-students` | | | | | | | |
| `fluent-community/list-quiz-attempts` | | | | | | | |
| `fluent-community/list-reactions` | | | | | | | |
| `fluent-community/list-topics` | | | | | | | |
| `fluent-community/list-unread-notifications` | | | | | | | |
| `fluent-community/mark-all-notifications-read` | | | | | | | |
| `fluent-community/mark-feed-notifications-read` | | | | | | | |
| `fluent-community/mark-notification-read` | | | | | | | |
| `fluent-community/remove-reaction` | | | | | | | |
| `fluent-community/remove-space-member` | | | | | | | |
| `fluent-community/search-members-mention` | | | | | | | |
| `fluent-community/submit-quiz-attempt` | | | | | | | |
| `fluent-community/sync-feed-topics` | | | | | | | |
| `fluent-community/sync-space-topics` | | | | | | | |
| `fluent-community/unenroll-user-from-course` | | | | | | | |
| `fluent-community/update-crm-tagging-config` | | | | | | | |
| `fluent-community/update-customization-settings` | | | | | | | |
| `fluent-community/update-features-settings` | | | | | | | |
| `fluent-community/update-member-status` | | | | | | | |
| `fluent-community/update-menu-settings` | | | | | | | |
| `fluent-community/update-notification-prefs` | | | | | | | |
| `fluent-community/update-privacy-settings` | | | | | | | |
| `fluent-community/update-profile-custom-fields` | | | | | | | |
| `fluent-community/update-space-group` | | | | | | | |
| `fluent-community/update-space-member-role` | | | | | | | |
| `fluent-community/update-topic` | | | | | | | |

## Fluent Messaging — 8 new abilities

**Probe site:** wicked-community primary, helenawillow parity

### abilities-v2.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-messaging/add-participant` | | | | | | | |
| `fluent-messaging/create-thread` | | | | | | | |
| `fluent-messaging/delete-message` | | | | | | | |
| `fluent-messaging/delete-thread` | | | | | | | |
| `fluent-messaging/mark-thread-read` | | | | | | | |
| `fluent-messaging/remove-participant` | | | | | | | |
| `fluent-messaging/update-message` | | | | | | | |
| `fluent-messaging/update-thread` | | | | | | | |

## FluentPlayer — 103 new abilities

**Probe site:** helenawillow primary, wicked-community parity

### abilities-analytics.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-player/analytics-devices` | | | | | | | |
| `fluent-player/analytics-location-breakdown` | | | | | | | |
| `fluent-player/analytics-new-returning-viewers` | | | | | | | |
| `fluent-player/analytics-performance-over-time` | | | | | | | |
| `fluent-player/analytics-retention` | | | | | | | |
| `fluent-player/analytics-stats` | | | | | | | |
| `fluent-player/analytics-top-users` | | | | | | | |
| `fluent-player/analytics-top-videos` | | | | | | | |
| `fluent-player/analytics-user-info` | | | | | | | |
| `fluent-player/analytics-user-retention` | | | | | | | |
| `fluent-player/analytics-user-stats` | | | | | | | |
| `fluent-player/analytics-user-top-videos` | | | | | | | |
| `fluent-player/analytics-video-devices` | | | | | | | |
| `fluent-player/analytics-video-location-breakdown` | | | | | | | |
| `fluent-player/analytics-video-retention` | | | | | | | |
| `fluent-player/analytics-video-stats` | | | | | | | |
| `fluent-player/analytics-video-top-users` | | | | | | | |

### abilities-bunny.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-player/bunny-storage-create-directory` | | | | | | | |
| `fluent-player/bunny-storage-delete-video` | | | | | | | |
| `fluent-player/bunny-storage-get-video` | | | | | | | |
| `fluent-player/bunny-storage-list-videos` | | | | | | | |
| `fluent-player/bunny-stream-create-collection` | | | | | | | |
| `fluent-player/bunny-stream-create-video` | | | | | | | |
| `fluent-player/bunny-stream-delete-collection` | | | | | | | |
| `fluent-player/bunny-stream-delete-video` | | | | | | | |
| `fluent-player/bunny-stream-list-collections` | | | | | | | |
| `fluent-player/bunny-stream-list-libraries` | | | | | | | |
| `fluent-player/bunny-stream-list-videos` | | | | | | | |
| `fluent-player/bunny-stream-update-collection` | | | | | | | |
| `fluent-player/bunny-stream-update-video` | | | | | | | |

### abilities-email.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-player/delete-email-collection` | | | | | | | |
| `fluent-player/export-email-collections` | | | | | | | |
| `fluent-player/get-email-collection` | | | | | | | |
| `fluent-player/get-integration-fields` | | | | | | | |
| `fluent-player/get-youtube-channel-info` | | | | | | | |
| `fluent-player/list-email-collections` | | | | | | | |
| `fluent-player/list-email-providers` | | | | | | | |
| `fluent-player/list-integrations` | | | | | | | |
| `fluent-player/list-layer-forms` | | | | | | | |
| `fluent-player/list-provider-resources` | | | | | | | |
| `fluent-player/list-smartcodes` | | | | | | | |
| `fluent-player/save-email-provider-settings` | | | | | | | |
| `fluent-player/save-integration-settings` | | | | | | | |
| `fluent-player/test-integration-connection` | | | | | | | |
| `fluent-player/validate-provider-field` | | | | | | | |

### abilities-license.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-player/activate-license` | | | | | | | |
| `fluent-player/deactivate-license` | | | | | | | |
| `fluent-player/get-license-details` | | | | | | | |

### abilities-media.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-player/create-media` | | | | | | | |
| `fluent-player/create-media-tag` | | | | | | | |
| `fluent-player/delete-media` | | | | | | | |
| `fluent-player/delete-media-tag` | | | | | | | |
| `fluent-player/get-media` | | | | | | | |
| `fluent-player/get-media-metadata` | | | | | | | |
| `fluent-player/list-media` | | | | | | | |
| `fluent-player/list-media-tags` | | | | | | | |
| `fluent-player/rename-media-tag` | | | | | | | |
| `fluent-player/search-media` | | | | | | | |
| `fluent-player/update-media` | | | | | | | |

### abilities-mux.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-player/mux-create-asset` | | | | | | | |
| `fluent-player/mux-create-live-stream` | | | | | | | |
| `fluent-player/mux-create-playback-restriction` | | | | | | | |
| `fluent-player/mux-create-signing-key` | | | | | | | |
| `fluent-player/mux-create-track` | | | | | | | |
| `fluent-player/mux-create-upload` | | | | | | | |
| `fluent-player/mux-delete-asset` | | | | | | | |
| `fluent-player/mux-delete-live-stream` | | | | | | | |
| `fluent-player/mux-delete-playback-restriction` | | | | | | | |
| `fluent-player/mux-delete-signing-key` | | | | | | | |
| `fluent-player/mux-delete-track` | | | | | | | |
| `fluent-player/mux-generate-track-subtitles` | | | | | | | |
| `fluent-player/mux-get-asset` | | | | | | | |
| `fluent-player/mux-get-asset-captions` | | | | | | | |
| `fluent-player/mux-get-delivery-usage` | | | | | | | |
| `fluent-player/mux-get-live-stream` | | | | | | | |
| `fluent-player/mux-get-upload-status` | | | | | | | |
| `fluent-player/mux-list-assets` | | | | | | | |
| `fluent-player/mux-list-live-streams` | | | | | | | |
| `fluent-player/mux-list-playback-restrictions` | | | | | | | |
| `fluent-player/mux-list-signing-keys` | | | | | | | |
| `fluent-player/mux-reset-stream-key` | | | | | | | |
| `fluent-player/mux-update-asset` | | | | | | | |
| `fluent-player/mux-update-asset-mp4-support` | | | | | | | |

### abilities-playlists.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-player/create-playlist` | | | | | | | |
| `fluent-player/delete-playlist` | | | | | | | |
| `fluent-player/generate-youtube-storyboard` | | | | | | | |
| `fluent-player/get-playlist` | | | | | | | |
| `fluent-player/get-youtube-captions` | | | | | | | |
| `fluent-player/import-youtube-captions` | | | | | | | |
| `fluent-player/list-playlists` | | | | | | | |
| `fluent-player/remove-subtitle` | | | | | | | |
| `fluent-player/update-playlist` | | | | | | | |
| `fluent-player/update-timed-content` | | | | | | | |
| `fluent-player/upload-subtitle` | | | | | | | |

### abilities-presets.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-player/create-preset` | | | | | | | |
| `fluent-player/delete-preset` | | | | | | | |
| `fluent-player/get-preset` | | | | | | | |
| `fluent-player/get-settings` | | | | | | | |
| `fluent-player/get-settings-section` | | | | | | | |
| `fluent-player/list-presets` | | | | | | | |
| `fluent-player/reset-settings` | | | | | | | |
| `fluent-player/update-preset` | | | | | | | |
| `fluent-player/update-settings` | | | | | | | |

