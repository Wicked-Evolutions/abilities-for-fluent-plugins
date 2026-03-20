# Changelog

All notable changes to Abilities for Fluent Plugins will be documented in this file.

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
