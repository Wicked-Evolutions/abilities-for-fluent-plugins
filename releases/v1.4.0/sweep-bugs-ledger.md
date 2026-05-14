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

### Class C — Silent persistence failure

Callback returns `success: true` but the requested write does NOT persist. Confirmed by immediate read-back returning unchanged data. **More severe than Class A** — operator thinks the action succeeded; data wasn't written. Cascading bad assumptions / data loss / wasted operator action.

**Fix shape:** trace the callback's write path. Likely cause: `where()` clause that doesn't match (wrong column / wrong record-resolution / type coercion), or vendor service method returning success-shape without commit. Per-callback investigation; not a generic pattern fix like Class A.

**Likely scope:** any `update-*` / `set-*` mutating ability. Sweep continuation will surface comprehensive scope.

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

### Chat 4 — GPT 5.5 · FluentCart + FluentCommunity (in progress)

**Status:** 37 new FluentCart abilities executed; paused at first product-bug per "STOP and report" protocol. Re-instructed to continue cataloguing through remaining ~71 Cart abilities + 53 Community abilities.

**Findings so far (Cart batch, 37 abilities executed):**

| # | Ability slug | Class | Failure | Source |
|---|---|---|---|---|
| 7 | `fluent-cart/update-product-pricing` | **C** | Returns `success: true` but `min_price` / `max_price` NOT persisted. Confirmed by immediate read-back via `get-product-pricing` + `fetch-products-by-ids` — both still report 0. | Chat 4 Cart batch |

**Audit (partial — Cart side, marker `[SPRINT-V2-TEST-CART]`):**
- ✅ Searchable products / customers / community surfaces clean of marker residue
- ✅ In-run cleanup completed: product 140, customers 9 + 10, customer address 14, order items 40 + 42
- ⚠️ **Cart orders 29 and 30 cancelled + stripped of marker-bearing data, but NOT deleted** — Cart's v2 surface has no `delete-order` ability in scope. Orphan-but-anonymized.

**Scope observation (not a bug):** Cart v2 lacks `delete-order` ability — testclient §3 in-run cleanup pairing is partial for order-creating tests. Cancellation + data-stripping is the best available cleanup path. Acceptable per Cart Phase B research scope (no delete-order ability cited). Worth noting in operator docs.

### Chat 5 — GPT 5.5 · FluentPlayer (COMPLETE — adapter-scope-blocked)

**Status:** sweep complete. 103 of 103 Player abilities executed; **all 103 classified as `adapter scope`** — release-gate evidence of authorization blockage, NOT a FluentPlayer product failure.

| Bucket | Count |
|---|---|
| adapter scope | 103 (read 55 + write 32 + delete 16) |
| product bug | 0 observed |
| vendor precondition / permission gate / client limitation / operator-pattern | 0 observed |

**Root cause:** adapter's OAuth client lacks `abilities:fluent-player:{read,write,delete}` scopes. Discovery succeeded for all 103 abilities on helenawillow; every execution returned `Required scope: abilities:fluent-player:{read|write|delete}`.

**Audit:** clean — no creates reached the product layer, no fixtures created, zero `[SPRINT-V2-TEST-PLAYER]` residue.

**Tracked under:** [`abilities-mcp-adapter` #116](https://github.com/Wicked-Evolutions/abilities-mcp-adapter/issues/116) (Principle 9 ScopeRegistry coverage gap, filed pre-sweep as a known release follow-up).

**Authoritative Player code verification:** PR #57 Phase B live verification via direct `WP_Ability::execute()` (Boards-precedent transport deviation). 41 successful executions + intentional typed errors + 12 testclient-skipped + 2 vendor-scalar successes = 63 reps across all 17 sub-clusters. Build identity sha256 `1cdc2406…`. Reviewer-ratified at round-7-redux.

**Release decision required:** does adapter-scope-blocked cold-AI Mode-C sweep evidence + PR #57's wp-eval direct-execute evidence together satisfy the release-gate test bar for Player, OR does release block until adapter scope coverage lands?

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
