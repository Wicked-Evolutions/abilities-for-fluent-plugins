# Abilities for Fluent Plugins

WordPress Abilities API integration for the Fluent plugin ecosystem. Conditional loading — only registers abilities for active Fluent products.

## Supported Fluent Products

| Module | Plugin | Abilities |
|--------|--------|-----------|
| CRM | FluentCRM + FluentCRM Pro | 94 |
| Community | FluentCommunity + Pro | 66 |
| Boards | Fluent Boards + Pro | 62 |
| Affiliate | FluentAffiliate + Pro | 55 |
| Cart | FluentCart + FluentCart Pro | 50 |
| Support | Fluent Support + Pro | 46 |
| Booking | FluentBooking + Pro | 42 |
| Snippets | Fluent Snippets | 7 |
| Forms | Fluent Forms | 6 |
| Cross-module | (any Fluent product) | 5 |
| Messaging | Fluent Messaging | 5 |
| SMTP | FluentSMTP | 5 |
| Auth | FluentAuth | 4 |
| **Total** | | **450** |

Runtime count varies by site — abilities only register for active plugins. Pro-gated abilities require the corresponding Pro plugin.

## Requirements

- WordPress 6.9+ (Abilities API in core)
- PHP 7.4+
- At least one Fluent product active
- [Abilities MCP Adapter](https://github.com/Wicked-Evolutions/abilities-mcp-adapter) (for MCP integration)

## How It Works

Each module checks for its Fluent plugin's `defined()` constant at `plugins_loaded` priority 20. Only active products get abilities registered. No abilities are loaded if no Fluent products are installed.

Module toggles are available via `fluent_abilities_enabled_modules` option for fine-grained control.

## Key Capabilities

### CRM (94 abilities)
Contacts (CRUD, bulk, tags, lists, companies), email campaigns (CRUD), sequences (CRUD + email management), templates, automation CRUD (create from scratch, add/remove steps, decoded settings), analytics (campaign stats, sequence progress, journey timeline, link clicks, event tracking, reports, tag timeline, per-step conversion rates), cohort analysis, smart links.

### Community (66 abilities)
Spaces, feeds, comments, reactions, bookmarks, members, notifications, courses, lessons, course progress, leaderboard, profiles, followers, activities, scheduled posts, media management.

### Boards (62 abilities)
Boards, tasks (CRUD), stages (CRUD), labels (CRUD + assignment), members, activities, task move/assign/archive.

### Affiliate (55 abilities)
Affiliates, campaigns, referrals, payouts, portal, reports, settings, dashboard stats.

### Cart (50 abilities)
Products, orders, customers, subscriptions, coupons, variations, downloads, licenses (Pro), labels, abandoned carts, order items, addresses, settings.

### Support (46 abilities)
Tickets (CRUD + close/reopen/bulk), conversations, replies, agents (CRUD), customers (CRUD), products (CRUD), tags (CRUD + ticket assignment), mailboxes, saved replies.

### Booking (42 abilities)
Calendars (CRUD), events (CRUD + status + clone), bookings (CRUD + activities + notes + meta), availability (CRUD + clone), hosts, booking stats.

### Cross-module (5 abilities)
Active modules discovery, user 360 view, dashboard, engagement scoring, user onboarding.

## Installation

Upload to `wp-content/plugins/abilities-for-fluent-plugins/` and activate.

The MCP Adapter automatically discovers all abilities.

## Security

Three-layer security model:
1. **Module toggles** — admin can disable entire modules via the plugin dashboard
2. **WordPress capabilities** — each ability checks appropriate caps before executing
3. **Input validation** — all inputs sanitized and validated

**Capability setup:** On activation, all custom caps (`fluent_crm_read`, `fluent_community_write`, etc.) are granted to the `administrator` role. Other roles must be granted caps manually. The full cap list is in `includes/security.php → fluent_abilities_get_caps()`.

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0.0 | 2026-03-17 | Initial release. 450 abilities across 13 modules. |

## Author

[Wicked Evolutions](https://wickedevolutions.com)

## License

GPL-2.0-or-later
