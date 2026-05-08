# Abilities for Fluent Plugins

> **A word from J, the director of this creation.**
>
> Everything you see here is built by a single human who does not read or write code and is written by AI. Everything is in constant motion and by observing that movement we create the illusion of being still. Change happens at any given moment. It is simply a law of evolution. Stillness is an act of conscious awareness, not a reality of life.

## Welcome, Wordpressnaut

Here is the spaceship, now you'll have to learn how to fly and please do remember, humans make mistakes, humans created AI so AI makes mistakes. Learning to fly is your job and to do that you'll need structure, systems, checklists, principles and understanding you stand before a magical leap of a steep and wonderful learning curve. Be patient and do backup things.

→ Knowledge layer (deeper traversal): [https://knowledge.wickedevolutions.com](https://knowledge.wickedevolutions.com)
→ [https://wickedevolutions.com](https://wickedevolutions.com)
→ [https://abilitiesforai.io](https://abilitiesforai.io)

Our development aim is the *Official WordPress Compatibility Contract* — see [PRINCIPLES.md](PRINCIPLES.md) for the full binding principles across the four-repo suite.

---

Wicked Evolutions' first-party translator for the Fluent suite. We build and maintain this because we use Fluent's plugins ourselves and wanted them AI-native — a deliberate ongoing investment, not a one-off example. Conditional loading: only registers abilities for active Fluent products.

## Supported Fluent Products

Each module registers automatically when its corresponding Fluent plugin is active.

| Module | Plugin | What's enabled |
|--------|--------|----------------|
| **CRM** | FluentCRM + FluentCRM Pro | Contacts (CRUD, bulk, tags, lists, companies), email campaigns, sequences, templates, automation CRUD, analytics (campaign stats, sequence progress, journey timeline, link clicks, event tracking, reports, tag timeline, per-step conversion), cohort analysis, smart links |
| **Community** | FluentCommunity + Pro | Spaces, feeds, comments, reactions, bookmarks, members, notifications, courses, lessons, course progress, leaderboard, profiles, followers, activities, scheduled posts, media management |
| **Boards** | Fluent Boards + Pro | Boards, tasks (CRUD), stages (CRUD), labels (CRUD + assignment), members, activities, task move/assign/archive |
| **Affiliate** | FluentAffiliate + Pro | Affiliates, campaigns, referrals, payouts, portal, reports, settings, dashboard stats |
| **Cart** | FluentCart + FluentCart Pro | Products, orders, customers, subscriptions, coupons, variations, downloads, licenses (Pro), labels, abandoned carts, order items, addresses, settings |
| **Support** | Fluent Support + Pro | Tickets (CRUD + close/reopen/bulk), conversations, replies, agents (CRUD), customers (CRUD), products (CRUD), tags (CRUD + ticket assignment), mailboxes, saved replies |
| **Booking** | FluentBooking + Pro | Calendars (CRUD), events (CRUD + status + clone), bookings (CRUD + activities + notes + meta), availability (CRUD + clone), hosts, booking stats |
| **Snippets** | Fluent Snippets | Snippet management |
| **Forms** | Fluent Forms | Form abilities |
| **Cross-module** | (any Fluent product) | Active modules discovery, user 360 view, dashboard, engagement scoring, user onboarding |
| **Messaging** | Fluent Messaging | Messaging abilities |
| **SMTP** | FluentSMTP | SMTP abilities |
| **Auth** | FluentAuth | Auth abilities |

Pro-gated abilities require the corresponding Pro plugin. Live ability counts vary by site — call `suite/get-status` on your install for the current count for your specific configuration.

## Requirements

- WordPress 6.9+ (Abilities API in core)
- PHP 7.4+
- At least one Fluent product active
- [Abilities MCP Adapter](https://github.com/Wicked-Evolutions/abilities-mcp-adapter) (for MCP integration)

## How It Works

Each module checks for its Fluent plugin's defined constant at `plugins_loaded` priority 20. Active products get abilities registered; the rest stay quiet. Install the parent plugin and the module activates next request — no configuration.

Module toggles are available via the `fluent_abilities_enabled_modules` option for fine-grained control.

## Connecting your AI client

Fluent abilities flow through the same MCP path as the rest of the Abilities Suite. Operator entry has two recommended paths:

- **Claude Desktop:** download `abilities-mcp.mcpb` from the [bridge's latest GitHub Release](https://github.com/Wicked-Evolutions/abilities-mcp/releases/latest), then upgrade to OAuth via `abilities-mcp upgrade-auth <site>` from terminal. Fluent abilities surface alongside the rest of the suite in the same Claude Desktop "Abilities MCP" entry.
- **Terminal MCP clients (Claude Code, Cursor, Codex, etc.):** `npm install -g @wickedevolutions/abilities-mcp`, then `abilities-mcp add-site <url>` — OAuth by default.

For the full bridge setup guide, see the [Abilities MCP README](https://github.com/Wicked-Evolutions/abilities-mcp#readme).

## Installation

Upload to `wp-content/plugins/abilities-for-fluent-plugins/` and activate.

The MCP Adapter automatically discovers all abilities.

## Security

Three-layer security model:

1. **Module toggles** — admin can disable entire modules via the plugin dashboard
2. **WordPress capabilities** — each ability checks appropriate caps before executing
3. **Input validation** — all inputs sanitized and validated

**Capability setup:** On activation, all custom caps (`fluent_crm_read`, `fluent_community_write`, etc.) are granted to the `administrator` role. Other roles must be granted caps manually. The full cap list is in `includes/security.php → fluent_abilities_get_caps()`.

### Fluent abilities + four-layer permissions model

When invoked through MCP, Fluent abilities flow through the suite's four-layer permissions model alongside everything else:

1. **Abilities for AI module permission** — per-blog Read/Write/Delete toggle in *WP Admin → Abilities for AI → Permissions* (the [Abilities for AI](https://github.com/Wicked-Evolutions/abilities-for-ai) plugin runs this check). Fluent modules sit alongside content/blocks/users/etc. as toggleable categories.
2. **WordPress capability** — the per-ability `current_user_can()` check (the Fluent custom caps above are what fire here).
3. **OAuth scope** — the bearer token must include the relevant `abilities:fluent:*` or per-module scope (`abilities:crm:*`, `abilities:community:*`, etc.). Bridge operators expand scopes with `abilities-mcp reauth <site> --add-scope="abilities:crm:write"`.
4. **Unclear** — generic 500, timeout, or malformed response. Check server logs.

The four gates apply together by design (see [PRINCIPLES.md](PRINCIPLES.md), Principle 5 — *Permissions Stay Layered*). The runtime `[ability_disabled]` error names which gate fired so you can act at the right layer.

## Version History

See [CHANGELOG.md](CHANGELOG.md) for the complete version history. Recent releases:

- **1.1.1** (2026-05-05) — Public Alpha Hardening: activation auto-detect for FluentAffiliate (#22), dashboard renders affiliate abilities (#21), disabled modules visible in settings UI (#20), CLI/stdio bridge denies destructive abilities without resolved WP user (#19), PHPUnit bootstrap stub for unit-mode (#23)
- **1.1.0** (2026-03-20) — Module additions and refinements (see CHANGELOG)
- **1.0.1** (2026-03-17) — Early activation/dashboard fixes (see CHANGELOG)
- **1.0.0** (2026-03-17) — Initial release

## Author

[Wicked Evolutions](https://wickedevolutions.com)

## License

GPL-2.0-or-later
