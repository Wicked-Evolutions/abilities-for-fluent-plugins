# P-TEST-L2 — GATE-0 Report (Layer 2 staging fixture strategy)

> **Status: AWAITING REVIEWER RATIFICATION.** This report DECLARES the
> Layer-2 fixture strategy and the one requested carve-out. **No
> canonical user has been created. No round-trip test has been
> written or run.** Per the J directive, work does not proceed to the
> round-trip tests until the reviewer ratifies the single carve-out
> below (routed in parallel).
>
> Layer 2 is **staging behavioural corroboration** and is **explicitly
> NOT a release-blocking gate** (issue [#110](https://github.com/Wicked-Evolutions/abilities-for-fluent-plugins/issues/110) sequencing; Layer 1 `tests/Contract/` is the gate, shipped in #111).

---

## 1. The J directive (fixture-strategy amendment) — recorded verbatim-faithful

1. **One persistent canonical test user**, marker `[SPRINT-V2-TEST] canonical`, on dev2. **NEVER deleted.** Reused as the subject for **all** cross-plugin associations (CRM contact / email / automation / sequence, messaging, community course & space membership, form entries, etc.).
2. **Any test requiring full user create / update / delete uses a SEPARATE throwaway user** — strict teardown + post-teardown read-back-absent + 0-residue. **Never the canonical user.**
3. The canonical user is a **DECLARED standing fixture**: recorded in this GATE-0 report (id + marker + provenance), and **EXPLICITLY EXEMPT** from the residue-sweep "absent / 0-residue" postcondition. **Every other fixture remains under the strict rule.**
4. Do **not** proceed to the round-trip tests until the reviewer ratifies this one carve-out (routed in parallel).

## 2. The single carve-out requiring ratification

> **CARVE-OUT (1 of 1):** the canonical test user is a permanent
> standing fixture and is **exempt** from the binding Layer-2 fixture
> postcondition "teardown WITH post-teardown read-back confirming
> absent; residue sweep as an explicit test postcondition" (issue #110
> Layer-2 bullet; the same bar gated on every #102/#103/#107 live
> verify).

This is a **deliberate, single, named exception** to a discipline that
has been binding all engagement. It is scoped as narrowly as possible:

- **Exactly one** entity is exempt (the canonical user, by id + marker).
- **Nothing else** is exempt. Every other fixture — throwaway users,
  CRM/messaging/community/form association records created during a
  round-trip — remains under strict marker-scoped teardown +
  post-teardown read-back-absent + 0-residue, as an explicit test
  postcondition.
- The exemption is from the **absent/0-residue** postcondition only.
  The canonical user is still: marker-scoped, declared here with
  provenance, build-identity-context recorded, and **never** the
  subject of a destructive-class (V8 wipe) operation (V8 wipe MUST NOT
  be exercised on shared staging at all — issue #110).

**Rationale (why a standing fixture, not per-run create):** the
round-trip pattern is "invoke ability → read entity back THROUGH the
vendor model → assert shape". Cross-plugin association subjects (a CRM
contact that is also a community member, a messaging participant, a
form submitter) are expensive and fragile to stand up per run on a
persistent shared site, and a mid-run fatal in the class under test
(the #106-class the corroboration exists to catch) would otherwise
strand per-run user residue — the exact failure the strict rule
prevents. A single declared, reused, never-deleted subject removes
that residue surface entirely for the association subject while
keeping the strict rule everywhere it still applies.

**Reviewer decision required:** ratify / reject / amend this one
carve-out. Round-trip test authoring is **blocked** until ratified.

## 3. Declared standing fixture (provenance) — VALUES PENDING CREATION

The canonical user is **not yet created** ("wait for ratification"
disposition). On ratification it will be created via an MCP ability
(abilities-first; no SSH user create) and this table filled in the
same commit that adds the first round-trip test:

| Field | Value |
|---|---|
| Role | Canonical cross-plugin association subject (Layer 2) |
| Marker | `[SPRINT-V2-TEST] canonical` (display name / first name carries the marker) |
| User ID | _TBD — recorded here at creation_ |
| Login / email | _TBD — marker-scoped, non-deliverable domain_ |
| Host (dev2) | _TBD — see §4_ |
| Created via (ability) | _TBD — the MCP create-user / CRM-contact ability used_ |
| Created at | _TBD_ |
| Lifecycle | **PERSISTENT — NEVER DELETED.** Exempt from residue-sweep absent/0-residue (the §2 carve-out). |
| Residue rule | **Exempt (declared).** All other Layer-2 fixtures: strict. |

## 4. dev2 resolution — Layer-2 staging provisioning prerequisite

"dev2" is **not** an existing MCP-bridge target (bridge exposes
`wickedevolutions, wicked-community, wicked-test1, wicked-knowledge,
helenawillow, abilitiesforai`) and is **not** `dev.helenawillow.com`
as issue #110 tentatively named. Per the directive author's
disposition, dev2 = **a NEW site added to the abilities bridge with
full ability scope across both ability plugins** (the WordPress
Abilities API plugin + `abilities-for-fluent-plugins`).

**Recorded prerequisite (blocks creation, not this report):** before
the canonical user can be created, dev2 must be provisioned —
bridged, full ability scope on both ability plugins, WP/PHP parity,
Fluent Pro licensed (issue #110 Layer-2 precondition). This is an
infrastructure/MCP-bridge configuration step outside this repo; it is
recorded here as a declared dependency, not silently assumed. It is
**not** a v1.4.0 release blocker (Layer 2 is non-gating).

## 5. Explicit STOP

- ✅ GATE-0 report authored (this document): strategy + carve-out +
  declared-fixture provenance template + dev2 prerequisite.
- ⛔ Canonical user **NOT** created (awaiting ratification + dev2).
- ⛔ Round-trip tests **NOT** authored/run (blocked on ratification).
- ⛔ No outward/irreversible action taken on any staging site.

Next action is the reviewer's: ratify / reject / amend the §2
carve-out. On ratification **and** dev2 provisioning: create the
canonical user via an MCP ability, fill §3, then begin Layer-2
round-trip tests under the strict rule (canonical-user carve-out
applied, everything else 0-residue).
