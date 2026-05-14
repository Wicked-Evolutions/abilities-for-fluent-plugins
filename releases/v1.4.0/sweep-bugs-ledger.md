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

**Likely scope:** any `update-*` / `create-*` / `set-*` mutating ability. Sweep continuation will surface comprehensive scope.

### Class D — SQL drift (callback queries missing/wrong columns)

Callback's SQL query uses column names that don't exist in the vendor table (or in the deployed schema variant). Result is either a SQL error or a query that silently returns empty/wrong data.

**Fix shape:** per-callback verification of column names against vendor source for the deployed plugin version. Same bug class as KD-1/KD-2 (v1.1.3 CPT/schema drifts) but in new v2 code paths.

**Likely scope:** any callback that constructs raw SQL or builds Eloquent queries with explicit column references against vendor models.

### Class E — Request signature mismatch

Callback invokes a vendor controller method that expects a `Request` object as parameter, but the callback passes a plain array. Result is a PHP type error at the vendor boundary.

**Fix shape:** wrap inputs in the proper `\FluentX\...\Request` instance before invoking the controller method. Similar pattern to the Player Request-constructor bug class that PR #57's `fluent_abilities_player_invoke_controller()` helper resolved — may benefit from an equivalent helper for the affected plugin.

**Likely scope:** any callback that bypasses a Request-wrapping helper and directly invokes vendor controller methods.

### Class G — API consistency violation (input-parameter naming)

Input schema uses an inconsistent parameter name compared to its sibling abilities. Examples: `get-form` requires `id` but every other form-scoped ability uses `form_id`; `get-submission` requires `id` but every other submission-scoped ability uses `submission_id`. Not a crash — operator passes `form_id`, ability rejects, operator can recover by passing `id`. But every LLM operator following convention will trip until they figure out the exception.

**Fix shape:** either rename the input param to match siblings (preferred — consistent API design), OR accept both names in the input schema (backwards-compat for any current consumers). The latter has Stable Contracts implications — adding an alternative param name is additive (safe).

**Likely scope:** any ability whose input schema diverges from its plugin's dominant entity-id naming convention. May exist in other plugins too.

### Class F — Eloquent / Laravel-paginator serialization leak

Callback returns a raw Eloquent model or Laravel paginator object instead of calling `->toArray()` first. `structuredContent` in the response leaks internal model traits (`incrementing`, `preventsLazyLoading`, `exists`, `wasRecentlyCreated`, `timestamps`, `usesUniqueIds`) or paginator internals (`onEachSide`) instead of the actual data. Text content is correct (because `__toString` works); structured content is corrupted.

**Fix shape:** call `->toArray()` (or `->jsonSerialize()`) on the model/paginator before returning. Likely one helper / wrapper pattern to fix once and apply across affected callbacks.

**Likely scope:** any callback that returns Eloquent models / paginators directly — heavy in CRM (campaign, funnel, sequence, label, template ops); likely present in other plugins too.

## Per-chat findings

### Chat 1 — Claude · FluentCRM (COMPLETE — 225/225 + baseline 81 exercised)

**Status:** full sweep complete via batch-execute on wicked-community. All `[SPRINT-V2-TEST-CRM]` fixtures cleaned.

**Classifications:**

| Bucket | Count |
|---|---|
| ✅ pass | (passed abilities not enumerated; remaining after deductions) |
| **product bug** | **41** |
| vendor precondition | 14 |
| operator-pattern (input-shape misfires, recovered) | ~30 |
| permission gate / adapter scope / client limitation | 0 |

**Audit:** ✅ Clean. Zero `[SPRINT-V2-TEST-CRM]` residue across tags / lists / labels / webhooks / sequences / automations / editor patterns / contacts / notes / campaigns.

**41 product bugs by class:**

**Class A — empty-array-normalization (24 abilities):**
`get-system-logs`, `list-experiments-campaigns`, `list-import-drivers`, `list-fluent-forms-templates`, `list-email-patterns`, `list-docs-addons`, `list-campaign-emails`, `get-funnel-all-activities`, `list-subscriber-automations`, `get-contact-purchase-history`, `get-contact-form-submissions`, `get-contact-support-tickets`, `get-contact-info-widgets`, `search-contacts-fast`, `get-report-recent-tags`, `get-report-top-campaigns`, `list-report-emails`, `get-report-advanced-providers`, `get-report-contacts-by-tags`, `get-report-contacts-by-lists`, `get-report-automations`, `list-pro-managers`, `list-form-entries`, `list-campaigns-pro-post-taxonomies`

**Class F — Eloquent/paginator serialization leak (~19 abilities — single root helper):**
Reads: `list-recurring-campaigns`, `list-campaign-unsubscribers`, `get-campaign-contacts-by-segment`, `get-campaign-processing-stat`, `list-funnel-subscribers`, `list-sequences-for-subscriber`, `get-funnel-subscriber-detail`, `list-templates-all`, `get-report-automation-steps`
Writes: `create-label`, `update-label`, `update-campaign-title`, `update-campaign`, `update-single-campaign-property`, `schedule-campaign`, `duplicate-campaign`, `duplicate-sequence`, `clone-funnel`, `change-funnel-trigger`

**Class B — output type mismatch / PHP fatals (14 abilities):**
Type mismatch: `list-campaigns` (⚠️ POTENTIAL BASELINE REGRESSION — `scheduled_at: null` for drafts but schema non-nullable), `list-templates` (⚠️ POTENTIAL BASELINE REGRESSION — every template returns `id: 0`), `get-doc`, `get-commerce-report`, `get-old-logs`, `create-recurring-campaign`, `update-subscribers-property`, `get-campaign-stats` (returns plain string "No email sends found" instead of zero-state object)

PHP fatals (5 abilities): `list-funnel-templates`, `list-dynamic-segment-custom-fields`, `list-commerce-reports-for-provider` (`Call to undefined get_woocommerce_currency_symbol`), `list-campaigns-pro-products` (`Call to undefined wc_get_products`), `sync-subscribers-segments` (`count() on null`), `create-pro-manager` (`Validator::__construct null`)

**Class C — logic/input-handling (1 ability):**
`create-dynamic-segment` — returns "Please provide segment title" even when `title` IS in input. Reads from wrong place.

**⚠️ Two POTENTIAL BASELINE REGRESSIONS flagged** (`list-campaigns`, `list-templates`):
These are in the v1.1.3 preserved-baseline 81 abilities. If they ARE regressions introduced by v1.4.0 work, that's a **Principle 10 Stable Contracts violation** that must be fixed pre-release. Phase C Step 2 Stable Contracts diff showed 0 schema mismatches across shared slugs, but BEHAVIOR could have shifted via shared helper changes. Needs targeted re-test against v1.1.3 baseline to confirm.

**Vendor preconditions (14 — not bugs, just unprovisioned on wicked-community):**
- Smart Links table absent
- Companies table absent
- Abandoned cart table absent
- Event Tracker not enabled
- AI provider not configured
- WooCommerce not active (separately bugs as PHP fatals above)
- No sequences / recurring campaigns exist (expected 404 path)

**Operator-pattern inconsistencies (~30 — API design observations, not bugs):**
Field-name inconsistency across abilities (id vs funnel_id vs campaign_id vs contact_id, q vs filters, doc_id, etc.). Tag/list-attach typed as comma-separated string not array. Worth a design pass before alpha but doesn't block release.

**Working findings file (Chat 1 side):** `/tmp/sprint-v2-crm-findings.md` — detailed per-ability evidence.

### Chat 2 — Claude · Fluent Boards + Messaging (BLOCKED mid-sweep, partial: ~60 abilities + production residue)

**Status:** sweep blocked mid-Boards run after ~60 abilities executed. Bridge crashed in same OAuth-degraded-mode pattern as Chat 3 hit. MCP server fully disconnected; teardown could not run.

**⚠️ CRITICAL — production residue on helenawillow:**

Marker `[SPRINT-V2-TEST-BOARDS]` — 10 fixtures left behind:
- Board id=24 (parent — cascade-delete should remove most children)
- Stage 208 ("Stage Alpha v2")
- Task 1159 ("Task One updated")
- Comment 34 + reply 35
- Subtask group 74 + subtask 1160
- Task 1161 (clone of 1159)
- Label 210
- Custom field 209
- Incoming webhook 44
- Folder 25 (separate from board)

Marker `[SPRINT-V2-TEST-MSG]` — zero residue (messaging never started).

**Cleanup plan dispatched to Chat 2 (cascade-first):** delete board id=24 → verify cascade → delete folder 25 + webhook 44 separately → audit clean before resuming sweep.

**Bridge crash root cause:** same as Chat 3. `wickedevolutions` OAuth refresh expired → bridge entered sticky degraded mode → all tool calls blocked (even healthy helenawillow per wp_bridge_health) → MCP server fully disconnected. Worth filing on `abilities-mcp` repo post-sweep — should be per-site quarantine, not global. Also "fully crashed and disconnected" beyond just degraded-mode is a separate fragility (not just sticky OAuth state).

**Bridge ergonomics observation:** rate limit trips at ≥5 parallel calls (`-32099` error). Throttle to ≤3 in production sweep batches.

**Findings before crash:**

| # | Ability slug | Class | Issue |
|---|---|---|---|
| 22 | `fluent-boards/list-board-assignees` | **F** | `array_map(): Argument #2 must be of type array, FluentForm\Framework\Support\Collection given`. Same Class F as CRM — missing `->toArray()` on Collection. |
| 23 | `fluent-boards/start-time-track` | suspected B/C | 3 attempts → "Connection closed". Uncaught exception in handler suspected. |
| 24 | `fluent-boards/mark-all-notifications-as-read` | suspected B/C | "Connection closed". Pattern same as #23. |
| 25 | `fluent-boards/delete-notification` | suspected B/C | "Connection closed". Pattern same as #23. |

**Class G findings — schema-vs-handler drift (~12 abilities affected):**
Discovery input_schema doesn't match what handlers actually require. Examples:
- `get-board` / `get-task` / `update-task` need `id` (not `board_id` / `task_id`)
- `update-stage` / `create-subtask` / `list-subtasks` need extra `board_id`
- `update-custom-field` / `save-task-custom-field-values` need `custom_field_id`
- `update-label` needs `id`
- `get-board-image-templates` / `get-board-menu-items` / `get-active-time-track` claim no-arg but require IDs
- `has-data-changed` needs `last_check_at`
- `update-comment-privacy` needs `privacy`

**AI-consumer hostile pattern.** Sweeping the suite blind is impossible from discovery alone. Same defect class as Forms `get-form` / `get-submission` (Class G), but broader scope on Boards.

**Other observations:**
- `create-outgoing-webhook` requires resolvable target URL (vendor precondition — `example.invalid` rejected; expected behavior)
- `move-task-to-next-stage` errors when task is already in last stage (operator-pattern — correct vendor state semantics)

**Action awaiting J:**
1. ✅ Reauth `wickedevolutions` (orchestrator gave paste command)
2. Restart MCP server / Claude Code session to clear cached degraded state
3. Re-dispatch Chat 2 with cleanup-first prompt (orchestrator drafted)
4. Decide whether 1 confirmed + 3 suspected product bugs + ~12 Class G drift = v1.4.0 hold

### Chat 3 — Claude · Fluent Forms + Bookings (BLOCKED mid-sweep, partial: 32 Forms abilities)

**Status:** sweep blocked mid-Forms-readonly batch. 32 abilities executed; remaining ~56 Forms + 78 Bookings pending bridge recovery.

**Blocker (operator-pattern, NOT a product bug):** abilities-mcp bridge entered sticky degraded mode. `wickedevolutions` + `abilitiesforai` OAuth refresh tokens expired (`invalid_grant`). Bridge refuses ALL tool calls (including healthy helenawillow) while any registered site is in this state. Recovery: J runs reauth commands for both sites (see orchestrator chat).

**Bridge design observation (file as adapter follow-up after sweep):** "all-or-nothing" degraded mode means any one site's expired token blocks the whole bridge. Worth filing on `abilities-mcp` repo — should be per-site quarantine, not global. Not v1.4.0 release blocker; affects sweep ergonomics.

**Findings before bridge degraded (32 executed):**

| Bucket | Count |
|---|---|
| ✅ pass | 24 |
| **product bug** (Class G — API consistency) | **2** |
| operator-pattern (bridge-degraded "Connection closed") | 3 (re-run after reauth) |
| client limitation (self-corrected) | 1 |

**2 product bugs — Class G API consistency:**

| # | Ability slug | Class | Issue |
|---|---|---|---|
| 20 | `fluent-forms/get-form` | **G** | Requires `id` parameter; every sibling form-scoped ability uses `form_id`. Works when called with `id` (not crash; consistency violation). |
| 21 | `fluent-forms/get-submission` | **G** | Requires `id` parameter; every sibling submission-scoped ability uses `submission_id`. Works when called with `id`. |

J disposition request: release blocker or v1.4.1 follow-up? (Inconsistency will trip every LLM operator following convention until they discover the exception.)

**Audit:** ✅ Clean. Zero `[SPRINT-V2-TEST-FORMS]` / `[SPRINT-V2-TEST-BOOKING]` fixture records — sweep blocked before any create phase. Production data on helenawillow untouched.

**Helena context (informational):** 7 forms / 183 submissions / 336 logs / 2 active integration feeds (fluentcrm + fluent_community). Empty surfaces (legitimate, not bugs): payments/transactions/subscriptions/managers/scheduled-actions/available-integrations/form-views.

**To resume:** orchestrator chat has paste-ready reauth commands for J. After reauth + bridge restart, Chat 3 re-runs scope: 3 connection-closed reruns + remaining ~56 Forms abilities + full 78 Bookings.

### Chat 4 — GPT 5.5 · FluentCart + FluentCommunity (COMPLETE — 161/161)

**Status:** sweep complete. 161 / 161 abilities executed (108 Cart + 53 Community).

**Classifications:**

| Bucket | Count |
|---|---|
| ✅ pass | 120 |
| vendor precondition / operator-pattern (mixed) | 24 |
| adapter scope / client surface | 4 |
| **product bug** | **13** |
| permission gate | 0 |

**Product bugs (13 total):**

| # | Ability slug | Class | Failure |
|---|---|---|---|
| 7 | `fluent-cart/update-product-pricing` | **C** | Returns `success: true` but `min_price` / `max_price` NOT persisted. Read-back confirms unchanged. |
| 8 | `fluent-cart/update-variant-inventory` | **D** | SQL drift — missing `stock_quantity` column reference. |
| 9 | `fluent-cart/search-variants-by-name` | **D** | SQL drift — query missing `title` column reference. |
| 10 | `fluent-cart/create-shipping-zone` | **C/D** | Returns success but read-back shows blank title/regions; `update-shipping-zone` then fails on missing title. |
| 11 | `fluent-cart/create-shipping-method` | **C** | Returns success but `list-shipping-methods` shows none. |
| 12 | `fluent-cart/create-shipping-class` | **C** | Returns success but read-back has blank title/slug; no delete surface. |
| 13 | `fluent-community/create-course` | **C** | Returns success but creates a `type=community` space instead of course — course APIs cannot find it. |
| 14 | `fluent-community/bulk-add-space-members` | **C** | Marked `idempotent: true` but creates duplicate membership row on second call. |
| 15 | `fluent-community/update-privacy-settings` | **E** | PHP type error — controller expects `Request` object, ability passes array. |
| 16 | `fluent-community/update-profile-custom-fields` | **D** | SQL drift — missing `custom_fields` column. |
| 17 | FluentCommunity follow-graph abilities | **D / vendor boundary** | Missing `wp_2_fcom_followers` table — borderline classification (vendor migration gap vs callback assumption). |
| 18 | `fluent-cart` customization-settings helpers | adapter scope | Customization settings helpers unavailable. |
| 19 | `fluent-community` notification prefs + quiz attempts | adapter scope / vendor precondition | Notification prefs model + quiz attempts table unavailable. |

**Audit:**
- ✅ Searchable Cart + Community surfaces marker-clean
- ✅ No `[SPRINT-V2-TEST-CART]` / `[SPRINT-V2-TEST-COMM]` fixture records remain
- ✅ Cart products / customers / coupons / attributes / tax / shipping zones / media downloads cleaned
- ✅ Community spaces / feeds / comments / media / topics / groups cleaned
- ⚠️ Cart order shells cancelled + stripped where no `delete-order` ability exists (testclient §3 in-run cleanup pairing partial — same scope observation as earlier; not a bug)
- ⚠️ Settings marker keys read back as `null` (not marker-bearing values) — confirms Class C silent-persistence pattern across multiple settings update abilities

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
