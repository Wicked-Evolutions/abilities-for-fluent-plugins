# Chat 1 — Claude cold-AI operator sweep — FluentCRM

## Mission

You are an AI operator running the **fresh-context exhaustive cold-AI sweep** for v1.4.0 release of `abilities-for-fluent-plugins`. This is the release gate — **release is blocked until J accepts your evidence**.

You own **225 new abilities** across FluentCRM. Your job:

1. Discover the live ability surface on the probe site
2. Execute each owned ability via the MCP adapter, capturing input + result
3. Classify each result per the 6-bucket failure taxonomy
4. Fill the ledger rows below
5. Cleanup all fixtures per testclient discipline
6. Report completion + audit to orchestrator

## Build identity (what's deployed)

| | |
|---|---|
| **Plugin version** | `1.4.0` |
| **Build SHA** | integration HEAD `72668c0` |
| **Zip filename** | `abilities-for-fluent-plugins-v1.4.0-pre-release.e043554.zip` |
| **Zip sha256** | `fe6150021a11cb9ad3f9f7c7c7010387b06752b1604784d9e5101daedf0e6149` |

## Probe site

| | |
|---|---|
| **Site** | `wicked-community` |
| **MCP tool** | `mcp__wordpress__mcp-adapter-discover-abilities` / `mcp__wordpress__mcp-adapter-execute-ability` with `site=wicked-community` |
| **First action** | Run discovery for your owned plugin categories. Confirm the ability count matches the ledger. If it doesn't, STOP and report to orchestrator. |

## Testclient discipline (BINDING)

Every fixture you create MUST follow this discipline:

1. **Marker prefix on titles/labels:** `[SPRINT-V2-TEST-CRM]` (your chat-specific suffix). Examples:
   - Contact email: `sprint-test+v2-crm-{cluster}@wickedevolutions.com`
   - Tag/list/segment name: `[SPRINT-V2-TEST-CRM] cluster-X smoke`
2. **Email fixtures:** pattern `sprint-test+v2-{slug}@wickedevolutions.com` where {slug} is a hint for the ability cluster.
3. **Central test contact:** `sprint-test+v2@wickedevolutions.com` — shared across all 5 chats for cross-plugin contact references. Created at sweep start by orchestrator. Do NOT delete it; orchestrator deletes at end.
4. **In-run cleanup:** every create test is paired with an explicit delete in the same captured ability-execution sequence. No fixture intentionally left behind.
5. **Fixture-only operations:** write/update/delete ONLY target records your chat created (matched by marker prefix). NEVER touch real records.
6. **Per-batch audit:** after completing your owned plugin(s), run `wp db query` or equivalent to confirm zero `[SPRINT-V2-TEST-CRM]`-prefixed records remain. Report audit-clean evidence.

## 6-bucket failure classification

For any failing call, classify into ONE bucket:

| Bucket | Meaning | Continue? |
|---|---|---|
| **product bug** | The v1.4.0 ability code is wrong (registration shape, callback logic, schema mismatch) | STOP and report to orchestrator immediately — release blocker until fixed |
| **vendor precondition** | Vendor module / table / credentials absent on this probe site | Continue; classify and move on |
| **permission gate** | `permission_callback` correctly denied; expected behavior for the auth context | Continue; classify and move on |
| **adapter scope** | MCP adapter scope/grant prevents execution | Continue; classify and report at end. Tracked under `abilities-mcp-adapter` #116; J decides whether this blocks release |
| **client limitation** | You (as cold AI) cannot handle / form / display the call or result. Schema/description improvement opportunity | Continue; classify and capture the issue type |
| **operator-pattern issue** | You made a semantically-wrong call (wrong IDs, wrong order). Coaching opportunity, not a release blocker | Continue; classify and capture |

## Ledger output format

For each row in the ledger below, fill the 8 columns:

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |

- **Site:** `wicked-community`
- **Client:** `Claude` (this session's identity)
- **Input (key):** brief summary of the input you used (e.g., `{contact_id: 48}` or `created via 5.1.1`)
- **Result:** `✅ pass` if data returned (or expected typed WP_Error like permission denial); `❌ fail` with brief reason otherwise
- **Classification:** one of the 6 buckets above (only for failures or expected denials)
- **Cleanup:** `n/a` for reads, `delete OK` after in-run delete, `audit clean` after batch audit
- **Notes:** anything noteworthy (schema gaps, slow responses, unclear descriptions, etc.)

## When you're done

1. All ledger rows filled
2. Per-batch audit clean
3. Report to J/orchestrator: "Chat 1 sweep complete. N abilities executed. Classifications: product-bug=X, vendor-precondition=Y, permission-gate=Z, adapter-scope=W, client-limitation=V, operator-pattern=U. Audit: clean."

If you find any `product bug` classifications, STOP and report immediately — orchestrator reactivates the dev chat to fix before you continue.

---

# Ledger fragment — FluentCRM

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
