# Cold-AI sweep coordination dashboard

> **Phase C Step 7** — Fresh-context exhaustive cold-AI operator sweep across 5 parallel chats. Release gate. Tracking which chats are running, done, blocked.

## Sweep build identity

| | |
|---|---|
| Plugin version | `1.4.0` |
| Integration HEAD | `72668c0` |
| Zip filename | `abilities-for-fluent-plugins-v1.4.0-pre-release.e043554.zip` |
| Zip sha256 | `fe6150021a11cb9ad3f9f7c7c7010387b06752b1604784d9e5101daedf0e6149` |
| Deployed on | `wicked-community` (network-active) + `helenawillow` (single-site) |

## Per-chat assignments

| Chat | AI | Coverage | Abilities | Probe site | Marker prefix | Fragment file |
|---|---|---|---|---|---|---|
| 1 | Claude | FluentCRM | 225 | wicked-community | `[SPRINT-V2-TEST-CRM]` | `chat-1-claude-fluentcrm.md` |
| 2 | Claude | Fluent Boards + Messaging | 169 | helenawillow | `[SPRINT-V2-TEST-BOARDS]` / `[SPRINT-V2-TEST-MSG]` | `chat-2-claude-boards-messaging.md` |
| 3 | Claude | Fluent Forms + Bookings | 166 | helenawillow | `[SPRINT-V2-TEST-FORMS]` / `[SPRINT-V2-TEST-BOOKING]` | `chat-3-claude-forms-bookings.md` |
| 4 | GPT 5.5 | FluentCart + FluentCommunity | 161 | wicked-community | `[SPRINT-V2-TEST-CART]` / `[SPRINT-V2-TEST-COMM]` | `chat-4-gpt5.5-fluentcart-fluentcommunity.md` |
| 5 | GPT 5.5 | FluentPlayer | 103 | helenawillow | `[SPRINT-V2-TEST-PLAYER]` | `chat-5-gpt5.5-fluentplayer.md` |
| **Total** | | | **824** | | | |

## Probe-site distribution

- **wicked-community** (Chats 1 + 4): 225 + 161 = **386 abilities** (CRM, Cart, Community)
- **helenawillow** (Chats 2 + 3 + 5): 169 + 166 + 103 = **438 abilities** (Boards, Messaging, Forms, Bookings, Player)

Central test contact: `sprint-test+v2@wickedevolutions.com` (single, cross-plugin reference)

## Progress tracking

| Chat | Status | Discovery confirmed | Abilities executed | Audit clean | Classifications |
|---|---|---|---|---|---|
| 1 (Claude · CRM) | ⏸ not started | — | 0 / 225 | — | — |
| 2 (Claude · Boards+Msg) | ⏸ not started | — | 0 / 169 | — | — |
| 3 (Claude · Forms+Booking) | ⏸ not started | — | 0 / 166 | — | — |
| 4 (GPT 5.5 · Cart+Comm) | ⏸ not started | — | 0 / 161 | — | — |
| 5 (GPT 5.5 · Player) | ⏸ not started | — | 0 / 103 | — | — |

(Orchestrator updates rows as J reports chat progress.)

## Coordination protocol if issues found

1. **Cold AI surfaces issue** → reports to J
2. **J pastes to orchestrator** → orchestrator classifies per 6-bucket taxonomy
3. **If `product bug`**:
   - Orchestrator identifies which feature branch / dev chat owns the slug (per plugin → dev chat mapping)
   - Reactivates parked dev chat via dispatch brief courier
   - Dev fixes on feature branch → merges to integration
   - Orchestrator rebuilds pre-release zip → redeploys to affected probe site
   - Orchestrator notifies affected cold-AI chat to re-sweep the fixed slug(s)
   - Other cold-AI chats CONTINUE in parallel (no global pause)
4. **If `vendor precondition` / `permission gate` / `client limitation` / `operator-pattern issue`**:
   - Classify in ledger, continue
5. **If `adapter scope`**:
   - Classify in ledger, continue
   - Add to running list for J's per-case ratification at sweep close
   - Tracked under `abilities-mcp-adapter` #116

## Sweep close criteria

- [ ] All 824 ledger rows filled with Result + Classification
- [ ] All 5 chats report audit clean
- [ ] No outstanding `product bug` classifications (all fixed + re-swept)
- [ ] J accepts the evidence
- [ ] Orchestrator merges per-chat fragments back into master ledger
- [ ] Orchestrator deletes central test contact `sprint-test+v2@wickedevolutions.com`
- [ ] Final orchestrator audit on both probe sites: zero `[SPRINT-V2-TEST*]` residuals across all plugin primary tables

Then Phase C continues with Step 8: Reviewer post-sweep review of evidence.

## Dev-chat reactivation map (if product-bug found)

| Plugin (in cold-AI chat) | Feature branch | Dev worktree path |
|---|---|---|
| FluentCRM | `feat/fluentcrm-registrar` | `~/my-agent/wordpress-plugins-temp/abilities-for-fluent-plugins.crm/` |
| FluentCart | `feat/fluentcart-registrar` | `~/my-agent/wordpress-plugins-temp/abilities-for-fluent-plugins.cart/` |
| Fluent Forms | `feat/fluentforms-registrar` | `~/my-agent/wordpress-plugins-temp/abilities-for-fluent-plugins.forms/` |
| Fluent Bookings | `feat/fluentbookings-registrar` | `~/my-agent/wordpress-plugins-temp/abilities-for-fluent-plugins.booking/` |
| Fluent Boards | `feat/fluentboards-registrar` | `~/my-agent/wordpress-plugins-temp/abilities-for-fluent-plugins.boards/` |
| FluentCommunity + Messaging | `feat/fluentcommunity-registrar` | `~/my-agent/wordpress-plugins-temp/abilities-for-fluent-plugins.community/` |
| FluentPlayer | `feat/fluentplayer-registrar` | `~/my-agent/wordpress-plugins-temp/abilities-for-fluent-plugins.player/` |

Worktrees remain parked at their last Phase B feature-PR HEAD; reactivation is a clean rebase + targeted fix + push cycle.
