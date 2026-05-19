# P-TEST-L2 — GATE-0 Report (Layer 2 staging fixture strategy)

> **Status: CARVE-OUT REVIEWER-RATIFIED on 7 binding conditions**
> (recorded on issue [#110](https://github.com/Wicked-Evolutions/abilities-for-fluent-plugins/issues/110), reviewer comment
> "Canonical-user carve-out — REVIEWER RATIFIED on 7 binding
> conditions"). This document records the ratified strategy and the 7
> conditions verbatim-faithful.
>
> **Still docs-only. The canonical user has NOT been created. No
> round-trip test has been authored or run.** Round-trip authoring
> proceeds (per the reviewer) only once this GATE-0 + the 7 conditions
> are in place AND the GATE-0 parity/license/bridge precondition (§4)
> is verified — neither of which is performed in this docs change.
>
> Layer 2 is staging behavioural corroboration and has **zero bearing
> on the v1.4.0 release** (Layer 1 `tests/Contract/`, merged #111, is
> the deterministic gate; condition 7).

---

## 1. The J directive (fixture-strategy amendment) — recorded verbatim-faithful

1. **One persistent canonical test user**, marker `[SPRINT-V2-TEST] canonical`, on dev2. **NEVER deleted.** The stable cross-plugin subject for all email / messaging / automation / email-sequence / course / space / form association testing.
2. **Full user-CRUD (create → update → delete) uses a SEPARATE throwaway user** — strict teardown + post-teardown read-back-absent + 0-residue. **Never the canonical user.**
3. The canonical user is a **DECLARED standing fixture**: recorded here (id + marker + provenance), exempt **from deletion / 0-residue only**. Every other fixture remains strictly bound.

A recorded J directive supersedes the standing reviewer 0-residue rule
(consistent with Addendum 26 / the 2026-05-18 #102 authorization / #69
ship-evidence lineage). Accepted because it is **narrow + recorded**
and — per conditions 2 & 3 — the exemption is from **deletion only**
while the canonical subject becomes a **verified invariant**:
F-CRM-01 protection is preserved for everything else and *strengthened*
for the one exception.

## 2. The ratified carve-out and its 7 binding conditions (verbatim-faithful)

> **CARVE-OUT (1 of 1, RATIFIED):** the single canonical test user is
> a permanent declared standing fixture, exempt from the binding
> "post-teardown read-back-absent / 0-residue" **deletion**
> postcondition — and *only* that. It remains fully under
> verification (condition 2/3).

Binding conditions, each of which the Layer-2 suite MUST encode:

1. **Exactly ONE exempt fixture — no generalization.** The exemption
   covers a single declared canonical user and nothing else. Any
   scope creep (a second exempt fixture, a class of exempt fixtures)
   **voids the carve-out**.
2. **Exempt from deletion / 0-residue ONLY, NOT from verification.**
   The residue-sweep postcondition is **rewritten as a
   whitelist-of-one**: at end of run assert (a) the canonical user is
   **present**, (b) its **baseline is unchanged**, and (c) **nothing
   else persists** (every non-canonical fixture still swept to 0
   residue). The sweep is not waived — it is re-expressed with exactly
   one whitelisted identity.
3. **Positive integrity assertion every run.** Each run asserts the
   canonical baseline (id / email / marker / key fields) is
   **unmodified at end of run**; any mutation = **run failure**. This
   makes the F-CRM-01 incident class (an ability silently mutating a
   shared subject) a **deterministic** catch rather than latent
   residue.
4. **NEVER a destructive-path subject.** The canonical user is never
   the subject of `delete-*` / full-replace / empty-array-wipe / any
   V8 destructive path — doing so is a **gate failure**. Destructive
   tests use a separate throwaway user (directive §1.2). (V8 wipe is
   never run on shared staging at all — Layer-1 guard-presence only,
   #110.)
5. **Associations on the canonical user are NOT exempt.** Any
   association created against the canonical user during a round-trip
   (CRM tag/list/automation/sequence, messaging thread, community
   course/space membership, form entry, …) is marker-scoped,
   **detached in-test**, and **residue-swept** like every other
   fixture. The carve-out covers the user identity only — never its
   associations.
6. **Provenance + fail-fast, NO auto-recreate.** The canonical user is
   recorded with provenance in §3. If it is **missing or drifted at
   run start**, that is a **setup failure** (fail-fast) — the suite
   does **not** silently recreate it. Recreation is a **deliberate
   GATE-0 update only** (a documented, reviewed change to this file),
   never an automatic test side effect.
7. **Carve-out scope only; Layer 2 non-gating.** This carve-out
   pertains solely to the Layer-2 staging fixture strategy. Layer 2 is
   not release-blocking and has **zero bearing on the Layer-1
   deterministic release gate (#111)**.

**Nothing else is exempt.** Throwaway CRUD users and every association
record remain under strict marker-scoped teardown + post-teardown
read-back-absent + 0-residue as an explicit test postcondition.

## 3. Declared standing fixture (provenance) — VALUES PENDING CREATION

The canonical user is **not yet created** in this docs change. On
creation (via an MCP ability — abilities-first, no SSH user create) it
is recorded here in the same commit as the first round-trip test, and
this table becomes the run-start invariant for conditions 3 & 6:

| Field | Value |
|---|---|
| Role | Canonical cross-plugin association subject (Layer 2) |
| Marker | `[SPRINT-V2-TEST] canonical` |
| User ID | _TBD — recorded at creation; the condition-6 fail-fast key_ |
| Login / email | _TBD — marker-scoped, non-deliverable domain_ |
| Key baseline fields | _TBD — id / email / marker / display name: the condition-3 invariant asserted unchanged every run_ |
| Host | `dev2.helenawillow.com` (see §4) |
| Created via (ability) | _TBD — the MCP create-user / CRM-contact ability used_ |
| Created at | _TBD_ |
| Lifecycle | **PERSISTENT — NEVER DELETED.** Exempt from deletion/0-residue ONLY (the §2 carve-out, condition 2). |
| Verification | **Always verified** — present + baseline-unchanged + nothing-else-persists every run (conditions 2 & 3). |
| Residue rule | Identity: whitelisted-of-one. Its associations: **strict** (condition 5). All other fixtures: **strict**. |
| Recreate policy | **NO auto-recreate.** Missing/drifted at run start = setup failure; recreation = deliberate GATE-0 update only (condition 6). |

## 4. dev2 resolution + GATE-0 parity/license/bridge precondition

dev2 is resolved: **`dev2.helenawillow.com`**, staging stood up and
**added to the MCP bridge per J** (reviewer comment "Layer 2 UNBLOCKED
— staging live"). It is a new bridged site with full ability scope
across both ability plugins (WP Abilities API + `abilities-for-fluent-
plugins`).

**GATE-0 precondition (verify, no assumption — performed at Layer-2
execution, NOT in this docs change):**

- dev2 WP/PHP parity **==** live;
- Fluent Pro suite license **ACTIVATED** on dev2;
- `wp_bridge_health` **resolves** dev2.

STOP + report if any fails (Layer 2 is invalid on a non-parity /
unreachable staging). Layer 2 remains non-gating; v1.4.0
proceeds independently on Layer 1 (#111 merged).

### 4.1 §4 license precondition — REVIEWER RATIFIED + AMENDED (issue #110, effective 2026-05-19)

The literal "Fluent Pro suite license **ACTIVATED**" precondition is
**superseded** by the following reviewer ruling (recorded J directive
→ reviewer-ratified; Addendum 26 / 2026-05-18 #102 auth / #69
ship-evidence lineage). This section is verbatim-faithful to the #110
reviewer ruling.

**(a) "Functional Pro = satisfied" — RATIFIED (ability/data surface only).**
GATE-0 §4 "Fluent Pro suite ACTIVATED on dev2" is satisfied by (i)
Pro add-on plugins active across the suite, AND (ii) functional proof
— a Pro-surface read-back **through the vendor model** returns the
persisted entity per product. This is RATIFIED **only because
verification reads back THROUGH THE VENDOR MODEL**: that read-back IS
the stub-vs-real discriminator. A license-stub / early-return persists
nothing, so the vendor-model round-trip assertion fails honestly
rather than false-greening. **Binding consequence:** any "ability
returned `success` but the vendor-model read-back shows nothing
persisted" is a **FAIL** (license-stub masking), never a pass.
License-**key** registration (Fluent's updates/support gate, not
feature execution) is NOT required for Layer-2 staging corroboration.
The §4 STOP no longer includes an "unlicensed" condition; it remains
STOP on non-parity / unreachable staging.

**(b) External-action abilities — EXCLUDED from Layer 2 entirely (NOT
license-gated).** Any ability whose callback reaches an **outbound
third-party transport** is excluded from the staging round-trip:

- SMS / voice gateway send;
- payment processor charge / capture / refund;
- live external calendar / CRM write-sync;
- real email to a non-controlled address;
- webhook delivery to a non-test endpoint.

**Fail-safe default:** uncertain classification → **EXCLUDED** (do not
fire a maybe-Stripe-charge to find out). Each excluded ability is
recorded in the run report as **skipped-with-reason** — never silently
omitted. Their registration / schema / crash-class is already covered
by Layer 1 (#111); that suffices for release. Any future behavioral
coverage of external-action abilities is a **separate, J-authorized
sandboxed / provider-test-mode mechanism** — never the staging
round-trip.

This amendment pertains solely to the Layer-2 staging precondition.
Layer 2 remains **non-gating** with **zero bearing on the Layer-1
deterministic release gate (#111)**.

## 5. Explicit STOP (state of this docs change)

- ✅ Carve-out **REVIEWER-RATIFIED** (issue #110); recorded here with
  all 7 binding conditions verbatim-faithful.
- ✅ Residue-sweep re-expressed as the **whitelist-of-one** (condition
  2) and the **positive integrity assertion** (condition 3) recorded
  as suite obligations.
- ⛔ Canonical user **NOT created** (docs-only this change).
- ⛔ Round-trip tests **NOT authored / NOT run**.
- ⛔ GATE-0 parity/license/bridge precondition (§4) **NOT executed**
  here (it is a Layer-2-execution step).
- ⛔ No outward / irreversible action on any staging site.

Round-trip authoring proceeds (per the reviewer "Dev unblocked:
round-trip tests proceed once GATE-0 + these 7 in place") only after:
GATE-0 §4 verified, the canonical user created via an MCP ability and
§3 filled, then Layer-2 round-trips authored under the strict rule
with this one carve-out applied (conditions 1–7 enforced). None of
that is done in this docs change.
