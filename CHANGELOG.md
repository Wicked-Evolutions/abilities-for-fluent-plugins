# Changelog

All notable changes to Abilities for Fluent Plugins will be documented in this file.

## [Unreleased]

### Fixed
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
