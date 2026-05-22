# Layer 1 Contract Gate — Coverage Scope (issue #110)

> Binding honesty discipline (#98 / #105 / #107-Item-4): this gate
> states exactly what it **deterministically catches** and exactly what
> it **does NOT**. No exhaustiveness is claimed. A scoped non-catch is
> recorded here, never silently dropped. Layer 1 is the release gate;
> it depends on nothing external (no WordPress site, no network, no DB,
> no staging, no license). Layer 2 (staging corroboration) is **not**
> built here and is **not** a release blocker.

Runner: `composer test:contract` (PHPUnit `Contract` testsuite). Runs
every PR. 20 tests today; the binding is the behaviour, not the count.

---

## SchemaValidityTest

**Catches (deterministic):** every registered `input_schema` /
`output_schema` reflected (registrar booted; **callbacks never
invoked**) is validated as a document against the committed, offline
JSON Schema **draft 2020-12** meta-schema. Flags malformed keyword
shapes, a leaf that is a string where an object is required, a
`properties`/`type` value of the wrong JSON type, `output[x]` declared
as a non-array — the P-B/P-D/P-H schema-shape class — before it can
fatal a live `validate_output()`.

**Does NOT catch:** semantic correctness of a well-formed schema vs
vendor behaviour (that is VendorContractDiffTest's narrower job).
**Deliberate scoped normalisation:** an empty PHP `array()` at an
object-position keyword (`properties`, `patternProperties`, `$defs`,
`definitions`, `dependentSchemas`) is normalised to `{}` before
meta-validation. PHP cannot distinguish an empty list from an empty
map; that `[]`-vs-`{}` ambiguity is a representation nuance, **not**
the malformed-shape class this gate targets. Policing empty-`properties`
encoding (project standard: omit empty `properties`) is a separate lint
concern, explicitly out of scope here. A **non-empty** value in those
positions is left untouched, so a genuinely malformed map is still
caught.

## RegistrationIntegrityTest

**Catches:** the booted ability surface vs `fixtures/registered-
abilities.manifest.json` by **set equality** (symmetric difference must
be empty) — names exactly which slugs were added or vanished. This is
the #104 over-strip / silent-drift detector. Also: every registered
ability exposes a callable `execute_callback`. The manifest count is
informational only — the assertion is the set, **never a hardcoded
`== N`** (a count nets two compensating deltas to the same number — the
drift trap the issue calls out).

**Does NOT catch:** whether a slug *should* exist (product decision).
An intended surface change is a reviewed manifest diff
(`php tests/Contract/tools/dump-manifest.php`).

## CrashClassScanTest ( = executable #108 )

**Catches (deterministic, zero site):** the #106/#107 crash class —
a value the code already encoded (`maybe_serialize` / `serialize` /
`json_encode` / `wp_json_encode`, or a variable traced to one in the
same function) assigned to `$model->encodedAttr` **or** passed as
`'encodedAttr' => …` into `Vendor::create([…])` / `$model->fill([…])`
/ `$model->update([…])`, where `$model` is intraprocedurally bound to a
**pinned** vendor model and `encodedAttr` is an attribute that model
itself encodes. The vendor encoded-attribute map is built by AST from
**pinned installed-vendor source** (`fixtures/vendor/`,
`PROVENANCE.json`) and **resolves inherited / trait / parent `$casts` +
mutators** (the issue's binding requirement) — verified by
`test_pinned_vendor_models_resolve_with_expected_encoded_attrs`. A
deliberately-bad sample (`fixtures/crash-samples/known-bad.php`, never
loaded as code) proves the scanner is not silently blind.

**Does NOT catch (scoped, tracked by follow-up #108):**
- vendor models **not pinned** here. Pinned set = the confirmed
  #106/#107 crash surface only (FluentBooking `CalendarSlot`/`Calendar`,
  FluentAffiliate `Affiliate`) + their parent/trait chain. Breadth
  expansion (the full inverse encoder-agnostic sweep) is **#108**, not
  claimed here.
- cross-function / cross-file laundering of a pre-encoded value (the
  binding is intraprocedural and best-effort).
- a vendor version newer than the pinned snapshot (the gate validates
  against the snapshot; refresh per `PROVENANCE.json` `refresh_policy`).
- unresolved ancestors are restricted to generic framework-internal
  concerns (the casting **engine**, no per-model `$casts`); an
  unresolved `App\Models` parent **fails the gate** (no silent
  under-resolution — `test_unresolved_ancestors_are_only_framework_
  internals`).

## VendorContractDiffTest

**Catches:** for the **pinned** crash-surface models — the columns the
#106/#107-fixed abilities write are still vendor-`$fillable`; the
vendor encoding contract for those columns is the locked expectation
(mutator→plain or a new serialized cast would change the fix premise);
and a concrete schema↔vendor field-name agreement sample
(`update-event-location-config.location_settings` ↔ CalendarSlot
column) — the get-customer/get-coupon/KD-2 drift technique.

**Does NOT catch:** a plugin-wide schema↔vendor reconciliation across
the full surface. Per-ability vendor association at large is the
`docs/vendor-map` + `VendorMapCoverageTest`'s job; its breadth is
tracked separately, not claimed here. This is a deterministic contract
**anchor** on pinned source, not a universal proof.

## StaticGuardsTest — V7 + V8 (static; the wipe is never executed)

**V8 catches:** every ability whose `input_schema` declares
`confirm_full_replace` must contain, **statically**, both the
`confirm_full_replace` reference and a typed-error
(`fluent_abilities_error` / `WP_Error`) guard. The destructive wipe is
**never invoked anywhere in this suite** — the test asserts the guard
*exists* (the F-CRM-01 lesson). Anchored on
`fluent-crm/update-contact-custom-fields`; the test fails loud if that
anchor vanishes.

**V7 catches:** an ability callback passing its raw first request param
directly into a write sink (`create`/`fill`/`update`/`insert`/
`insertGetId`/`replace`/`save`/`*OrCreate`) with no intervening
transform of that variable — the P1 #82 unfiltered-input shape.

**Does NOT catch:** V7/V8 operate on the registrar-array closure form
`$reg->read|write|delete( 'slug', [ … 'callback' => fn … ] )` (the
dominant form; 671 callbacks indexed). Abilities registered via a named
function reference or a non-closure shape are outside the static V7/V8
scope (CrashClassScanTest is **not** so limited — it scans every booted
file). V7 is a literal direct-pass-through check; cross-function
laundering of the raw param is out of deterministic scope.

## AbsentVendorPreconditionTest — V10 (non-staging mechanism)

**Catches:** the unit/contract process is itself the deliberately-
degraded fixture (no real Fluent vendor classes loaded — asserted by
`test_degraded_fixture_has_no_real_vendor_classes`). Abilities whose
**first** statement is a `! class_exists/function_exists/interface_
exists(...)` precondition returning `fluent_abilities_error(...)` are
invoked with the vendor absent; each MUST return a typed `WP_Error`
and never fatal. This covers the class a parity-maintained staging
site **cannot** (Pro is always present there) — hence Layer 1, not
Layer 2, per the issue.

**Does NOT catch:** absent-vendor robustness of abilities that guard
*later* than the first statement (only provably side-effect-free
guard-first callbacks are invoked, by design — safety over breadth).
Those remain covered statically by their own guards but are not
runtime-exercised here.

---

## Out of Layer 1 by design

Layer 2 staging behavioural corroboration (round-trip read-back through
the live vendor model) is **not** built here and is **explicitly not a
release blocker** (issue #110 sequencing). The encoder-agnostic inverse
vendor-attribute sweep is **#108** (post-v1.4.0, non-gating). Naming
these here is the recorded scope boundary, not a silent deferral.
