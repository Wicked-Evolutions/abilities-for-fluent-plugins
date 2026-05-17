# Package 8 — FluentPlayer (full single fix package)

> Branch `fix/v1.4.0/p8-player` off `fix/v1.4.0-cold-start-findings @ 1565911`
> (post-P7.1). Source of truth: Phase-7 tester report *FluentPlayer — Full
> Row Set* (120 rows, helenawillow). FluentPlayer + Pro installed on
> helenawillow = the incidental live probe (Addendum-19 framing).
>
> **Authorizations of record:**
> - **Scope:** J directive 2026-05-17 (ledger **Addendum 26**) — work through
>   FluentPlayer completely before progressing, single package; supersedes the
>   Addendum-23 v1.5.0 deferral. FluentPlayer behavior is in v1.4.0 as
>   Package 8.
> - **Shared-root treatment:** **J-authorized 2026-05-17 (ledger
>   Addendum 32)** per the COLD START shared-root rule — ≥2 reproductions
>   evidenced (~30, one root: the proxy discarded the vendor
>   `WP_REST_Response` HTTP status); single shared fix in
>   `invoke_controller` (wpfluent `error→≥422` / `success→200` contract).
>   **Not reviewer-gated** — this is the J authorization the standing
>   shared-root rule requires.
> - **Technical drift record:** ledger **Addendum 28** (the shared-root /
>   drift entry — a technical record, not the scope or shared-root authority).

## Sprint Plan (3-line)
1. **Shared-root (J-authorized, ledger Addendum 32; technical record
   Addendum 28):** the FluentPlayer proxy discarded the vendor
   `WP_REST_Response` HTTP status, so every vendor `sendError()` reached the
   write callbacks as a bare body wrapped in `success:true` — fixed by
   preserving the status (wpfluent guarantees `sendError→≥422`,
   `sendSuccess→200`) and mapping ≥400 → typed `WP_Error`; one shared
   treatment, per-family live-verified.
2. **Per-slug:** analytics-performance-over-time scope→null + vendor-grounded
   output (V10/V5); create-playlist persisted-id read-back (V3/V9);
   create-media-tag read-back-resolved tag (V3/V9); list-media-tags
   serialization (V5); update-playlist/update-media existence guards (V10).
3. **Surfaced not changed:** update-settings contract-hardening (Outcome-4
   cross-plugin candidate, no shared treatment without J auth); P-E proven an
   MCP-adapter artifact, not a registrar defect; FluentPlayer confirmed not
   P7.1-phantom-affected.

## Audit (vendor-source-grounded; /tmp/fpv vendor tree, helenawillow)

The proxy `fluent_abilities_player_invoke_controller()`
(`includes/player/abilities.php`) normalised `WP_REST_Response →
get_data()` and **dropped the status code**. The wpfluent base controller
(`fluent-player/vendor/wpfluent/framework/src/WPFluent/Http/Controller.php:128-141`
+ `Response/Response.php:102-120`): `sendSuccess($data)` →
`WP_REST_Response($data,200)`; `sendError($data,$code)` →
`WP_REST_Response($data, max($code,422))`; **no top-level `success`
boolean is ever added**. Therefore **HTTP status ≥ 400 ⇔ vendor
`sendError()` ⇔ failure** — a single, fully vendor-grounded discriminator.

Pre-P8, every vendor `sendError()` (Bunny/Mux integration-disabled 422,
Media/Subtitle/TimedContent "Media not found" 404, validation 422, Layer
"Form plugin not active" 400) arrived as a bare body; the write callbacks
unconditionally `return array('success'=>true, …)`. That single root
explains the Phase-7 reproductions: ~30 `success:true`-envelope, the P-L
not-found false-successes, and the P-H error-string-in-array cases where
the vendor used `sendError()`.

Classification per Phase-7 pattern:
- **V5/V6 envelope (~30):** all `bunny-stream-*`, all `mux-*` write/get,
  `get-youtube-channel-info`, `test-integration-connection`, `delete-media`,
  `upload-subtitle` — shared-root. `bunny-storage-*` is the clean
  counter-example (its own `$bunny_storage_enabled()` precheck) — **must
  stay unchanged** (verified).
- **V10 P-L false-success:** `update-timed-content`, `remove-subtitle`,
  `import-youtube-captions`, `generate-youtube-storyboard` (vendor returns
  real `sendError(...404)` — shared-root); `update-playlist`,
  `update-media` (vendor **upserts-by-id, no 404** — per-slug existence
  guard required).
- **V10 product bug:** `analytics-performance-over-time` (scope reject).
- **V3/V9 write-correctness:** `create-playlist` (ID:0 — vendor `store()`
  un-reloaded), `create-media-tag` (term id discarded).
- **V5 serialization:** `list-media-tags` (empty name + `tagOptions:["Array"]`).
- **P-E:** `list-media`, `list-playlists` "Connection closed".
- **Contract-hardening:** `update-settings`.
- **P7.1 confirmation:** FluentPlayer registrars pass **explicit property
  maps** to the schema-output helpers, never the `$obj` fragment — **not
  Addendum-27-phantom-affected**; the P7.1 discriminator covers them
  trivially. Confirmed, not re-fixed.

## Fixes

### Shared-root — `fluent_abilities_player_invoke_controller` (J-authorized, ledger Addendum 32; technical record Addendum 28)
Preserve `WP_REST_Response::get_status()`; `≥400` → `fluent_abilities_player_vendor_error()`
(404→`not_found`, 401/403→`forbidden`, 422/400→`vendor_precondition_failed`,
else `vendor_error`) carrying the vendor `message`/`errors`. Plus
`fluent_abilities_player_detect_disabled_leak()` — a tightly-bounded HTTP-200
detector for the Bunny-Stream/Mux **raw `return $service`** path (no
`sendError`): fires only when the body's sole signal is a
`/not enabled|not configured|not active|is disabled/i` message **and no
domain entity key is present** (genuine successes always carry an entity, so
it cannot misfire). Also handles Mux `handleWebhook`'s
`{success:true,result:{message}}` wrap. All three closures (`$invoke`,
`$call`, `$mux_call`) delegate to the proxy, so the treatment is genuinely
shared. **J-authorized shared-root (2026-05-17, ledger Addendum 32)** per
the COLD START shared-root rule (≥2 reproductions, one root, one shared fix)
— not reviewer-gated. Justification: the discriminator is the framework
contract itself (status code), not a heuristic; per-family live-verified
below.

### Per-slug
- **analytics-performance-over-time** — `scope='global'`/absent → pass
  `null` (the vendor dashboard path; `AnalyticsController::getPerformanceOverTime`
  AnalyticsController.php:596-653 rejects any non-empty scope ∉ {video,user}).
  `output_schema` redeclared to the real vendor shape
  `{metric,start_date,end_date,dates:[string],series:[{provider_key,name,data:[number]}]}`
  (`AnalyticsService::getPerformanceOverTime`→`buildSeriesByProvider`
  AnalyticsService.php:1162,1035); `start/end` mapped to vendor
  `start_date/end_date`.
- **create-playlist** — resolve persisted id from the model
  (`ID|id|post_id`); if unresolved, **read back** the newest `Playlist` by
  `post_title`; error if still none (vendor `store()` does not reload,
  contrast `update()` PlaylistController.php:42-57).
- **create-media-tag** — vendor discards the term id; **read back** via
  `getTags/getTagOptions`, resolve the created tag by exact name → return
  `tag:{name,slug}` (slug = the only stable vendor identifier).
- **list-media-tags** — always request `with_counts=1` (the no-count vendor
  branch is a flat `string[]`); emit `tags:[{name,count}]` and
  `tagOptions:[{name,slug}]` objects.
- **update-playlist / update-media** — existence pre-check via
  `Playlist::find` / `Media::find` → typed `not_found` (vendor upserts,
  no 404).
- **update-settings** — description hardened to document the vendor
  `array_replace_recursive` merge + that returned `settings` is a
  `getSettings()` read-back; flagged cross-plugin candidate, **no behavior
  change, no shared treatment** (J-auth required).

## V3 read-back evidence (live, helenawillow, 2026-05-17)

```yaml
fluent-player/create-media-tag:
  input: { tag_name: "[SPRINT-V2-TEST-PLAYER] P8 Tag" }
  vendor: TagController::createTag -> {message} (term id discarded)
  readback: getTags/getTagOptions resolve by exact name
  result: { success: true, message: "Tag created successfully",
            tag: { name: "[SPRINT-V2-TEST-PLAYER] P8 Tag",
                   slug: "sprint-v2-test-player-p8-tag" } }
  list-media-tags: { tags:[{name:"[SPRINT-V2-TEST-PLAYER] P8 Tag",count:0}],
                     tagOptions:[{name:"...",slug:"sprint-v2-test-player-p8-tag"}] }   # no empty name, no "Array"
  cleanup: delete-media-tag -> success; round-trip 0 residue
  status: PASS

fluent-player/create-playlist:
  vendor: PlaylistController::store returns {playlist:<model>} un-reloaded
  fix: resolve ID|id|post_id else read-back newest Playlist by title
  status: code-verified + lint; live round-trip PENDING — helenawillow has
          no wp_playlists table provisioned (0 FluentPlayer playlists);
          documented (not flipped) — same site-state class as P-E below
```

## Live per-family verification (helenawillow, opcache-cycled, 2026-05-17)

| Probe | Pre-P8 (Phase-7) | Post-P8 |
|---|---|---|
| analytics-performance-over-time `{scope:global}` | `success:true{message:"Invalid scope…"}` | OK `{metric,start_date,end_date,dates[13],series[]}` |
| update-playlist `{id:999999}` | `success:true,ID:999999` | `WP_ERROR[not_found]` |
| update-media `{id:999999}` | `output[media] not object` | `WP_ERROR[not_found]` |
| update-timed-content `{media_id:999999}` | `success:true` | `WP_ERROR[not_found]` |
| remove-subtitle `{…999999}` | `success:true,removed:true` | `WP_ERROR[not_found]` |
| import-youtube-captions `{…999999}` | `success:true` | `WP_ERROR[not_found]` |
| generate-youtube-storyboard `{…999999}` | `success:true,status:queued` | `WP_ERROR[not_found]` |
| bunny-stream-create-video / list-libraries | `success:true{…not enabled}` | `WP_ERROR[vendor_precondition_failed]` |
| mux-create-asset / mux-list-assets | `success:true` / `data:["…not enabled"]` | `WP_ERROR[vendor_precondition_failed]` |
| get-youtube-channel-info | `success:true{success:false}` | `WP_ERROR[vendor_precondition_failed]` |
| bunny-storage-get-video (counter-example) | clean error | clean error (unchanged) |
| list-layer-forms `{type:fluentform}` | `forms:["Form plugin is not active"]` | `WP_ERROR[vendor_precondition_failed]` |
| create-media-tag → list-media-tags → delete | name="", `tagOptions:["Array"]` | `tag:{name,slug}`; objects; round-trip clean |
| list-media / list-playlists | "Connection closed" (bridge) | graceful `WP_ERROR[execution_failed]` (no crash) |

## P-E result (per dispatch decision tree)
`list-media` / `list-playlists` via `ability->execute()` on the healthy
bridge do **not** "Connection closed" — they return a graceful typed
`WP_Error` (`execution_failed`; helenawillow has no `wp_medias`/`wp_playlists`
vendor tables provisioned = site state). The Phase-7 "Connection closed" was
an **MCP-adapter / bridge transport artifact** (the tester noted it
self-heals on the next call), **not a registrar-side code defect**. No
registrar fix; no abilities-mcp-adapter ticket warranted (transport, already
self-healing). Documented.

## V11
- **(a) input** — analytics: added `metric` enum + clarified `scope`/dates
  mapping (no rename; P-C/P-D editorial unchanged, shipped P5/#91).
- **(b) behaviour** — vendor-signalled failures now surface as typed
  `WP_Error` instead of `success:true`; existence guards added; **no change**
  to genuine-success paths or to the bunny-storage counter-example.
- **(c) write** — create-media-tag round-trip verified + cleaned (0 residue);
  create-playlist read-back code-verified (live round-trip site-state-blocked,
  documented).
- **(d) error** — typed codes: `not_found`, `vendor_precondition_failed`,
  `forbidden`, `vendor_error`, `integration_not_configured`.
- **(e) output boundary** — analytics-performance-over-time `output_schema`
  redeclared to the real vendor shape; list-media-tags emits well-typed
  object collections.

## STOP-condition / scope discipline
- Excluded & untouched: `reset-settings`, `save-integration-settings`,
  `save-email-provider-settings`, `activate/deactivate-license`.
- `update-settings` STOP(a) already Outcome-4 — surfaced (description), not
  fixed; cross-plugin shared treatment withheld pending J authorization.
- P-C/P-D editorial shipped P5/#91 — not redone.
- Shared-root is **J-authorized 2026-05-17 (ledger Addendum 32)** per the
  COLD START shared-root rule — one treatment, per-family verification table
  above, justification = framework contract. Not reviewer-gated.

## Deploy / restore receipts — 0 residue
- helenawillow plugin path
  `…/wp-content/plugins/abilities-for-fluent-plugins/includes/player/`.
- Deployed for verification (5 files), opcache-cycled, per-family probe run,
  media-tag fixture round-tripped and deleted.
- **Restored to base `1565911`** (md5 == `git show 1565911:includes/player/<f>`):
  `abilities.php` `5f26d988…`, `abilities-analytics.php` `4cc32626…`,
  `abilities-playlists.php` `99cdacfc…`, `abilities-media.php` `418d06bb…`,
  `abilities-presets.php` `b0c09d06…`. Opcache cycled. All probe temp files
  + the vendor-source pull (`/tmp/fpv`) removed (local + remote). 0 residue.
- Unit suite **997 green** (only pre-existing PHPUnit deprecations).
