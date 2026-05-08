# Security Policy

## Reporting a Vulnerability

If you discover a security vulnerability in Abilities for Fluent Plugins, **do not open a public issue.**

Instead, please use [GitHub's private vulnerability reporting](https://github.com/Wicked-Evolutions/abilities-for-fluent-plugins/security/advisories/new) to report it directly.

Include:
- Description of the vulnerability
- Steps to reproduce
- Potential impact
- Suggested fix (if you have one)

We review private vulnerability reports as bandwidth allows. We do not commit to specific response or fix timelines — this is a small team, and timing depends on severity, complexity, and what else is in flight. We will respond when we have something useful to say. Critical issues are prioritized.

## Scope

This policy covers:

### Ability registration & execution

- Ability handlers across all 13 modules (CRM, Community, Forms, Boards, Booking, Cart, Affiliate, Support, SMTP, Auth, Snippets, Messaging, Cross-module)
- The `Registrar` wrapper (`src/Core/Registrar.php`) that auto-injects `permission_callback` on every registered ability
- Per-ability permission enforcement: WordPress capabilities (`fluent_{module}_{level}`) + per-module enable/disable toggles + Pro license tier where applicable
- Anonymous CLI / stdio bridge denial path — typed `WP_Error` 401 with migration guidance for write/delete/admin operations without a resolved WordPress user
- Input validation, schema enforcement, output sanitization across all ability callbacks

### Capability & module gates

- Custom capability registration on activation (`fluent_crm_read`, `fluent_community_write`, etc.) — see `includes/security.php`
- Module enable/disable state stored in `fluent_abilities_enabled_modules` option (per-blog on multisite via `update_site_option`)
- Pro tier gating via `fluent_abilities_pro_gate()` wrapping every Pro-eligible callback

### Conditional loading

- Each module checks for its parent Fluent plugin's defined constant at `plugins_loaded` priority 20 — abilities only register when the corresponding Fluent plugin is active
- Activation auto-detect for fresh installs (the `fluent_abilities_enabled_modules` seeding logic)

For vulnerabilities in the MCP transport, response redaction filter, OAuth resource server, or rate limiter, use the relevant repository:

- [abilities-mcp-adapter](https://github.com/Wicked-Evolutions/abilities-mcp-adapter/security/advisories/new) — WordPress-side MCP protocol layer + OAuth resource server
- [abilities-mcp](https://github.com/Wicked-Evolutions/abilities-mcp/security/advisories/new) — Node bridge
- [abilities-for-ai](https://github.com/Wicked-Evolutions/abilities-for-ai/security/advisories/new) — WordPress core abilities + Knowledge Layer

## Out of scope

- WordPress core security — report to WordPress Security Team
- Fluent plugins themselves (FluentCRM, FluentCommunity, etc.) — report to [WPManageNinja](https://wpmanageninja.com)
- Theme-level vulnerabilities

## Supported Versions

We support the latest released version. Older versions do not receive security patches — please update.
