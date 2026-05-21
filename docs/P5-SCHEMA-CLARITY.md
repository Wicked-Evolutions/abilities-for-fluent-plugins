# Package 5 — V4 Schema Clarity (editorial) + reclassification escalations

> Editorial pass — **Principle 4 absolute**: no field renamed, no callback/
> behavior changed, no schema structure/type/enum/required altered (the single
> exception: one P-D factually-corrective output-type widen, below). Probe =
> incidental test surface; the target is product behavior on the vendor
> contract (`docs/PRODUCT-SCOPE.md` / Addendum 19). V3 read-back **N/A** (no
> writes). V11 (a)–(e): **(a)/(b)/(c)/(d) N/A** (no input/behavior/write/error
> change); **(e) N/A** (no response-shape change) — editorial only, except the
> P-D widen which is V5-corrective (no false rejection).

## P-C — input-schema description clarity (≈70 slug-lines / 7 plugins) — PATCHED
Per-slug: appended plain-language description stating the exact handler-expected
field name/shape (e.g. "the identifier is `campaign_id` (not `id`)", "`tag_ids`
is a comma-separated string, not an array", "`status` is the string
\"yes\"|\"no\", not a boolean"). **No field renamed** (Principle 4). Stale
`Source: Class::method` citations corrected on touched slugs where verified
against installed source.
- FluentCRM (incl. Addendum 2): `update-campaign`, `update-funnel-title`,
  `update-recurring-campaign-labels`, `export-subscribers`, `attach-tag`,
  `detach-tag`, `do-bulk-action-contacts`, `do-bulk-action-funnels`,
  `update-contact-custom-fields` (**Addendum 2**: registrar `Source:` corrected
  `CustomFieldsController` → `\FluentCrm\App\Http\Controllers\CustomContactFieldsController::saveGlobalFields`).
- FluentCart: `extend-license-validity`, `reorder-attribute-term`,
  `update-variant-inventory`, `update-payment-method`, `update-storage-driver`,
  `update-eu-vat-config`, `bulk-update-products`.
- Fluent Forms: `update-form`, `delete-submission-note`, `get-form-integration`,
  `get-integration-list-ids`, `toggle-integration-status`, `export-entries`,
  `global-search`, `get-submission`.
- FluentCommunity/Messaging: `update-space`, `delete-space`, `update-course`,
  `follow-user`, `unfollow-user`, `check-is-following`, `create-comment`,
  `add-reaction`, `remove-reaction`, `list-reactions`, `search-members-mention`,
  `cast-survey-vote`, `update-crm-tagging-config`, `emit-event`, `get-lesson`,
  `fluent-messaging/get-thread`, `fluent-messaging/send-message`.
- Fluent Bookings: `list-calendar-conflicts`, `get-available-slots`,
  `list-remote-calendars`, `get-zoom-account`, `get-calendar-integration`,
  `get-booking-activities`, `get-booking-meta`, `update-booking-status`,
  `set-booking-meta`, `list-booking-hosts`, `add-booking-note`.
- FluentPlayer: `create-media-tag`, `get-media`, `get-media-metadata`,
  `get-youtube-captions`, `analytics-video-stats`,
  `bunny-storage-create-directory`, `list-integrations`, `list-email-providers`,
  `list-provider-resources`, `mux-get-live-stream`, `search-media`.
- FluentBoards (P-C / F-1): `delete-stage` (**§8 F-1** id-vs-stage_id),
  `move-task`, `update-subtask-position`, `convert-task-to-subtask`,
  `archive-task`.

Where a subagent found the brief's premise differed from installed code (e.g.
`set-booking-meta` uses `value` not `meta_value`; `search-media` field is `q`;
`update-recurring-campaign-labels` already uses `labels`), the description was
clarified **to match the installed code** (truthful schema), not the brief.

## P-D — Boards output type drift — PATCHED (factually-corrective, V4+V5)
- `fluent-boards/list-tasks-by-stage`: output `position` declared
  `['number','null']`; vendor returns a numeric **string** ("0.00") →
  widened to `['number','string','null']` (prevents false rejection; no
  behavior change) + description note.
- `fluent-boards/get-template-detail`: `stages` output is untyped `array`
  (no false-reject) → **description note only**: vendor returns stage
  `id`/`position` as strings vs sibling integer (vendor type drift).

## RECLASSIFICATION ESCALATIONS — STOP, NOT fixed in P5 (dispatch rule)
The dispatch reclassification rule: a focused reproduction proving executable
behavior is wrong (handler rejects vendor-valid input / schema accepts an input
the handler crashes-or-noops on / oneOf forbids a shape the handler accepts) →
**STOP, escalate to the package owning that principle; do NOT self-elect or
fold a behavior fix into P5.** Three findings hit this: #1 and #2 are
escalated-out (orchestrator-routed, no dev action); **#3 (Boards P-B ×5) was
reviewer-directed to be folded into THIS PR corrected against installed-source
truth — RESOLVED below (anyOf, NOT oneOf).**

1. **`fluent-crm/set-global-email-style`** — vendor
   `TemplateController::setGlobalStyle` reads the `config` key; the ability
   schema declares/forwards `style`. A schema-valid `{style:{…}}` is forwarded
   verbatim, the handler reads absent `config`, **silently saves an empty style
   and returns success** — input discarded, misleading success (P-L-shape:
   success but state ≠ input). Executable-behavior wrong, not naming.
   → **Escalate to the write-correctness owner (Package-3 / P-L family);
   orchestrator routes.** No P5 edit applied.

2. **`fluent-crm/list-subscribers-prev-next-ids`** — vendor
   `SubscriberController::getPrevNextIds` requires `filter_type` + `current_id`
   and never reads `id`; `id` is the ability schema's only `required` field.
   The handler **rejects 100% of schema-valid inputs** ("filter_type and
   current_id are required"). The schema's required contract is factually
   unusable — beyond a description clarification (the `required` array is wrong).
   → **Escalate (contract/behavior); orchestrator routes.** No P5 edit applied.

3. **`fluent-boards` P-B ×5 — RESOLVED in this PR (reviewer-directed, no split).**
   The dispatch prescribed `oneOf` ("exactly one; both rejected"). Installed
   handler source **contradicts the premise** (Vendor-Contract "caution on
   principle-checking" → installed-source wins). Reviewer disposition: fold the
   **corrected** constraint into #91 as **`anyOf` ("at least one" / not-neither),
   explicitly NOT oneOf** — both-supplied AND either-supplied stay accepted,
   only neither rejected.

   **(a) Constraint = `anyOf`** added to each input_schema:
   `anyOf:[ {required:[attachment_id]}, {required:[<url>]} ]` (`<url>` =
   `image_url`, or `csv_url` for `upload-csv`). oneOf was NOT used (it would
   false-reject the handler-valid both-case = Principle-4 violation).

   **(b) Per-slug installed-handler-source citation** (precedence chain — all 5
   identical shape: `$attachment_id=(int)($input['attachment_id']??0); if($attachment_id){…} elseif(!empty($input['<url>'])){…} ; if(!$resolved) return fluent_abilities_error('ability_invalid_input','Provide attachment_id or <url>.')`):
   - `upload-board-background-image` — `includes/boards/abilities-discovery.php` (`if($attachment_id) wp_get_attachment_url elseif(image_url) media_sideload_image; if(!$image_url) error`)
   - `add-task-cover-image` — `includes/boards/abilities-tasks-extended.php` (same; writes task.settings.cover_image)
   - `upload-comment-image` — `includes/boards/abilities-comments-replies.php` (same)
   - `add-task-attachment` — `includes/boards/abilities-attachments.php` (`if($attachment_id) get_post elseif(image_url) media_sideload; else error`)
   - `upload-csv` — `includes/boards/abilities-import.php` (`if($attachment_id) get_attached_file elseif(csv_url) sideload; else 'Provide attachment_id or csv_url.'`; note: this slug has no top-level `required` — `anyOf` is the only cross-field constraint)

   **(c) Principle-10 changelog note (constraint addition on released abilities —
   factually-corrective, NOT silent):** adding `anyOf` to these 5 released
   abilities is a *factually-corrective* schema constraint, not a behavior
   change: (1) it breaks **no working call** — the neither-input case already
   failed at the handler (`Provide attachment_id or <url>.`); `anyOf` now
   rejects it one layer earlier with a typed schema error; (2) it **false-rejects
   no handler-valid input** — both-supplied and either-supplied still satisfy
   `anyOf` and reach the unchanged handler; (3) handler/callbacks untouched.
   Documented here and surfaced in the PR body changelog snippet (CHANGELOG.md
   is scaffold-owned — orchestrator integrates).

   **(d) Focused live verify** (representative `upload-board-background-image`,
   FluentBoards on the helenawillow probe — incidental surface; board 13,
   deploy→verify→restore→0 residue):
   - **both-supplied** `{board_id:13, attachment_id:999999999, image_url:"https://example.com/…"}` → reached the handler, returned the **handler** message `"Provide attachment_id or image_url."` ⇒ schema `anyOf` **accepted** the both-case (no false-reject); handler precedence unchanged.
   - **neither-supplied** `{board_id:13}` → `"invalid input. Reason: input does not match any of the expected formats"` ⇒ **schema-layer `anyOf` typed rejection, pre-handler** (previously this passed schema and failed only at the handler).
   - Probe `includes/boards/abilities-discovery.php` restored to parent `44f3991` (post-restore md5 `7db95aa8c83565d6271a510e33b50538` == parent blob; pre-restore = p5 build `8173be02…`). No fixtures created (fake ids failed at handler — no upload); `/tmp` cleaned. **0 residue.**

## Acceptance
- Every P-C / routed-V4 / P-D finding **patched** (editorial; one P-D
  factual type-widen) — 33 files, 72/72 1-for-1, all `php -l` clean, no
  behavior/rename/structural change.
- The 3 reclassifications **logged here with one-line rationale + escalation
  target**, per the dispatch rule (not folded into P5).
- Multisite: editorial/description + one type-widen — vendor-contract-level,
  site-config-agnostic; no single/multisite branching. Flagged, not gated.
