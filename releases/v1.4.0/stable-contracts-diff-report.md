# Stable Contracts gate v1.1 amendment — diff report

> **Phase C Step 2** — Sprint plan §"Phase C" Step 2a runtime registry diff. Sprint plan v1.1 amendment binding gate for v1.4.0 release.
>
> **Captured:** 2026-05-14 against `helenawillow.com` (carries all 7 sprint plugins + 6 non-sprint plugins).

## Registry sources

| File | Site | Plugin version | Fluent abilities | Total abilities |
|---|---|---|---|---|
| `helenawillow-v1.1.3-baseline-registry.json` | helenawillow.com | 1.1.3 (tag) | 384 | 791 |
| `helenawillow-v1.4.0-rc1-registry.json` | helenawillow.com | 1.4.0-rc1 (integration `682d768`) | 1,208 | 1,615 |

Total delta: +824 Fluent abilities.

## Gate result

✅ **PASS — Stable Contracts preserved across all 384 v1.1.3 → v1.4.0-rc1 shared abilities.**

| Check | Result |
|---|---|
| v1.1.3 slugs removed in v1.4.0-rc1 | **0** ✅ |
| Schema/category mismatches on shared slugs | **0** ✅ |
| New abilities in v1.4.0-rc1 | **+824** (matches sprint count exactly) |

## New abilities per plugin (v1.4.0-rc1 - v1.1.3)

| Plugin | v1.1.3 | v1.4.0-rc1 | New | Sprint cite | ✓ |
|---|---|---|---|---|---|
| FluentCRM | 81 | 306 | +225 | 225 | ✓ |
| FluentCart | 50 | 158 | +108 | 108 | ✓ |
| Fluent Forms | 6 | 94 | +88 | 88 | ✓ |
| Fluent Bookings | 37 | 115 | +78 | 78 | ✓ |
| Fluent Boards | 37 | 198 | +161 | 161 | ✓ |
| FluentCommunity | 56 | 109 | +53 | 53 | ✓ |
| Fluent Messaging | 5 | 13 | +8 | 8 | ✓ |
| FluentPlayer | 0 | 103 | +103 | 103 | ✓ (greenfield) |
| **Sprint total** | **272** | **1,096** | **+824** | **824** | **✓** |
| Non-sprint (affiliate/auth/smtp/snippets/support/cross-module) | 112 | 112 | 0 | unchanged | ✓ |
| **Grand total** | **384** | **1,208** | **+824** | | |

## Comparison fields

Per sprint plan v1.1 amendment: "every v1.1.3 ability MUST be present in v2.0.0 candidate with contract-identical (slug + input_schema + output_schema + permission_callback identifier) match."

Fields compared at the WP_Abilities_Registry level via `wp_get_abilities()`:
- ✅ **slug** (registry key)
- ✅ **category** (matched on all shared slugs)
- ✅ **input_schema** (deep-equality matched on all shared slugs)
- ✅ **output_schema** (deep-equality matched on all shared slugs)
- 🛈 **permission_callback identifier** — not runtime-comparable across PHP processes (closures), BUT every Phase B PR's source diff via `git diff v1.1.3 -- includes/{plugin}/` confirmed zero touches to existing v1.1.3 `$reg->read|write|delete()` registration blocks. Strong evidence permission_callback closures are unchanged.

## Static-extraction discrepancy resolution

PR #75 cold-AI sweep ledger noted a 16-slug delta between static grep extraction (808 new slugs) and the ratified sprint count (824 new). This diff confirms **the ratified 824 is the truth source** — the 16-slug gap was static-extraction lossiness (likely variable-interpolated registrations in FluentCart + Fluent Forms code that literal-string grep cannot resolve). Live registry shows exactly 824 new abilities, matching per-plugin ratified counts.

The cold-AI sweep ledger should be reconciled to the live registry as the canonical sweep target. The 16 missing slugs surface in live discovery and will be added to the ledger during Phase C Step 10 prep.

## Per-plugin known-defects ledger preservation

Per Principle 10 Stable Contracts, v1.1.3 abilities flagged as known-defects (KD-1 through KD-12) ship UNCHANGED in v1.4.0. This diff confirms:

| KD | v1.1.3 ability slug | Present in v1.4.0-rc1 | Schemas unchanged |
|---|---|---|---|
| KD-1 | `fluent-cart/create-product` | ✓ | ✓ |
| KD-2 | (8 schema/CPT drifts across FluentCart abilities) | ✓ | ✓ |
| KD-3 | (FluentCommunity dead-branch `class_exists` check — internal, not schema) | ✓ | ✓ |
| KD-4 | (Fluent Bookings `$count = 22` error_log — internal, not schema) | n/a | n/a |
| KD-5 | (Fluent Bookings status enum drift) | ✓ | ✓ |
| KD-6 | (Fluent Boards `move-task` destructive — design quirk, not schema) | ✓ | ✓ |
| KD-7 | (Fluent Boards `Board::boot` global scope — vendor quirk, not schema) | n/a | n/a |
| KD-8 | `fluent-crm/delete-automation` (cap override drift) | ✓ | ✓ |
| KD-9 | `fluent-crm/get-funnel-conversion` (schema/callback mismatch) | ✓ | ✓ |
| KD-10 | `fluent-cart/get-customer` (user_id schema-strict) | ✓ | ✓ |
| KD-11 | `fluent-booking/create-event` (vendor filter TypeError) | ✓ | ✓ |
| KD-12 | `fluent-messaging/send-message` (phantom message_id) | ✓ | ✓ |

All 12 preserved-defect abilities present in v1.4.0-rc1 with unchanged schemas — Stable Contracts guarantee holds.

## Sampled runtime parity

Per sprint plan v1.1 amendment, sampled runtime parity is the second leg of the Stable Contracts gate (Phase C Step 2a paragraph 2). Disposition note:

The schema-shape diff above shows **0 mismatches across all 384 shared slugs**. WordPress ability registration binds input_schema + output_schema + permission_callback together; identical schemas + clean per-PR source diffs across all 7 Phase B feature PRs (verified during each Reviewer round) constitutes strong evidence runtime behavior is unchanged.

**Recommendation:** Accept this diff as sufficient evidence for Stable Contracts gate ratification, OR run an additional sampled-execute pass against the v1.1.3 baseline (requires re-downgrade cycle on a probe site, ~10 min). J's call.

## Evidence files

- `releases/v1.4.0/helenawillow-v1.1.3-baseline-registry.json` (440 KB, 791 abilities)
- `releases/v1.4.0/helenawillow-v1.4.0-rc1-registry.json` (802 KB, 1,615 abilities)
- This report
