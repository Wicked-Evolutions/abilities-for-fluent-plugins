# Cold-AI sweep — bug findings ledger

> **Phase C Step 7 — running bug catalogue.** Orchestrator-maintained. Updated as each cold-AI chat surfaces classified findings. Fix wave triggered once all 5 chats report complete (Path A — continue parallel sweep, bulk-fix at end).
>
> **Build under test:** plugin v1.4.0, integration HEAD `72668c0`, zip sha256 `fe6150...`
> **Probe sites:** wicked-community + helenawillow

## Defect classes (running)

### Class A — Empty-array-normalization (list-* abilities)

`list-*` callbacks return `null`/`object`/`""` for an array-typed `output_schema` field when the underlying data set is empty. Adapter rejects on `output[X] is not of type array`.

**Fix shape:** every list-* callback coerces the array-typed field to `[]` before return on empty-data paths.

**Likely scope:** any `list-*` / `get-*-list` ability in v1.4.0 that returns vendor data without explicit empty-array normalization. Could be present across multiple plugins.

### Class B — PHP type-fatal in callback

Callback code accesses a string offset as if array (or similar PHP type confusion). True PHP fatal-class error, not a schema mismatch.

**Fix shape:** targeted per-callback type-guard.

## Per-chat findings

### Chat 1 — Claude · FluentCRM (in progress)

**Status:** continuing sweep after first 20-ability batch surfaced 6 bugs. Re-instructed to catalogue all remaining ~205 abilities, NOT pause for fix.

**Findings so far (Batch 1):**

| # | Ability slug | Class | Failure | Source |
|---|---|---|---|---|
| 1 | `fluent-crm/get-system-logs` | A | `output[logs] is not of type array` — vendor returns object/null when empty | Chat 1 Batch 1 |
| 2 | `fluent-crm/get-old-logs` | A | `output[error] is not of type string` (error pathway non-string) | Chat 1 Batch 1 |
| 3 | `fluent-crm/list-experiments-campaigns` | A | `output[campaigns] is not of type array` | Chat 1 Batch 1 |
| 4 | `fluent-crm/list-import-drivers` | A | `output[drivers] is not of type array` | Chat 1 Batch 1 |
| 5 | `fluent-crm/list-funnel-templates` | **B** | PHP type error: Cannot access offset of type string on string | Chat 1 Batch 1 |
| 6 | `fluent-crm/list-fluent-forms-templates` | A | `output[templates] is not of type array` | Chat 1 Batch 1 |

**Audit:** clean (readonly-only Batch 1; no fixtures created).

### Chat 2 — Claude · Fluent Boards + Messaging
_(not started)_

### Chat 3 — Claude · Fluent Forms + Bookings
_(not started)_

### Chat 4 — GPT 5.5 · FluentCart + FluentCommunity
_(not started)_

### Chat 5 — GPT 5.5 · FluentPlayer
_(not started)_

## Fix-wave plan (triggered when all 5 chats report complete)

1. **Audit code for Class A pattern across all v1.4.0 plugins.** Grep for `list-*` ability registrations whose `execute_callback` returns vendor responses without `?? []` normalization on array-typed fields. May surface bugs beyond what cold-AI sweep catches in finite-time sampling.
2. **Reactivate parked dev chats per plugin** where bugs were found. Dev chat receives the consolidated bug list for its plugin.
3. **Per-plugin fix on feature branch** → merge to integration. Each dev chat owns its plugin's fixes.
4. **Rebuild + redeploy** pre-release zip from integration HEAD post-fix.
5. **Targeted re-sweep** — affected cold-AI chats re-execute the previously-failing slugs + a representative sample of same-class abilities (regression-guard).
6. **Re-audit + report.** Continue Phase C from Step 8 once all bug fixes confirmed live.

## Dev-chat reactivation reference

| Plugin | Feature branch | Dev worktree |
|---|---|---|
| FluentCRM | `feat/fluentcrm-registrar` | `~/my-agent/wordpress-plugins-temp/abilities-for-fluent-plugins.crm/` |
| FluentCart | `feat/fluentcart-registrar` | `~/my-agent/wordpress-plugins-temp/abilities-for-fluent-plugins.cart/` |
| Fluent Forms | `feat/fluentforms-registrar` | `~/my-agent/wordpress-plugins-temp/abilities-for-fluent-plugins.forms/` |
| Fluent Bookings | `feat/fluentbookings-registrar` | `~/my-agent/wordpress-plugins-temp/abilities-for-fluent-plugins.booking/` |
| Fluent Boards | `feat/fluentboards-registrar` | `~/my-agent/wordpress-plugins-temp/abilities-for-fluent-plugins.boards/` |
| FluentCommunity + Messaging | `feat/fluentcommunity-registrar` | `~/my-agent/wordpress-plugins-temp/abilities-for-fluent-plugins.community/` |
| FluentPlayer | `feat/fluentplayer-registrar` | `~/my-agent/wordpress-plugins-temp/abilities-for-fluent-plugins.player/` |
