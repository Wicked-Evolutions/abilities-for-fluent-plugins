# Changelog

All notable changes to Abilities for Fluent Plugins will be documented in this file.

## [1.4.0] - 2026-05-18

> **Sprint:** [Fluent Suite Registrar Bundle Sprint 2026-05-13](../../00%20Influencentricity%20OS/Plans/Alpha%20Release%20Gate/Fluent%20Suite%20Registrar%20Bundle%20Sprint%202026-05-13.md) (vault path). Sprint plan / dispatch briefs were authored with a "v2.0.0" working label; ratified release version is v1.4.0 per semver (additive feature wave, zero breaking changes).
>
> **Surface delta:** the 272 existing + 824 new = 1,096 figure is the *feature-wave maximum* and predates the cold-start removals. **43 abilities were subsequently removed** from the v1.4.0 surface (3 in P7-close [#93] + 40 in #101 P-REMOVAL [#104]; see *Breaking changes*), so the shipped new-ability surface is reduced by 43 from the feature-wave maximum. An exact post-removal registry total is intentionally not re-asserted here (the authoritative count is the registry/`#101` manifest, not a hand-recomputed figure). Original sprint plan projected ~728 new abilities; final feature-wave delivery exceeded projection because the per-plugin authoritative inventory sections were higher than several TL;DR estimates, reviewed/ratified during Phase B. Existing abilities for `support`, `smtp`, `auth`, `snippets`, `affiliate`, `cross-module` ship unchanged from v1.1.3.
>
> **Stable Ability Contracts:** v1.1.3 ability *input/output schemas + slugs + permission semantics* remain stable in v1.4.0. **Exception — abilities removed (J-authorized Principle-10 named removals, no working v1.1.3 contract to preserve):** 3 never-functional v2.0.0 abilities in P7-close [#93] plus a further 40 J-deferred cold-start abilities in #101 P-REMOVAL [#104] — 43 total; every removed registration block is archived verbatim in issue #101 for roadmap restore (see *Breaking changes*). The cold-start fix sprint (below) additionally corrected behavior in many abilities **without** changing their released input/output schema or slug (callback-body and factually-corrective schema fixes only). Several previously-preserved v1.1.3 defects are now **resolved** by the fix sprint (see *Preserved v1.1.3 defects*).

### Cold-start fix sprint (post-feature-wave, P1–P8) — 2026-05-15 → 2026-05-18

After the feature wave, a cold-start re-test surfaced behavior defects across the new surface; the v1.4.0 fix sprint resolved them in eight reviewed packages P1–P8 (merged to `integration/fluent-suite-registrar-v2` via parent PR [#97](https://github.com/Wicked-Evolutions/abilities-for-fluent-plugins/pull/97)), followed by the #100 verify-first triage and two post-#97 fix PRs ([#102](https://github.com/Wicked-Evolutions/abilities-for-fluent-plugins/pull/102), [#103](https://github.com/Wicked-Evolutions/abilities-for-fluent-plugins/pull/103)) and the #101 P-REMOVAL strip ([#104](https://github.com/Wicked-Evolutions/abilities-for-fluent-plugins/pull/104)); single-reviewer V1–V12 contract gate across all. Reconciled to integration HEAD `37a803b`. No released `input_schema`/`output_schema`/slug changed except factually-corrective schema fixes (P-B `anyOf`, P-D type drift, P-H output-shape) that bring the schema into agreement with what the vendor handler actually accepts/returns.

- **P1 [#82] — Safety (V7/V8):** `fluent-crm/update-contact-custom-fields` now requires explicit `confirm_full_replace` for the destructive empty-array full-replace (typed `WP_Error` otherwise); `fluent-crm/create-webhook` whitelists input to schema-declared keys before persistence (transport-envelope leak closed).
- **P2 [#83] — Crash blockers (V5/V10):** one shared Collection→array coercion helper fixes the 11-site FluentBoards `array_map` `TypeError` (P-A); 8 CRM/Community/Bookings PHP fatals (P-K, incl. `update-privacy-settings`, `get-available-slots`) converted to typed `WP_Error`.
- **P3 [#84/#85/#86/#88] — Write correctness (V2/V3/V9):** `create-course`/`update-course` + 8 cascade reads routed through the canonical `\FluentCommunity\Modules\Course\Model\Course` namespace; F-COM-03 Utility/NotificationPref namespace drift fixed; `send-message`/`add-booking-note` return the real persisted id (`insertGetId`); `add-booking-note` duplicate-write removed; `create-custom-order` totals, `list-attachable-users` filter, `update-order-address-id` typed precondition; `update-webhook` envelope whitelist (P3.5); `create-form` now persists `title`/`status` and returns a read-back, not an input echo (P3c); `update-customization-settings` made non-destructive (read-merge-write).
- **P4 [#89/#90] — Response boundary (V5):** ~17 FluentCRM Eloquent/model leaks projected to plain arrays + 5 paginator responses unwrapped via one shared helper (P-G/P-J); ~24 P-H output-schema corrections (empty-state normalize vs union, vendor-source-verified per slug); `get-template` returns a typed `not_found` instead of a silent empty placeholder.
- **P5 [#91] — Schema clarity (V4):** ~70 `input_schema` description / vendor-`Source:` citation corrections; FluentBoards `oneOf`→`anyOf` (P-B, installed-handler truth) and integer/string type-drift fixes (P-D); FluentPlayer P-C/P-D editorial.
- **P7.1 [#94] — Schema-output boundary (V5/V10):** a schema-construction defect that fataled WP-core `validate_output()` on *populated* responses across 19 FluentCRM list/collection read abilities (empty-site passed, populated-site fataled — the cold-start signature) fixed via an item-schema discriminator.
- **P8 [#95] — FluentPlayer behavior:** shared status-aware proxy (vendor `success:true` wrapping an inner failure → typed `WP_Error` from the framework HTTP status); V10 crash/precondition guards; P-L write-correctness; P-H/serialization output fixes. (FluentPlayer was scoped into v1.4.0 once its Phase-7 test set was ready; J-authorized.)

**#100 verify-first triage + post-#97 fix PRs (2026-05-18):** the rolling cold-start fixed-set issue ([#100](https://github.com/Wicked-Evolutions/abilities-for-fluent-plugins/issues/100)) findings were triaged **verify-first** against the integration build (build-identity pinned, live-reproduced before any fix). The **majority were pre-fix-build artifacts** already covered by the landed P1–P8 packages — re-confirmed on the current build and **bounced with evidence, not re-fixed** (e.g. the FluentBoards Collection `array_map` set, the CRM Eloquent-leak/output-shape/pagination sets, several FluentPlayer P-L/P-E findings). Two findings reproduced as genuine defects on the integration build and landed as focused fix PRs:

- **[#102] — FluentCart pricing root + get-order (V1/V3/V5):** vendor-source-grounded. `fluent-cart/create-product` now also creates the default vendor `ProductVariation` (matching `FluentCart\App\Http\Controllers\ProductController::create`'s documented contract) with `item_price` set, and sets `ProductDetail.default_variation_id`; `update-product-pricing` writes the authoritative `ProductVariation.item_price` (the vendor's real sellable-price column; `ProductDetail.min/max` is the derived aggregate); `get-product` maps the real vendor columns (`variation_title`/`item_price`) and `created_at` from `post_date`; `get-order` reads `total_amount` (the vendor `Order` model has no `total` column). One shared pricing root covering create-product + get-product + update-product-pricing; verified on the live FluentCart store (`community.wickedevolutions.com`), throwaway fixtures, deploy→verify→restore.
- **[#103] — CRM/Booking vendor-contract (V1/V3):** `fluent-crm/create-company-note` now wraps the documented vendor `CompanyController::addNote` `note` payload (a flat body made `$request->get('note')` null → `Validator::__construct(null)` TypeError); `fluent-booking/get-global-settings` routes the documented vendor `Helper::getGlobalSettings()` (the prior `__fluent_booking_global_settings` option was unused/empty — a data-missing root, not a serialization patch). V3 read-back proven on helenawillow.

Per-finding provenance: dispatch brief + ledger Addenda 1–35 (`[[DISPATCH BRIEF — v1.4.0 Fix Sprint 2026-05-15]]`, `[[SPRINT BRIEF CAPTURE — v1.4.0 Cold-Start Re-test]]`); #100 evidence comments; #101 removal manifest + verbatim archive.

**Post-#105 production-confirmed blocker — FluentBooking front-end 500 (V3, [#106] → [#107](https://github.com/Wicked-Evolutions/abilities-for-fluent-plugins/pull/107), 2026-05-18):** distinct from the #100 triage above; surfaced after the #105 reconciliation. `fluent-booking/update-event-location-config` (and five sibling `settings` writers) manually `maybe_serialize()`d a value before assigning it into a vendor Eloquent attribute, so the vendor mutator (`CalendarSlot::setLocationSettingsAttribute`/`setSettingsAttribute`, `Calendar::setSettingsAttribute`) serialized it again; the vendor accessor then `maybe_unserialize()`d once and returned a *string*, and vendor `count($this->location_settings)` (installed `CalendarSlot.php:263/272`) fataled under PHP 8.3 (`count(): Argument #1 must be Countable|array, string given`) → production-confirmed FluentBooking booking-form / front-end calendar **500**. Fixed by passing the plain array and letting the vendor mutator perform the single canonical serialization (V3 — route through the vendor model; smallest local change). The same pass fixed a `\FluentAffiliate\App\Models\Affiliate` `settings` sibling (`includes/affiliate/portal-abilities.php`) where the vendor `setSettingsAttribute` `is_array()` guard *discarded every submitted setting* when handed a pre-serialized string — a **silent `bank_details`/`disable_new_ref_email` data-loss**, same V3 vendor-mutator-bypass root. Live read-back proven on helenawillow (incidental test surface, throwaway fixture, build-identity pinned, 0 residue); the FluentAffiliate sibling classified on installed-vendor-source authority. The encoder-agnostic inverse sweep (non-`maybe_serialize` encoders and non-adjacent pre-encoded values — the residual sub-class) is recorded as post-v1.4.0 follow-up [#108](https://github.com/Wicked-Evolutions/abilities-for-fluent-plugins/issues/108) — **non-gating**.

### FluentCRM

**+225 abilities across 32 sub-clusters** ([#68](https://github.com/Wicked-Evolutions/abilities-for-fluent-plugins/pull/68)). Existing 81 v1.1.3 abilities preserved unchanged.

- Subscriber extensions + bulk operations (15)
- Campaign lifecycle / metrics / recipients / schedule / labels (28)
- Templates + smart-codes + email patterns + editor patterns (23)
- Funnel atomic operations + state + reports + templates (26)
- Reports surface (18)
- Settings sub-cluster (17 read/write, +2 abandoned-cart from §5.15)
- AI module / abandoned-cart / custom-fields / labels / webhooks / users / import / forms / docs (17)
- Companies Pro (17)
- Sequences / Recurring Campaigns / Dynamic Segments / Campaigns-Pro / Smart Links (38)
- Pro settings + commerce reports (9)
- Global search helpers (1)

`§5.32` drift-fix abilities deferred to a future v1.x hotfix lane per Principle 10 Stable Contracts (deferred drift-fixes tracked at [#62](https://github.com/Wicked-Evolutions/abilities-for-fluent-plugins/issues/62) and [#63](https://github.com/Wicked-Evolutions/abilities-for-fluent-plugins/issues/63); these are Phase-B follow-ups, not v1.1.3 KD-ledger entries — the canonical ledger defines only KD-1…KD-7).

### FluentCart

**+108 abilities across 19 sub-clusters** ([#53](https://github.com/Wicked-Evolutions/abilities-for-fluent-plugins/pull/53)). Existing 50 v1.1.3 abilities preserved unchanged.

- Products + customers + orders + transactions + shipping + tax rates (with `delete-tax-rate` added per Reviewer authorization in round-4-redux)
- Coupons + subscriptions + licenses + activities + reports + settings
- Cluster 4.11 (product upgrade paths, 4 abilities) deferred — research-cited `UpgradePath` model absent in deployed FluentCart Pro 1.3.26 ([#65](https://github.com/Wicked-Evolutions/abilities-for-fluent-plugins/issues/65) — vendor-version reconciliation follow-up).

### Fluent Forms

**+88 abilities across 23 sub-clusters** ([#58](https://github.com/Wicked-Evolutions/abilities-for-fluent-plugins/pull/58)). Existing 6 v1.1.3 abilities preserved unchanged.

- Form CRUD + submissions + fields + analytics + reports + import/export + integrations + payments + webhooks + duplications + permissions + entries + logs + assets + conversational + scheduling + workflows + notifications

### Fluent Bookings

**+78 abilities across 18 sub-clusters** ([#56](https://github.com/Wicked-Evolutions/abilities-for-fluent-plugins/pull/56)). Existing 37 v1.1.3 abilities preserved unchanged.

- Bookings + availability + booking-meta + calendar integrations + calendar-meta + coupons + event config + event location + global settings + import + license + multi-host + orders + permissions + reports + reschedule + slots + team + webhooks + Zoom/Twilio
- New `fluent_booking_admin` capability tier added for license / global settings / integration credentials (high-risk operations distinct from standard write tier).

### Fluent Boards

**+161 abilities across 22 sub-clusters** ([#60](https://github.com/Wicked-Evolutions/abilities-for-fluent-plugins/pull/60)). Existing 37 v1.1.3 abilities preserved unchanged.

- Board + stage + task + sub-task + relations + assignees + labels + watchers + custom-fields + comments + activities + reports + filters + automations + notifications + permissions + import/export
- `§4.19.1–.3` deferred (slug collision with v1.1.3 frozen `create-/update-/delete-stage`).
- KD-6 ([#50](https://github.com/Wicked-Evolutions/abilities-for-fluent-plugins/issues/50)) `move-subtask-to-board` carries `destructive: true` + explicit data-loss warning. KD-7 ([#51](https://github.com/Wicked-Evolutions/abilities-for-fluent-plugins/issues/51)) sales-pipeline board surfacing handled via `list-boards-by-type` enum.

### FluentCommunity (+ Messaging)

**+61 abilities across 15 sub-clusters** ([#55](https://github.com/Wicked-Evolutions/abilities-for-fluent-plugins/pull/55)). Existing 61 v1.1.3 abilities (56 community + 5 messaging) preserved unchanged.

- Community (53): spaces + members + courses + lessons + feeds + notifications + scheduled posts + media + reactions + moderation
- Messaging (8): threads + participants + messages + thread-read state
- KD-3 ([#47](https://github.com/Wicked-Evolutions/abilities-for-fluent-plugins/issues/47)) preserved per Stable Contracts; all new course/lesson code uses canonical `\FluentCommunity\Modules\Course\Model\{Course,CourseLesson}`.
- Messaging delete operations use `level => 'write'` with inner-callback author/self-removal enforcement (no `fluent_messaging_delete` cap added — intentional Messaging-specific auth model ratified in Reviewer round-8).

### FluentPlayer (+ FluentPlayer Pro) — new module

**+103 abilities across 17 sub-clusters** ([#57](https://github.com/Wicked-Evolutions/abilities-for-fluent-plugins/pull/57)). **Greenfield module — no v1.1.3 baseline.** 22 free + 81 Pro split. New `fluent-player` ability category registered.

- Media + playlists + presets + email collections + analytics + Bunny CDN stream + Bunny storage + Mux + license
- License cluster gated via `manage_options` capability override per research §5.17 (no separate `fluent_player_admin` cap added — simpler operator model per research recommendation).
- Pro-tier abilities (81 surfaces) gated by `defined('FLUENT_PLAYER_PRO_VERSION')`; Bunny Storage cluster guarded by `BunnyCDNStorageService::getSettings()` pre-check returning typed `integration_not_configured` errors when credentials absent.
- Runtime PII / secret redaction applied at callback layer via `fluent_abilities_player_redact()` for License / Email Collections / Analytics / Mux / Bunny credential fields. Operationally-required fields (user_id, list_id, form_id, status enums, counts) preserved.

### Scaffold / shared infrastructure

- New ability category: `fluent-player` ([#67](https://github.com/Wicked-Evolutions/abilities-for-fluent-plugins/pull/67))
- New capability: `fluent_booking_admin` ([#66](https://github.com/Wicked-Evolutions/abilities-for-fluent-plugins/pull/66)) for FluentBooking license / global settings / integration credentials tier
- New capability: `fluent_forms_delete` ([#54](https://github.com/Wicked-Evolutions/abilities-for-fluent-plugins/pull/54)) so Fluent Forms delete abilities ship with proper Principle 5 layered permissions
- Loader wiring for the seven plugin sub-file sets in `abilities-for-fluent-plugins.php` ([#59](https://github.com/Wicked-Evolutions/abilities-for-fluent-plugins/pull/59), [#61](https://github.com/Wicked-Evolutions/abilities-for-fluent-plugins/pull/61), [#66](https://github.com/Wicked-Evolutions/abilities-for-fluent-plugins/pull/66), [#67](https://github.com/Wicked-Evolutions/abilities-for-fluent-plugins/pull/67) + per-plugin top-of-file requires in Boards / Forms feature PRs)
- Test-infra hygiene: Booking `permission_callback` assertion updated to tolerate `bool|WP_Error` denial return shapes per WordPress Abilities API spec ([#70](https://github.com/Wicked-Evolutions/abilities-for-fluent-plugins/pull/70))

### Compatibility / documentation notes

- **`permission_callback` return shape now matches WordPress Abilities API spec.** Anonymous-CLI denials return `WP_Error('fluent_abilities_no_cli_user_context', ...)` instead of bare `bool false`. This aligns with the upstream `check_permissions()` documented `bool|WP_Error` return shape. Existing operator integrations that introspected `permission_callback` return values for the strict `bool` type should accept the typed WP_Error as a valid denial signal. Not a breaking change for standard ability execution paths; this only affects code that directly introspects `permission_callback` return types.
- **Abilities for AI permission gating:** new delete-tier abilities across Fluent modules may require updates to the `wp_abilities_suite_permissions` site option before execution is allowed. Operators upgrading from v1.1.3 may need to verify the option includes delete-tier for Fluent modules they intend to use. See [#72](https://github.com/Wicked-Evolutions/abilities-for-fluent-plugins/issues/72).

### Preserved v1.1.3 defects (queued for a future v1.x hotfix lane)

Per Principle 10 Stable Contracts, the canonical v1.1.3 known-defects ledger ([`docs/V1.1.3-KNOWN-DEFECTS.md`](docs/V1.1.3-KNOWN-DEFECTS.md)) defines **KD-1 … KD-7**, disposition **PRESERVE** for v1.4.0 except as reconciled below — they otherwise ship unchanged and are tracked for a separate v1.x hotfix lane (issues [#45](https://github.com/Wicked-Evolutions/abilities-for-fluent-plugins/issues/45)–[#51](https://github.com/Wicked-Evolutions/abilities-for-fluent-plugins/issues/51)). **KD-1 reconciliation (partial supersede — not closed):** the prior "F-CART-01 J-approved defer to #45" disposition is now accurate only for the *wrong-CPT literal*. Per the recorded **2026-05-18 J authorization (superseding Addendum-5 / KD-1 / #45)**, PR [#102] fixed the **`create-product` pricing root** (+ `update-product-pricing`) in v1.4.0 — these now create and persist the vendor pricing `ProductVariation` correctly. **The KD-1 wrong-CPT literal itself (`fct_product` vs canonical `fluent-products`) remains PRESERVE / separately tracked at [#45], untouched** — reviewer-confirmed *cleanly separable* from the pricing fix and runtime-inert (the vendor `Product` model's `creating` hook forces the canonical CPT regardless of the literal). KD-1 is therefore **not closed**; only its pricing-root aspect was addressed by #102. KD-2 (8 FluentCart schema/CPT drifts, #46) remains preserved. The cold-start fix sprint (above) resolved a number of **distinct cold-start re-test findings that are not v1.1.3 known defects** — e.g. `fluent-cart/get-customer` `user_id` union (P4/P7.1), `fluent-booking/create-event` fatal→typed `WP_Error` (P2), `fluent-messaging/send-message` real persisted id (P3); those are described by package in the fix-sprint section above, **not** as KD-ledger entries. KD-3 (always-false `CourseLesson` dead branches, #47) remains PRESERVE — distinct from the P3a `create-course` *write-namespace* fix (F-COM-01); P3 did not remove the KD-3 dead branches. *(Prior revisions of this changelog carried non-canonical "KD-8…KD-12" rows absent from the canonical ledger on this branch — removed here to reconcile.)* None block v1.4.0 release.

| KD | Plugin | Symptom | Issue |
|---|---|---|---|
| KD-1 | FluentCart | `fluent-cart/create-product` writes wrong CPT literal (`fct_product` vs canonical `fluent-products`) — **PRESERVE/#45, runtime-inert** (vendor model forces canonical CPT). *Separately, the create-product **pricing root** was fixed in v1.4.0 via #102 under the 2026-05-18 J authorization superseding Addendum-5/KD-1/#45 — KD-1 not closed; only the wrong-CPT literal remains.* | [#45](https://github.com/Wicked-Evolutions/abilities-for-fluent-plugins/issues/45) |
| KD-2 | FluentCart | 8 schema/CPT drifts across existing 50 FluentCart abilities | [#46](https://github.com/Wicked-Evolutions/abilities-for-fluent-plugins/issues/46) |
| KD-3 | FluentCommunity | Always-false `CourseLesson` namespace check (dead branches) | [#47](https://github.com/Wicked-Evolutions/abilities-for-fluent-plugins/issues/47) |
| KD-4 | Fluent Bookings | Stale `$count = 22` in abilities.php error_log | [#48](https://github.com/Wicked-Evolutions/abilities-for-fluent-plugins/issues/48) |
| KD-5 | Fluent Bookings | Status enum drift (`no-show` read vs `no_show` write) | [#49](https://github.com/Wicked-Evolutions/abilities-for-fluent-plugins/issues/49) |
| KD-6 | Fluent Boards | Cross-board `move-task` is destructive (documentation gap) | [#50](https://github.com/Wicked-Evolutions/abilities-for-fluent-plugins/issues/50) |
| KD-7 | Fluent Boards | `Board::boot` global scope excludes sales-pipeline (vendor design quirk) | [#51](https://github.com/Wicked-Evolutions/abilities-for-fluent-plugins/issues/51) |

### Known follow-up work (not release blockers)

Plugin-side hygiene tracked separately for follow-up sprint:

- [#65](https://github.com/Wicked-Evolutions/abilities-for-fluent-plugins/issues/65) — FluentCart cluster 4.11 deferred pending vendor-version/source reconciliation with FluentCart Pro `UpgradePath` model
- [#72](https://github.com/Wicked-Evolutions/abilities-for-fluent-plugins/issues/72) — `wp_abilities_suite_permissions` delete-tier may need operator-side updates (see Compatibility note above)
- [#73](https://github.com/Wicked-Evolutions/abilities-for-fluent-plugins/issues/73) — `Fluent_Abilities_Plugin_Updater` dev-mode bypass for cleaner Phase B / live verification cycles

Adapter-side (different repo, separate hygiene):

- [`abilities-mcp-adapter` #116](https://github.com/Wicked-Evolutions/abilities-mcp-adapter/issues/116) — ScopeRegistry coverage gap for new Phase B ability categories. Operators using the bundled MCP adapter for ability execution will need adapter-side scope grants updated to access the new `fluent-player` category and the expanded surface in existing Fluent categories. Tracked in the adapter repo per Principle 9.

### Breaking changes

**Three abilities removed (J-authorized, Principle-10 named removal — v1.4.0 P7-close [#93]):** `fluent-crm/get-report-top-campaigns`, `fluent-crm/set-global-email-style`, `fluent-crm/list-subscribers-prev-next-ids`. All three were **never-functional since their v2.0.0 introduction** (a non-existent vendor route → 404; input forwarded to the wrong vendor key → silently discarded; a required field the handler never reads → 100% rejection). They are **not in v1.1.3**, so no working released contract existed to preserve; removal is the correct disposition (shipping callable-but-dead abilities is the defect). Verified absent at tag `v1.1.3`; named removal version recorded; no aliasing (no working contract to alias).

**Forty (40) further abilities removed (J disposition-semantics directive — #101 P-REMOVAL [#104]):** J-deferred cold-start findings (per-#101: bridge-connection-closers across modules, FluentCommunity Phase-4 + FluentCRM Phase-2 deferrals, Fluent Forms Phase-5 helpers, `fluent-boards/list-top-tasks-for-boards`, Bookings Phase-6 coupons/webhooks/onboarding, etc.) removed from the v1.4.0 surface per J's "removed from this release = removed from the plugin" directive. None are v1.1.3 abilities. The reconciled Stage-1 manifest honored every #101 correction/withdrawal — the FluentBoards Pattern-A ×11 set stayed (fixed via #100/P2, not removed); 14 #101-listed-but-in-sprint-fixed abilities were retained. **The strip was 40, not 41:** an initial list erroneously included `fluent-crm/get-system-logs`, which is a Package-4b ([#90]) ensured-fixed *retained* ability with its own vendor-map row — caught at the pre-merge gate and **restored** (registration + vendor-map row + test coverage), final removed count **40**. Every removed registration block is archived **verbatim** in issue [#101](https://github.com/Wicked-Evolutions/abilities-for-fluent-plugins/issues/101) ("v1.4.0 removed-ability code archive", grouped by module, file:line + integration-HEAD md5) for roadmap restore; the archive predates the strip commit. **Combined v1.4.0 removed surface: 3 (P7-close [#93]) + 40 (#101 [#104]) = 43 abilities.**

All **v1.1.3** abilities remain contract-identical (slug + input_schema + output_schema + permission semantics). Operator integrations that rely on `permission_callback` strict bool return-type checks should accept `WP_Error` as a valid denial (compatibility note above) — semantically the same denial signal.

## [1.1.3] - 2026-05-10

Bug fix — Registrar `input_schema` default restored to JSON Schema draft 2020-12 conformance.

### Fixed

- `src/Core/Registrar.php:137` — `input_schema` default value changed from PHP `array()` (which JSON-encodes as `[]`) to `array( 'type' => 'object' )` (JSON `{"type":"object"}`). The previous default caused MCP clients to hit `400 tools.N.custom.input_schema: JSON schema is invalid. It must match JSON Schema draft 2020-12` on `tools/list` for any zero-arg ability registered without an explicit `input_schema` — affecting 8 zero-arg abilities surfaced when Fluent OAuth scopes were granted (rising to 17 with Fluent Affiliate active). Mirrors the equivalent fix that shipped in `abilities-for-ai` v1.9.1.
- Unit test added at `tests/Unit/RegistrarTest.php` asserting `json_encode($args['input_schema'])` returns `'{"type":"object"}'` (not `'[]'`) when no `input_schema` is provided in `$config`.

Closes [#41](https://github.com/Wicked-Evolutions/abilities-for-fluent-plugins/issues/41).

## [1.1.2] - 2026-05-08

Documentation update — README rewrite for first-party positioning + Wordpressnaut welcome + four-layer permissions framing + version-history catch-up. Code unchanged from v1.1.1.

### Documentation

- README rewritten end-to-end:
  - **Removed leading ability-count framing** (no 450-ability table at the top, no `(N abilities)` in module section headings). The product is the open registry, not the count — operators get live counts via `suite/get-status` for their specific install.
  - **First-party deliberate-investment positioning** surfaced in the opening paragraph — *"Wicked Evolutions' first-party translator for the Fluent suite. We build and maintain this because we use Fluent's plugins ourselves and wanted them AI-native — a deliberate ongoing investment, not a one-off example."*
  - **Supported Fluent Products table** now describes what each module enables operators to do (e.g., for CRM: *"Contacts CRUD bulk tags lists companies, email campaigns, sequences, ..."*) — no count framing
  - **Bridge / adapter setup orientation** added — operators get pointed at the recommended bridge install paths (`.mcpb` for Claude Desktop / `npm install -g @wickedevolutions/abilities-mcp` for terminal MCP clients), with a link to the bridge README for full setup
  - **Four-layer permissions model section** added — names the four layers (Abilities for AI module · WordPress capability · OAuth scope · unclear), points operators at the runtime `[ability_disabled]` error as the layer indicator, and shows operators how to expand Fluent-specific OAuth scopes (`abilities-mcp reauth <site> --add-scope="abilities:crm:write"`)
  - **Version History catch-up** — README now reflects 1.0.0 → 1.0.1 → 1.1.0 → 1.1.1 (was stuck on 1.0.0 only)
  - Welcome block at top with verbatim *"Welcome, Wordpressnaut"* spaceship paragraph + 3 URL pointers
  - Disclaimer block from J at the very top
  - Pointer to [PRINCIPLES.md](PRINCIPLES.md) as the *Official WordPress Compatibility Contract* binding all four suite repos

Closes [#38](https://github.com/Wicked-Evolutions/abilities-for-fluent-plugins/issues/38).

## [1.1.1] - 2026-05-05

### Fixed
- **#22:** Fresh activations with FluentAffiliate active now auto-enable the `affiliate` module. The activation hook's auto-detect map at `abilities-for-fluent-plugins.php:231-243` listed 11 modules through `cart` and omitted `FLUENT_AFFILIATE_VERSION`, while the runtime loader at lines 109-121 already included it. CLI-first installs that activated FluentAffiliate alongside this plugin saw zero affiliate abilities register on first activation despite the plugin advertising Affiliate support.
- **#21:** Affiliate abilities now appear in the dashboard explorer when the FluentAffiliate module is enabled. The dashboard's `$module_to_category` map omitted `affiliate => fluent-affiliate`, so `get_fluent_abilities()` filtered out the 21 affiliate-categorized abilities (across `abilities.php`, `payout-abilities.php`, `portal-abilities.php`, `report-abilities.php`, `settings-abilities.php`) before they reached the explorer. Adding the map entry restores them and lets the explorer's category-filter dropdown narrow to `affiliate`.
- **#20:** Disabled installed modules no longer vanish from the settings UI. Module-toggle rows for installed-but-disabled modules now render in the Abilities explorer (with an enabled checkbox so the operator can re-enable from the UI), independently of whether the module currently registers any abilities. Previously, disabling a module via the toggle would remove it from the explorer entirely on next page load — re-enabling required hand-editing the `fluent_abilities_enabled_modules` option. Underlying bug had two layers: the explorer iterated registered abilities to emit module headers (so disabled modules with zero abilities had no header emitted), and the existing fallback loop referenced an undefined `$all_seen_modules` variable + only matched `! detected` (skipping installed-disabled).
- **#19 (P1, alpha blocker):** WP-CLI / stdio bridge invocations without a resolved WordPress user no longer authorize destructive abilities by module-toggle alone. Anonymous CLI requests now deny every level by default and return a typed `WP_Error( 'fluent_abilities_no_cli_user_context', ..., array( 'status' => 401 ) )` at the ability boundary. A one-release backwards-compatibility shim, `FLUENT_ABILITIES_CLI_ALLOW_ANONYMOUS_READ=1`, allows anonymous read-level access for enabled modules during operator migration. Removal target: v1.2.0. Operators relying on anonymous write/delete/admin must wire OAuth user resolution (or set the env-var explicitly per invocation) — read-only.
- **#23:** PHPUnit unit-test suite no longer fatals on `Call to undefined function fluent_abilities_pro_gate()`. Bootstrap loads a passthrough stub in unit mode (decoupled from the real `tier-gate.php` / license-manager). Previously-fataling Registrar tests now run and pass.

### Changed
- **CI hygiene:** unit-tests matrix now runs PHP 8.1, 8.2, 8.3, 8.4, 8.5 (composer.lock requires PHP 8.1+ — production minimum unchanged at PHP 7.4 per `composer.json`). Vestigial integration-tests matrix removed: it ran against `tests/Integration/` which has never existed in the repo, so the matrix was testing test-suite setup, not any production code. `fail-fast: false` on unit-tests so a single matrix entry's failure no longer cancels its siblings.

## [1.1.0] - 2026-03-20

### Fixed
- **#1 (P0):** Cart table prefix `fc_` corrected to `fct_` — `list-abandoned-carts`, `list-tax-rates`, `list-shipping-methods` now return actual data
- **#4 (P0):** `list-product-downloads` `product_variation_id` no longer returns "Array" — handles array-to-string coercion
- **#9:** `delete-tag` now returns success instead of false "Tag not found" — detaches pivot entries and fires FluentCRM cleanup hooks
- **#6:** `delete-tag` no longer creates ghost duplicates — proper cleanup prevents re-creation via lifecycle hooks
- **#7:** `delete-label` param renamed `label_id` to `id` for consistency with `create-label` (also fixed `update-label`)

### Added
- **#5:** `fluent-cart/delete-product-variation` ability
- **#8:** `fluent-boards/delete-stage` ability with task migration to fallback stage
- **#10:** `fluent-cart/delete-product-download` ability

## [1.0.1] - 2026-03-17

### Fixed
- Network license inherits to all subsites
- Standalone dashboard menu when Abilities for AI is not installed

## [1.0.0] - 2026-03-17

### Added
- Initial release — 450 abilities across 13 modules
- Migrated from `Influencentricity/abilities-suite-for-fluent-plugins` with slug rename, branding update, option key migration, and version reset
- Modules: CRM (94), Community (66), Boards (62), Affiliate (55), Cart (50), Support (46), Booking (42), Snippets (7), Forms (6), Cross-module (5), Messaging (5), SMTP (5), Auth (4)

---

## License

GPL-2.0-or-later
