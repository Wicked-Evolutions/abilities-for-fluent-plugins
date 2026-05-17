# Package 7 — Pre-close cleanup-and-verify (MINIMAL, post-SPLIT)

> Branch `fix/v1.4.0/p7-close` off `fix/v1.4.0-cold-start-findings @ 7785e88`
> (P6-merged, #92). Probe = incidental test surface; the target is product
> behavior on the vendor contract (`docs/PRODUCT-SCOPE.md` / Addendum 19).
> Disposition: **SPLIT (reviewer)** — the schema-output defect surfaced during
> verification is its own **Package 7.1** (next), NOT touched here.

## Scope landed in this PR

### 1. Three never-functional abilities REMOVED (unregistered)

No working contract to preserve on any of these — broken since FluentCRM
v2.0.0. Unregistered (registration block replaced with a tombstone comment),
not repointed/re-specced (a new contract is out of close scope).

| Ability | Why never functional | Tombstone |
|---|---|---|
| `fluent-crm/get-report-top-campaigns` | Proxies `GET /fluent-crm/v2/reports/top-campaigns` — no such route in the installed vendor route table (`app/Http/Routes/api.php`); `rest_do_request` → 404. P4b separable-defect bounce; reviewer disposition = deprecate. | `includes/crm/extended-reports.php` |
| `fluent-crm/set-global-email-style` | Vendor `TemplateController::setGlobalStyle` reads `config`; ability schema/forwards `style` → schema-valid input saved an empty config and returned a misleading success. P5 reclassification escalation; reviewer disposition = deprecate. | `includes/crm/extended-templates-and-patterns.php` |
| `fluent-crm/list-subscribers-prev-next-ids` | Vendor `SubscriberController::getPrevNextIds` requires `filter_type`+`current_id` and never reads `id` (the only schema-`required` field) → rejected 100% of schema-valid input. P5 reclassification escalation; reviewer disposition = deprecate. | `includes/crm/extended-subscribers.php` |

Vendor-map rows in `docs/vendor-map/fluent-crm.json` flipped to
`REMOVED (v1.4.0 P7 close)`.

### 2. `fluent-crm/create-template` FIXED (F-FORMS-01 / P3c pattern)

Callback-only change (no schema change). The write is routed so title/body
persist via the vendor `POST /fluent-crm/v2/templates` (`{template:{...}}`),
the persisted `template_id` is required back, and the ability returns a
**read-back** of the persisted record (`GET /templates/{id}`), not an input
echo. Typed `WP_Error` on missing title / no persisted id / proxy error.

**V3 read-back evidence** (live, helenawillow, FluentCRM, 2026-05-16):

```yaml
ability: fluent-crm/create-template
input:
  title: "P7 V3 readback probe"
  email_subject: "P7 subject"
  email_body: "<p>P7 body</p>"
write_route: "POST /fluent-crm/v2/templates {template:{post_title,post_content,email_subject,edit_type:html,...}}"
persisted_template_id: 5112
readback_route: "GET /fluent-crm/v2/templates/5112"
readback_assertions:
  template.template_id: 5112        # vendor primary id, not positional (V9)
  template.post_title: "P7 V3 readback probe"   # persisted == requested (not input echo)
  template.post_content: "<p>P7 body</p>"        # persisted body matches
result: PASS
cleanup: template 5112 deleted post-verify; 0 residue
```

### 3. `fluent_abilities_unwrap_paginator()` single-key-wrapper fix — live-verified CORRECT

The P-J helper now descends a single-key assoc wrapper whose sole value
resolves to a paginator. Vendors wrap the paginator under **their own** key,
which need not equal the schema `items_key`:

- `CampaignAnalyticsController::getUnsubscribers` → `['unsubscribes' => $paginator]` (items_key `unsubscribers`)
- sequences-for-subscriber → `['sequence_trackers' => $paginator]` (items_key `sequences`)

Both a pre-loop single-key branch and a bounded in-loop single-key descent
were added (the prior foreach-over-all-values scan is removed — it was never
the cause of the fatal; see SPLIT below). Clean: only the sole value, only
when it resolves to a paginator, so a legitimate single-row assoc result is
never mis-descended. `php -l` clean.

**Live-verified CORRECT at the data layer** (helenawillow, FluentCRM
populated, opcache cycled via own-worker recycle, 2026-05-17), via a direct
proxy+unwrap probe (bypassing `validate_output`):

```yaml
fluent-crm/list-campaign-unsubscribers:
  input: { id: 191 }
  vendor_raw: { "unsubscribes": { current_page:1, data:[<1 row + nested subscriber>], per_page:15, total:1, ... } }
  unwrap_out: { "unsubscribers": [ <1 real row + nested subscriber> ], total:1, page:1, per_page:15 }
  result: CORRECT (canonical shape, real rows, no paginator-internals leak)

fluent-crm/list-sequences-for-subscriber:
  input: { subscriber_id: 1 }
  vendor_raw: { "sequence_trackers": { current_page:1, data:[<3 rows>], ... } }
  unwrap_out: { "sequences": [ <3 real rows> ], total:..., page:..., per_page:... }
  result: CORRECT (canonical shape, 3 real rows)
```

## SPLIT — Addendum 27 → Package 7.1 (NOT in this PR)

Verifying the 4 pending read-backs through `ability->execute()` surfaced a
**separate, broad executable defect** in WordPress core output validation:

```
Fatal error: Uncaught TypeError: Cannot access offset of type string on string
  in wp-includes/rest-api.php:2203
  #6 class-wp-ability.php(573): rest_validate_value_from_schema()
  #7 class-wp-ability.php(638): WP_Ability->validate_output()
```

**Root cause:** `fluent_abilities_schema_list_output($key, $obj)` /
`_collection_output` / `_item_output` (in `includes/schemas.php`) assign the
schema fragment `$obj = {type:object, additionalProperties:true}` into the
JSON-Schema `properties` map. That declares a phantom property literally
named `type` whose schema is the **string** `"object"`. Every FluentCRM/Cart
row carries a real `type` column, so WP core recurses into a string and
fatals. Blast radius: every populated-response read using these helpers.
Cold-start signature: empty probe site passes per-item validation, populated
site fatals — this is **why the 4 P-J/Cart read-backs were "pending"**.

**Disposition (reviewer): SPLIT.** The schema-output discriminator fix is
its own **Package 7.1** (next). `includes/schemas.php` is **NOT** touched in
this PR. The 4 read-backs (`list-campaign-unsubscribers`,
`list-sequences-for-subscriber`, `fluent-cart/get-customer`,
`fluent-cart/get-coupon`) are **NOT flipped to VERIFIED** — vendor-map rows
record them as routed to Package 7.1 under Addendum 27, **code-routed, not
infra-deferred** (supersedes the prior opcache / test-env-topology framing).
P7's `unwrap_paginator()` fix is itself live-verified correct and ships here;
it is the necessary data-layer half — Package 7.1 supplies the schema half.

## Gate

- **Sprint Plan 3-line:** (1) removed 3 never-functional CRM abilities
  (deprecate disposition on P4b/P5 escalations); (2) fixed
  `create-template` (vendor write + read-back return, V3 PASS template_id
  5112); (3) `unwrap_paginator()` single-key fix live-verified correct —
  schema-output defect SPLIT to Package 7.1 (Addendum 27), 4 read-backs not
  flipped.
- **V3 read-back:** create-template PASS (YAML above). The 3 removals + the
  unwrap fix are not writes (V3 N/A).
- **V11:** (a) input — N/A (no input-schema change); (b) behavior — 3
  abilities now `not found` (intended deprecation), create-template routes
  the write + returns read-back; (c) write — create-template persists via
  vendor model, verified + cleaned (0 residue); (d) error — typed `WP_Error`
  on create-template precondition failures; (e) response-shape —
  create-template returns persisted read-back; unwrap canonical
  `{<key>:[...],total,page,per_page}` (data-layer verified).
- **Vendor-map:** 3 CRM rows → `REMOVED`; 4 read-back rows →
  `PENDING — NOT flipped; routed to Package 7.1 (Addendum 27)`.
- **Ledger:** PRINCIPLES-VENDOR.md drift table — Addendum 27 row added;
  set-global-email-style row annotated with the P7 deprecate disposition.

## Deploy / restore receipts (0 residue)

- helenawillow plugin path:
  `/home/u748067201/domains/helenawillow.com/public_html/wp-content/plugins/abilities-for-fluent-plugins`
- create-template verify: live PASS, template 5112 deleted post-verify.
- `helpers.php` deployed for the P-J data-layer verification
  (md5 `641e0b1e4ddd278cf02946b6fdd281cd`), then **restored to parent
  `7785e88` blob** (md5 `6089e3cf17ada725282eee7ab9a993fa`,
  == `git show 7785e88:includes/helpers.php`), opcache cycled.
- All probe temp files removed (`/tmp/pj_probe.php`, `/tmp/pj_raw.php`,
  `/tmp/helpers_parent.php`). Probe surface returned to `7785e88`; 0 residue.
