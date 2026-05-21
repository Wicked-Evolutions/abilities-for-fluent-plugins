# Registrar Development — Fluent Suite Registrar Bundle Sprint

> **Audience:** Per-plugin dev chats working on a feature branch in the **Fluent Suite Registrar Bundle Sprint** (target release: `abilities-for-fluent-plugins` v2.0.0).
>
> **Sprint plan:** [`Plans/Alpha Release Gate/Fluent Suite Registrar Bundle Sprint 2026-05-13.md`](../../../00%20Influencentricity%20OS/Plans/Alpha%20Release%20Gate/Fluent%20Suite%20Registrar%20Bundle%20Sprint%202026-05-13.md) (vault path; binding scope, branching shape, per-plugin gates, release-coupling criteria).
>
> **Principles contract:** [`PRINCIPLES.md`](../PRINCIPLES.md) at repo root (binding 11-principle Ability Suite Compatibility Contract).
>
> **Known v1.1.3 defects:** [`docs/V1.1.3-KNOWN-DEFECTS.md`](V1.1.3-KNOWN-DEFECTS.md) — read before writing code that touches existing v1.1.3 abilities.

---

## What this sprint is

`abilities-for-fluent-plugins` v2.0.0 expands the registered Ability surface across seven Fluent plugins (FluentCRM, FluentCart, Fluent Forms, Fluent Bookings, Fluent Boards, FluentCommunity, FluentPlayer Pro) by ~728 abilities on top of the 272 currently shipping in v1.1.3. Total projected v2.0.0 surface: ~1000 abilities for the seven covered plugins.

The expansion is **additive in contract**: every ability shipping in v1.1.3 keeps its slug, schemas, permission_callback, and runtime behavior unchanged in v2.0.0. The Stable Ability Contracts principle is the binding gate.

Seven dev chats run in parallel, each scoped to one Fluent plugin, each branching from the shared `integration/fluent-suite-registrar-v2` branch.

---

## Branching shape

```
main (v1.1.3 LIVE — frozen during sprint, hotfix lane open)
  │
  └── integration/fluent-suite-registrar-v2  ← long-lived
        │
        ├── (Phase A) chore/scaffold-v2.0.0  ← orchestrator-owned (this PR)
        │
        ├── feat/fluentcrm-registrar         ← CRM dev chat
        ├── feat/fluentcart-registrar        ← Cart dev chat
        ├── feat/fluentforms-registrar       ← Forms dev chat
        ├── feat/fluentbookings-registrar    ← Bookings dev chat
        ├── feat/fluentboards-registrar      ← Boards dev chat
        ├── feat/fluentcommunity-registrar   ← Community + Messaging dev chat
        └── feat/fluentplayer-registrar      ← FluentPlayer dev chat (greenfield)
```

Each dev chat works in its own worktree on disk to avoid filesystem collisions. Worktree creation is orchestrator's responsibility; chats receive a ready worktree path in their dispatch brief.

---

## File ownership contract

### Chat-owned (per plugin)

A dev chat may add, edit, or remove files **only** within:

- `includes/{plugin}/` — your plugin's module folder (existing for six plugins; `includes/player/` newly stubbed for FluentPlayer)
- `tests/Unit/{Plugin}/` — your plugin's unit test folder (newly stubbed in scaffold PR)

Renaming or restructuring existing files within your owned folder requires **orchestrator approval** before committing.

The seven plugin-to-folder mappings:

| Plugin chat | Owned source folder | Owned test folder |
|---|---|---|
| FluentCRM | `includes/crm/` | `tests/Unit/CRM/` |
| FluentCart | `includes/cart/` | `tests/Unit/Cart/` |
| Fluent Forms | `includes/forms/` | `tests/Unit/Forms/` |
| Fluent Bookings | `includes/booking/` | `tests/Unit/Booking/` |
| Fluent Boards | `includes/boards/` | `tests/Unit/Boards/` |
| FluentCommunity (+ Messaging) | `includes/community/` AND `includes/messaging/` | `tests/Unit/Community/` |
| FluentPlayer (greenfield) | `includes/player/` (new module) | `tests/Unit/Player/` |

### Scaffold-owned (orchestrator integrates chat-requested edits at merge)

If your work needs changes to any of the following **central files**, describe the needed edit in your PR body under a heading `## Scaffold-owned edits requested`. Orchestrator integrates them at merge time. Do NOT edit these files directly:

- `abilities-for-fluent-plugins.php` — main loader; conditional `require_once` wiring per Fluent product
- `includes/ability-categories.php` — central category registration
- `includes/schemas.php` — shared schema fragments (only edit if your shared schema is used by ≥2 plugins)
- `includes/helpers.php` — shared helpers (only edit if your helper is used by ≥2 plugins)
- `includes/security.php` — capability checks + nonce flows (security-sensitive; orchestrator + Reviewer scrutiny)
- `includes/class-registrar.php` — core registrar wrapper (security-sensitive; orchestrator + Reviewer scrutiny; affects Stable Ability Contracts gate)
- `CHANGELOG.md` — release notes (orchestrator appends per-plugin section at merge using your changelog snippet from PR body)
- `tests/bootstrap.php` — test harness (cross-suite test impact; orchestrator approval required)

### Cross-chat collision protection

Cross-chat collision is structural: chats own non-overlapping folders. Shared edits surface only at scaffold-owned files, where orchestrator is the single writer. Two chats never edit the same file directly.

---

## Slug naming convention

Inherited from v1.1.3 and unchanged in v2.0.0:

```
{category}/{verb}-{noun}
```

Examples:

- `fluent-crm/list-contacts` (read)
- `fluent-cart/create-product` (write)
- `fluent-boards/delete-task` (delete)
- `fluent-player/list-media` (read; greenfield — `fluent-player` category newly registered)

**Categories** are the per-plugin slugs declared in `includes/ability-categories.php`:

- `fluent-crm`, `fluent-cart`, `fluent-forms`, `fluent-booking`, `fluent-boards`, `fluent-community`, `fluent-messaging`, `fluent-player` (newly added in this sprint), plus the unchanged-in-this-sprint `fluent-affiliate`, `fluent-support`, `fluent-smtp`, `fluent-snippets`, `fluent-auth`, `fluent` (cross-module)

**Verbs** follow the existing v1.1.3 convention. Common verbs:

- Read: `list`, `get`, `search`, `count`, `find`
- Write: `create`, `update`, `set`, `add`, `assign`, `attach`, `move`, `duplicate`
- Delete: `delete`, `remove`, `detach`

If your new ability needs a verb not in the existing set, add it consistently and document the choice in PR body.

---

## Test pattern

Mirror the existing v1.1.3 test patterns at `tests/Unit/RegistrarTest.php` and `tests/Unit/SchemasTest.php`. Each plugin chat adds tests under its owned `tests/Unit/{Plugin}/` folder.

Required test coverage per the Phase B acceptance gate:

- **All read paths** — every read ability has at least one test that asserts its registered shape + an execution test that asserts a representative response shape against a fixture
- **All write paths** — every write ability has at least one test that asserts its registered shape + an execution test that exercises the create/update path
- **All delete paths** — every delete ability has at least one test that exercises the delete path
- **Permission failure paths** — every ability has at least one test that asserts the `permission_callback` rejects unauthorized requests

Run unit tests locally with:

```bash
vendor/bin/phpunit --testsuite Unit
```

CI must be green before opening PR for Reviewer.

---

## Stable Ability Contracts discipline

**Every Ability shipping in v1.1.3 is FROZEN in v2.0.0.** Slugs, input_schemas, output_schemas, permission_callbacks — all contract-identical. Runtime behavior preserved (with the explicit known-defects ledger preservation expectations).

**You may only ADD abilities in this sprint.** You may NOT:

- Rename any existing v1.1.3 ability slug
- Modify any existing v1.1.3 ability's input_schema or output_schema
- Modify any existing v1.1.3 ability's permission_callback
- Restructure or refactor any existing v1.1.3 ability registration block (`$reg->read|write|delete('slug', [...])`)
- Silently fix any [`V1.1.3-KNOWN-DEFECTS.md`](V1.1.3-KNOWN-DEFECTS.md) entry for your plugin

If you find a NEW v1.1.3 defect during your work that's not in the ledger, follow the dev-chat protocol in `V1.1.3-KNOWN-DEFECTS.md` (STOP, report to orchestrator, wait for disposition).

### Phase B per-plugin source-diff command (binding)

Before opening your PR, run this command and paste the full output into your PR body under a `## Stable Contracts source diff` heading:

```bash
git diff v1.1.3 -- includes/{plugin}/ tests/Unit/{Plugin}/
```

Replace `{plugin}` and `{Plugin}` with your plugin's owned-folder names per the [File ownership contract](#chat-owned-per-plugin) table. Examples:

```bash
# FluentCRM dev chat
git diff v1.1.3 -- includes/crm/ tests/Unit/CRM/

# FluentCart dev chat
git diff v1.1.3 -- includes/cart/ tests/Unit/Cart/

# FluentCommunity dev chat (covers community + messaging)
git diff v1.1.3 -- includes/community/ includes/messaging/ tests/Unit/Community/

# FluentPlayer dev chat (greenfield — entire diff is additions)
git diff v1.1.3 -- includes/player/ tests/Unit/Player/
```

Reviewer per-plugin checklist confirms: **no `-` (deleted) or `+` (modified) lines fall inside any existing `$reg->read|write|delete()` registration block**. Additions OUTSIDE existing registration blocks are expected — those are the new abilities being added.

If your diff shows ANY change inside an existing registration block, your PR will be rejected. Either back out the change (if accidental) or escalate per Stable Contracts protocol (if intentional and you believe an exception is warranted — escalation is to orchestrator + J, not unilateral).

---

## PR body template

> **Product scope is canonical:** read [`docs/PRODUCT-SCOPE.md`](PRODUCT-SCOPE.md)
> before filling this in. Site-agnostic product for arbitrary single-site **and**
> multisite WordPress; our sites are an incidental test environment, never the
> fix scope; site-coupled behavior is a defect; multisite is first-class. Every
> PR body carries the one-line pointer in the template below.

Every Phase B feature PR uses this template. Copy and fill in:

```markdown
> Product scope: site-agnostic, arbitrary single/multisite WordPress; probe
> sites are incidental test surfaces — see docs/PRODUCT-SCOPE.md.

## Sprint context

- Sprint: Fluent Suite Registrar Bundle Sprint 2026-05-13 v1.1
- Plugin: {Plugin name}
- Research input: {link to plugin's Ability Registrar Research file}
- Branch: feat/{plugin}-registrar
- Owned folder: includes/{plugin}/ + tests/Unit/{Plugin}/

## What this PR adds

{Per-plugin summary: N new abilities across M clusters. Brief description of what's covered.}

## Stable Contracts source diff

```
{Paste full output of: git diff v1.1.3 -- includes/{plugin}/ tests/Unit/{Plugin}/}
```

Reviewer-verifiable: no `-` or `+` lines fall inside any existing `$reg->read|write|delete()` registration block. All changes are additions outside existing blocks.

## Live verification evidence

{Per-cluster: which abilities executed against which probe site, with stdin/stdout JSONL evidence captured. Follow the cluster-type carve-out: mutable / read-only / non-deletable / permission-only per the sprint plan's Phase B gate item (e) restated section.}

Probe site: {wicked-community / helenawillow}
Testclient/fixture discipline: {confirmed applied where helenawillow was the probe site}

## Scaffold-owned edits requested

{If your work needs central-file edits, list them here. Orchestrator integrates at merge.}

Examples:
- `includes/ability-categories.php` — add new category `fluent-{plugin-suffix}` (greenfield only)
- `abilities-for-fluent-plugins.php` — add conditional require_once for new sub-file `includes/{plugin}/{new-sub-file}.php`
- `CHANGELOG.md` — append per-plugin sub-section content (snippet below)

CHANGELOG snippet:
```markdown
### {Plugin name}

- Added N new abilities across M clusters: {brief enumeration}
- Stable Contracts: all v1.1.3 abilities for this plugin preserved unchanged.
```

## v1.1.3 known-defects acknowledgement

{If your plugin has entries in `docs/V1.1.3-KNOWN-DEFECTS.md`, list which ones your work touches adjacency:}

- KD-N ({brief): preserved as-is per Stable Contracts; new ability {slug} avoids the defective pattern by {how}.

## Tests

- Unit tests: {N} new tests added under `tests/Unit/{Plugin}/`
- `vendor/bin/phpunit --testsuite Unit` — green
- CI status: {green / failing — with reason}

## Deviations

{Either: "None" — or describe any deviation from the dispatch brief / sprint plan with one-line justification.}
```

---

## Acceptance gate (per the sprint plan Phase B section)

Your PR is ready for merge into integration when:

- (a) All abilities listed in your plugin's research Proposed Ability inventory section are registered with slug, input_schema, output_schema, permission_callback matching research recommendations
- (b) Stable Contracts source diff (above) shows no `-`/`+` lines inside existing `$reg->...()` registration blocks
- (c) Unit tests added covering all read / write / delete / permission-failure paths
- (d) `vendor/bin/phpunit --testsuite Unit` green
- (e) Live verification per the cluster-type carve-out, fixture discipline applied where Helena is the probe site, evidence in PR body
- (f) Scaffold-owned edits described in PR body (if any)
- (g) PR body uses the template above + references this contributor guide + sprint plan + Principles v1
- (h) Reviewer per-plugin Source-check round passes

---

## When you're stuck

- **Ambiguous research recommendation** — surface to orchestrator. Do not invent or extrapolate.
- **Schema doesn't fit the proposed shape** — surface to orchestrator with source citation.
- **Vendor source ambiguity** — read the vendor source via `filesystem-read-file` against the probe site, cite file:line in your PR body.
- **Test failure you don't understand** — surface to orchestrator with reproduction. Do not skip the test.
- **You think the plan is wrong** — surface to orchestrator with rationale. Sprint plan amendments require Reviewer + J ratification; you do not amend unilaterally.

---

## After your PR merges

After orchestrator merges your PR into `integration/fluent-suite-registrar-v2`:

- Your dev chat is **done for this sprint** unless orchestrator dispatches a follow-up (e.g. fix-up PR after Reviewer notes, split-trigger sub-PRs, etc.).
- Phase C runs at orchestrator level — final integration → main release wave per sprint plan Phase C section.
- Your work ships in v2.0.0.
