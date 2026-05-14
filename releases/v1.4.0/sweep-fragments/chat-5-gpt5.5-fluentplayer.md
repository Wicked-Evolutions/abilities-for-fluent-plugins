# Chat 5 — GPT 5.5 cold-AI operator sweep — FluentPlayer

## Mission

You are an AI operator running the **fresh-context exhaustive cold-AI sweep** for v1.4.0 release of `abilities-for-fluent-plugins`. This is the release gate — **release is blocked until J accepts your evidence**.

You own **103 new abilities** across FluentPlayer. Your job:

1. Discover the live ability surface on the probe site
2. Execute each owned ability via the MCP adapter, capturing input + result
3. Classify each result per the 6-bucket failure taxonomy
4. Fill the ledger rows below
5. Cleanup all fixtures per testclient discipline
6. Report completion + audit to orchestrator

## Build identity (what's deployed)

| | |
|---|---|
| **Plugin version** | `1.4.0` |
| **Build SHA** | integration HEAD `72668c0` |
| **Zip filename** | `abilities-for-fluent-plugins-v1.4.0-pre-release.e043554.zip` |
| **Zip sha256** | `fe6150021a11cb9ad3f9f7c7c7010387b06752b1604784d9e5101daedf0e6149` |

## Probe site

| | |
|---|---|
| **Site** | `helenawillow` |
| **MCP tool** | `mcp__wordpress__mcp-adapter-discover-abilities` / `mcp__wordpress__mcp-adapter-execute-ability` with `site=helenawillow` |
| **First action** | Run discovery for your owned plugin categories. Confirm the ability count matches the ledger. If it doesn't, STOP and report to orchestrator. |

## Testclient discipline (BINDING)

Every fixture you create MUST follow this discipline:

1. **Marker prefix on titles/labels:** `[SPRINT-V2-TEST-PLAYER]` (your chat-specific suffix). Examples:
   - Media/playlist/preset title: `[SPRINT-V2-TEST-PLAYER] cluster-X smoke`
2. **Email fixtures:** pattern `sprint-test+v2-{slug}@wickedevolutions.com` where {slug} is a hint for the ability cluster.
3. **Central test contact:** `sprint-test+v2@wickedevolutions.com` — shared across all 5 chats for cross-plugin contact references. Created at sweep start by orchestrator. Do NOT delete it; orchestrator deletes at end.
4. **In-run cleanup:** every create test is paired with an explicit delete in the same captured ability-execution sequence. No fixture intentionally left behind.
5. **Fixture-only operations:** write/update/delete ONLY target records your chat created (matched by marker prefix). NEVER touch real records.
6. **Per-batch audit:** after completing your owned plugin(s), run `wp db query` or equivalent to confirm zero `[SPRINT-V2-TEST-PLAYER]`-prefixed records remain. Report audit-clean evidence.

## 6-bucket failure classification

For any failing call, classify into ONE bucket:

| Bucket | Meaning | Continue? |
|---|---|---|
| **product bug** | The v1.4.0 ability code is wrong (registration shape, callback logic, schema mismatch) | STOP and report to orchestrator immediately — release blocker until fixed |
| **vendor precondition** | Vendor module / table / credentials absent on this probe site | Continue; classify and move on |
| **permission gate** | `permission_callback` correctly denied; expected behavior for the auth context | Continue; classify and move on |
| **adapter scope** | MCP adapter scope/grant prevents execution | Continue; classify and report at end. Tracked under `abilities-mcp-adapter` #116; J decides whether this blocks release |
| **client limitation** | You (as cold AI) cannot handle / form / display the call or result. Schema/description improvement opportunity | Continue; classify and capture the issue type |
| **operator-pattern issue** | You made a semantically-wrong call (wrong IDs, wrong order). Coaching opportunity, not a release blocker | Continue; classify and capture |

## Ledger output format

For each row in the ledger below, fill the 8 columns:

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |

- **Site:** `helenawillow`
- **Client:** `GPT 5.5` (this session's identity)
- **Input (key):** brief summary of the input you used (e.g., `{contact_id: 48}` or `created via 5.1.1`)
- **Result:** `✅ pass` if data returned (or expected typed WP_Error like permission denial); `❌ fail` with brief reason otherwise
- **Classification:** one of the 6 buckets above (only for failures or expected denials)
- **Cleanup:** `n/a` for reads, `delete OK` after in-run delete, `audit clean` after batch audit
- **Notes:** anything noteworthy (schema gaps, slow responses, unclear descriptions, etc.)

## When you're done

1. All ledger rows filled
2. Per-batch audit clean
3. Report to J/orchestrator: "Chat 5 sweep complete. N abilities executed. Classifications: product-bug=X, vendor-precondition=Y, permission-gate=Z, adapter-scope=W, client-limitation=V, operator-pattern=U. Audit: clean."

If you find any `product bug` classifications, STOP and report immediately — orchestrator reactivates the dev chat to fix before you continue.

---

# Ledger fragment — FluentPlayer

## FluentPlayer — 103 new abilities

**Probe site:** helenawillow primary, wicked-community parity

### abilities-analytics.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-player/analytics-devices` | | | | | | | |
| `fluent-player/analytics-location-breakdown` | | | | | | | |
| `fluent-player/analytics-new-returning-viewers` | | | | | | | |
| `fluent-player/analytics-performance-over-time` | | | | | | | |
| `fluent-player/analytics-retention` | | | | | | | |
| `fluent-player/analytics-stats` | | | | | | | |
| `fluent-player/analytics-top-users` | | | | | | | |
| `fluent-player/analytics-top-videos` | | | | | | | |
| `fluent-player/analytics-user-info` | | | | | | | |
| `fluent-player/analytics-user-retention` | | | | | | | |
| `fluent-player/analytics-user-stats` | | | | | | | |
| `fluent-player/analytics-user-top-videos` | | | | | | | |
| `fluent-player/analytics-video-devices` | | | | | | | |
| `fluent-player/analytics-video-location-breakdown` | | | | | | | |
| `fluent-player/analytics-video-retention` | | | | | | | |
| `fluent-player/analytics-video-stats` | | | | | | | |
| `fluent-player/analytics-video-top-users` | | | | | | | |

### abilities-bunny.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-player/bunny-storage-create-directory` | | | | | | | |
| `fluent-player/bunny-storage-delete-video` | | | | | | | |
| `fluent-player/bunny-storage-get-video` | | | | | | | |
| `fluent-player/bunny-storage-list-videos` | | | | | | | |
| `fluent-player/bunny-stream-create-collection` | | | | | | | |
| `fluent-player/bunny-stream-create-video` | | | | | | | |
| `fluent-player/bunny-stream-delete-collection` | | | | | | | |
| `fluent-player/bunny-stream-delete-video` | | | | | | | |
| `fluent-player/bunny-stream-list-collections` | | | | | | | |
| `fluent-player/bunny-stream-list-libraries` | | | | | | | |
| `fluent-player/bunny-stream-list-videos` | | | | | | | |
| `fluent-player/bunny-stream-update-collection` | | | | | | | |
| `fluent-player/bunny-stream-update-video` | | | | | | | |

### abilities-email.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-player/delete-email-collection` | | | | | | | |
| `fluent-player/export-email-collections` | | | | | | | |
| `fluent-player/get-email-collection` | | | | | | | |
| `fluent-player/get-integration-fields` | | | | | | | |
| `fluent-player/get-youtube-channel-info` | | | | | | | |
| `fluent-player/list-email-collections` | | | | | | | |
| `fluent-player/list-email-providers` | | | | | | | |
| `fluent-player/list-integrations` | | | | | | | |
| `fluent-player/list-layer-forms` | | | | | | | |
| `fluent-player/list-provider-resources` | | | | | | | |
| `fluent-player/list-smartcodes` | | | | | | | |
| `fluent-player/save-email-provider-settings` | | | | | | | |
| `fluent-player/save-integration-settings` | | | | | | | |
| `fluent-player/test-integration-connection` | | | | | | | |
| `fluent-player/validate-provider-field` | | | | | | | |

### abilities-license.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-player/activate-license` | | | | | | | |
| `fluent-player/deactivate-license` | | | | | | | |
| `fluent-player/get-license-details` | | | | | | | |

### abilities-media.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-player/create-media` | | | | | | | |
| `fluent-player/create-media-tag` | | | | | | | |
| `fluent-player/delete-media` | | | | | | | |
| `fluent-player/delete-media-tag` | | | | | | | |
| `fluent-player/get-media` | | | | | | | |
| `fluent-player/get-media-metadata` | | | | | | | |
| `fluent-player/list-media` | | | | | | | |
| `fluent-player/list-media-tags` | | | | | | | |
| `fluent-player/rename-media-tag` | | | | | | | |
| `fluent-player/search-media` | | | | | | | |
| `fluent-player/update-media` | | | | | | | |

### abilities-mux.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-player/mux-create-asset` | | | | | | | |
| `fluent-player/mux-create-live-stream` | | | | | | | |
| `fluent-player/mux-create-playback-restriction` | | | | | | | |
| `fluent-player/mux-create-signing-key` | | | | | | | |
| `fluent-player/mux-create-track` | | | | | | | |
| `fluent-player/mux-create-upload` | | | | | | | |
| `fluent-player/mux-delete-asset` | | | | | | | |
| `fluent-player/mux-delete-live-stream` | | | | | | | |
| `fluent-player/mux-delete-playback-restriction` | | | | | | | |
| `fluent-player/mux-delete-signing-key` | | | | | | | |
| `fluent-player/mux-delete-track` | | | | | | | |
| `fluent-player/mux-generate-track-subtitles` | | | | | | | |
| `fluent-player/mux-get-asset` | | | | | | | |
| `fluent-player/mux-get-asset-captions` | | | | | | | |
| `fluent-player/mux-get-delivery-usage` | | | | | | | |
| `fluent-player/mux-get-live-stream` | | | | | | | |
| `fluent-player/mux-get-upload-status` | | | | | | | |
| `fluent-player/mux-list-assets` | | | | | | | |
| `fluent-player/mux-list-live-streams` | | | | | | | |
| `fluent-player/mux-list-playback-restrictions` | | | | | | | |
| `fluent-player/mux-list-signing-keys` | | | | | | | |
| `fluent-player/mux-reset-stream-key` | | | | | | | |
| `fluent-player/mux-update-asset` | | | | | | | |
| `fluent-player/mux-update-asset-mp4-support` | | | | | | | |

### abilities-playlists.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-player/create-playlist` | | | | | | | |
| `fluent-player/delete-playlist` | | | | | | | |
| `fluent-player/generate-youtube-storyboard` | | | | | | | |
| `fluent-player/get-playlist` | | | | | | | |
| `fluent-player/get-youtube-captions` | | | | | | | |
| `fluent-player/import-youtube-captions` | | | | | | | |
| `fluent-player/list-playlists` | | | | | | | |
| `fluent-player/remove-subtitle` | | | | | | | |
| `fluent-player/update-playlist` | | | | | | | |
| `fluent-player/update-timed-content` | | | | | | | |
| `fluent-player/upload-subtitle` | | | | | | | |

### abilities-presets.php

| Ability slug | Site | Client | Input (key) | Result | Classification | Cleanup | Notes |
|---|---|---|---|---|---|---|---|
| `fluent-player/create-preset` | | | | | | | |
| `fluent-player/delete-preset` | | | | | | | |
| `fluent-player/get-preset` | | | | | | | |
| `fluent-player/get-settings` | | | | | | | |
| `fluent-player/get-settings-section` | | | | | | | |
| `fluent-player/list-presets` | | | | | | | |
| `fluent-player/reset-settings` | | | | | | | |
| `fluent-player/update-preset` | | | | | | | |
| `fluent-player/update-settings` | | | | | | | |

