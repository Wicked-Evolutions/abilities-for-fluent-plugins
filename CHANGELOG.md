# Changelog

All notable changes to Abilities for Fluent Plugins will be documented in this file.

## [1.1.3] - 2026-05-10

Bug fix — Registrar `input_schema` default restored to JSON Schema draft 2020-12 conformance.

### Fixed

- `src/Core/Registrar.php:137` — `input_schema` default value changed from PHP `array()` (which JSON-encodes as `[]`) to `array( 'type' => 'object' )` (JSON `{"type":"object"}`). The previous default caused MCP clients to hit `400 tools.N.custom.input_schema: JSON schema is invalid. It must match JSON Schema draft 2020-12` on `tools/list` for any zero-arg ability registered without an explicit `input_schema` — affecting 8 zero-arg abilities surfaced when Fluent OAuth scopes were granted (rising to 17 with Fluent Affiliate active). Mirrors the equivalent fix that shipped in `abilities-for-ai` v1.9.1.
- Unit test added at `tests/Unit/RegistrarTest.php` asserting `json_encode($args['input_schema'])` returns `'{"type":"object"}'` (not `'[]'`) when no `input_schema` is provided in `$config`.

Closes [#41](https://github.com/Wicked-Evolutions/abilities-for-fluent-plugins/issues/41).

## [1.1.2] - 2026-05-08

Documentation update — README rewrite for first-party positioning + Wordpressnaut welcome + four-layer permissions framing + version-history catch-up. Code unchanged from v1.1.1.

### Documentation

- README rewritten end-to-end:
  - **Removed leading ability-count framing** (no 450-ability table at the top, no `(N abilities)` in module section headings). The product is the open registry, not the count — operators get live counts via `suite/get-status` for their specific install.
  - **First-party deliberate-investment positioning** surfaced in the opening paragraph — *"Wicked Evolutions' first-party translator for the Fluent suite. We build and maintain this because we use Fluent's plugins ourselves and wanted them AI-native — a deliberate ongoing investment, not a one-off example."*
  - **Supported Fluent Products table** now describes what each module enables operators to do (e.g., for CRM: *"Contacts CRUD bulk tags lists companies, email campaigns, sequences, ..."*) — no count framing
  - **Bridge / adapter setup orientation** added — operators get pointed at the recommended bridge install paths (`.mcpb` for Claude Desktop / `npm install -g @wickedevolutions/abilities-mcp` for terminal MCP clients), with a link to the bridge README for full setup
  - **Four-layer permissions model section** added — names the four layers (Abilities for AI module · WordPress capability · OAuth scope · unclear), points operators at the runtime `[ability_disabled]` error as the layer indicator, and shows operators how to expand Fluent-specific OAuth scopes (`abilities-mcp reauth <site> --add-scope="abilities:crm:write"`)
  - **Version History catch-up** — README now reflects 1.0.0 → 1.0.1 → 1.1.0 → 1.1.1 (was stuck on 1.0.0 only)
  - Welcome block at top with verbatim *"Welcome, Wordpressnaut"* spaceship paragraph + 3 URL pointers
  - Disclaimer block from J at the very top
  - Pointer to [PRINCIPLES.md](PRINCIPLES.md) as the *Official WordPress Compatibility Contract* binding all four suite repos

Closes [#38](https://github.com/Wicked-Evolutions/abilities-for-fluent-plugins/issues/38).

## [1.1.1] - 2026-05-05

### Fixed
- **#22:** Fresh activations with FluentAffiliate active now auto-enable the `affiliate` module. The activation hook's auto-detect map at `abilities-for-fluent-plugins.php:231-243` listed 11 modules through `cart` and omitted `FLUENT_AFFILIATE_VERSION`, while the runtime loader at lines 109-121 already included it. CLI-first installs that activated FluentAffiliate alongside this plugin saw zero affiliate abilities register on first activation despite the plugin advertising Affiliate support.
- **#21:** Affiliate abilities now appear in the dashboard explorer when the FluentAffiliate module is enabled. The dashboard's `$module_to_category` map omitted `affiliate => fluent-affiliate`, so `get_fluent_abilities()` filtered out the 21 affiliate-categorized abilities (across `abilities.php`, `payout-abilities.php`, `portal-abilities.php`, `report-abilities.php`, `settings-abilities.php`) before they reached the explorer. Adding the map entry restores them and lets the explorer's category-filter dropdown narrow to `affiliate`.
- **#20:** Disabled installed modules no longer vanish from the settings UI. Module-toggle rows for installed-but-disabled modules now render in the Abilities explorer (with an enabled checkbox so the operator can re-enable from the UI), independently of whether the module currently registers any abilities. Previously, disabling a module via the toggle would remove it from the explorer entirely on next page load — re-enabling required hand-editing the `fluent_abilities_enabled_modules` option. Underlying bug had two layers: the explorer iterated registered abilities to emit module headers (so disabled modules with zero abilities had no header emitted), and the existing fallback loop referenced an undefined `$all_seen_modules` variable + only matched `! detected` (skipping installed-disabled).
- **#19 (P1, alpha blocker):** WP-CLI / stdio bridge invocations without a resolved WordPress user no longer authorize destructive abilities by module-toggle alone. Anonymous CLI requests now deny every level by default and return a typed `WP_Error( 'fluent_abilities_no_cli_user_context', ..., array( 'status' => 401 ) )` at the ability boundary. A one-release backwards-compatibility shim, `FLUENT_ABILITIES_CLI_ALLOW_ANONYMOUS_READ=1`, allows anonymous read-level access for enabled modules during operator migration. Removal target: v1.2.0. Operators relying on anonymous write/delete/admin must wire OAuth user resolution (or set the env-var explicitly per invocation) — read-only.
- **#23:** PHPUnit unit-test suite no longer fatals on `Call to undefined function fluent_abilities_pro_gate()`. Bootstrap loads a passthrough stub in unit mode (decoupled from the real `tier-gate.php` / license-manager). Previously-fataling Registrar tests now run and pass.

### Changed
- **CI hygiene:** unit-tests matrix now runs PHP 8.1, 8.2, 8.3, 8.4, 8.5 (composer.lock requires PHP 8.1+ — production minimum unchanged at PHP 7.4 per `composer.json`). Vestigial integration-tests matrix removed: it ran against `tests/Integration/` which has never existed in the repo, so the matrix was testing test-suite setup, not any production code. `fail-fast: false` on unit-tests so a single matrix entry's failure no longer cancels its siblings.

## [1.1.0] - 2026-03-20

### Fixed
- **#1 (P0):** Cart table prefix `fc_` corrected to `fct_` — `list-abandoned-carts`, `list-tax-rates`, `list-shipping-methods` now return actual data
- **#4 (P0):** `list-product-downloads` `product_variation_id` no longer returns "Array" — handles array-to-string coercion
- **#9:** `delete-tag` now returns success instead of false "Tag not found" — detaches pivot entries and fires FluentCRM cleanup hooks
- **#6:** `delete-tag` no longer creates ghost duplicates — proper cleanup prevents re-creation via lifecycle hooks
- **#7:** `delete-label` param renamed `label_id` to `id` for consistency with `create-label` (also fixed `update-label`)

### Added
- **#5:** `fluent-cart/delete-product-variation` ability
- **#8:** `fluent-boards/delete-stage` ability with task migration to fallback stage
- **#10:** `fluent-cart/delete-product-download` ability

## [1.0.1] - 2026-03-17

### Fixed
- Network license inherits to all subsites
- Standalone dashboard menu when Abilities for AI is not installed

## [1.0.0] - 2026-03-17

### Added
- Initial release — 450 abilities across 13 modules
- Migrated from `Influencentricity/abilities-suite-for-fluent-plugins` with slug rename, branding update, option key migration, and version reset
- Modules: CRM (94), Community (66), Boards (62), Affiliate (55), Cart (50), Support (46), Booking (42), Snippets (7), Forms (6), Cross-module (5), Messaging (5), SMTP (5), Auth (4)

---

## License

GPL-2.0-or-later
