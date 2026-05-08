# Contributing to Abilities for Fluent Plugins

We welcome contributions — bug reports, feature ideas, code, documentation, and questions.

## How We Work

This project is built by a human founder and a team of AI agents. The founder does not read or write code. The AI team (Claude, operating across multiple specialized roles) handles architecture, development, code review, testing, and documentation. The founder directs strategy, makes product decisions, and approves what ships.

Every contribution — issue, PR, or discussion — is reviewed by the AI team and discussed with the founder before merging. This means:

- **Response times vary.** We review in batches, not in real-time.
- **PRs require approval.** The `main` branch is protected. All external contributions come through pull requests.
- **We may ask clarifying questions.** Context helps us make better decisions.
- **We may adapt your contribution.** If the direction is right but the implementation needs adjustment for our architecture, we'll work with you on it.

## Reporting Bugs

Open an issue with:

1. What you expected to happen
2. What actually happened
3. Steps to reproduce
4. Your environment (WordPress version, PHP version, plugin version, multisite or single-site, **which Fluent products are active** — CRM, Community, Forms, Boards, Booking, Cart, Affiliate, Support, SMTP, Auth, Snippets, Messaging, including which Pro versions if applicable)

If the bug involves a specific ability execution, include the ability name, input parameters, and the error response (redact any sensitive data first).

## Suggesting Features

Open an issue describing:

1. What you want to do (the use case, not just the feature)
2. Why existing abilities don't cover it
3. Which Fluent product / module the feature belongs to
4. Any ideas on implementation (optional)

We track feature requests as GitHub issues and prioritize based on how they fit the product direction. Missing-ability requests for actively-used Fluent product surfaces are particularly welcome — this plugin is a deliberate ongoing investment in the Fluent ecosystem.

## Pull Requests

1. Fork the repo and create a branch from `main`
2. Make your changes
3. Run `vendor/bin/phpunit --testsuite Unit` if you modified PHP code
4. Write clear commit messages describing what and why
5. Open a PR against `main`
6. Describe what your PR does and which issue it addresses (if any)

### What makes a good PR

- **Focused.** One concern per PR.
- **Tested.** Describe how you verified it works. Run the PHPUnit Unit suite if applicable.
- **PHP lint clean.** `php -l` on all modified files.
- **Documented.** If your change affects user-facing behavior, update the relevant docs.

### What we look for in review

- Does it follow the WordPress Abilities API contract (`wp_register_ability` with input/output schemas, capability checks at execution)?
- Does it use the [`Registrar`](src/Core/Registrar.php) wrapper for ability registration (which auto-injects `permission_callback`, annotations, and Pro-gating)?
- Does it handle `WP_Error` correctly?
- Does it follow the existing namespace and class patterns?
- Does it maintain backward compatibility with existing ability consumers (the adapter, the bridge, third-party MCP clients)?
- Is the permission model consistent (per-module Read/Write/Delete toggles, Pro tier where applicable)?
- Does it use native Fluent plugin APIs (e.g., FluentCRM's contact API) before reaching for raw database queries? See [PRINCIPLES.md](PRINCIPLES.md), Principle 2 — *Native APIs Over Raw Storage*.

## Code Style

- **PHP 7.4+** (production minimum, matching `composer.json` `require.php` and the plugin header `Requires PHP`)
- **Test toolchain PHP 8.1+** (PhpUnit 10.5 transitive dependencies pin this)
- PSR-4 autoloading under `WickedEvolutions\AbilitiesForFluent`
- WordPress coding standards for hook names and option keys
- Consistent with existing code patterns

## Adding a new ability

If you want to register a new ability — either for an existing Fluent module or for a new module — use the `Registrar` wrapper:

```php
use WickedEvolutions\AbilitiesForFluent\Core\Registrar;

$reg = new Registrar( 'crm' ); // module slug

$reg->read( 'fluent-crm/list-something', array(
    'label'         => 'List Something',
    'description'   => '...',
    'input_schema'  => array( /* JSON Schema */ ),
    'output_schema' => array( /* JSON Schema */ ),
    'callback'      => function( $params ) { /* ... */ },
) );

$reg->write( 'fluent-crm/create-something', array( /* ... */ ) );
$reg->delete( 'fluent-crm/delete-something', array( /* ... */ ) );
```

The Registrar handles `permission_callback`, MCP annotations (`readonly`, `destructive`, `idempotent`, `permission`), Pro-gating, and the `wp_register_ability()` call. See [`src/Core/Registrar.php`](src/Core/Registrar.php) for the full contract.

## Security

If you discover a security vulnerability, **do not open a public issue.** See [SECURITY.md](SECURITY.md) for the private reporting process.

## License

By contributing, you agree that your contributions will be licensed under the GPL-2.0-or-later license.
