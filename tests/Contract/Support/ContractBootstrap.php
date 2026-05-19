<?php
/**
 * Contract-suite boot helper — Layer 1 deterministic gate (issue #110).
 *
 * Boots EVERY ability module registrar by requiring all files that hook
 * `wp_abilities_api_init`, then firing that action ONCE. Callbacks are
 * NEVER invoked — only the registered `wp_register_ability()` args
 * (schemas, slug, annotations, the callback *reference*) are reflected.
 *
 * Zero WordPress site, zero network, zero DB. The WP surface is the
 * unit stubs already loaded by tests/bootstrap.php (unit mode). Module
 * enable-gating lives in the plugin's main loader, NOT in the ability
 * files (each file unconditionally defines a fn + add_action) — so the
 * gate reflects the FULL authored surface, independent of any site's
 * enabled-modules option (that independence is the point of a gate).
 *
 * @package Fluent_Abilities\Tests\Contract
 */

final class ContractBootstrap {

	/** @var array<string,array>|null Registered ability args, slug => args. */
	private static $abilities = null;

	/** @var string[] Absolute paths of every required ability module file. */
	private static $files = array();

	/**
	 * Boot once; return the registered ability args map (slug => args).
	 * Idempotent — repeated calls return the memoised map.
	 *
	 * @return array<string,array>
	 */
	public static function boot(): array {
		if ( null !== self::$abilities ) {
			return self::$abilities;
		}

		$root = dirname( __DIR__, 3 );

		// ~30 ability files emit a one-line `error_log( 'Registered N …' )`
		// banner at load. error_log() defaults to STDERR in CLI and is
		// NOT captured by ob_*; under PHPUnit process isolation that
		// stderr corrupts the child→parent result stream. Redirect
		// error_log() to a throwaway file for the boot — deterministic,
		// no banner leakage, child IPC stays clean.
		ini_set( 'log_errors', '1' );
		ini_set( 'error_log', sys_get_temp_dir() . '/fluent-contract-gate-boot.log' );

		// tests/bootstrap.php (unit mode) already loaded the composer
		// autoloader + stubs. Guard for isolated Contract-suite runs.
		if ( ! function_exists( 'wp_register_ability' ) ) {
			if ( is_file( $root . '/vendor/autoload.php' ) ) {
				require_once $root . '/vendor/autoload.php';
			}
			require_once $root . '/tests/stubs/wordpress-stubs.php';
		}

		// Path/abspath constants the entrypoints use to require their own
		// in-$reg-scope sub-files (e.g. includes/boards/abilities.php).
		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', $root . '/' );
		}
		if ( ! defined( 'FLUENT_ABILITIES_PATH' ) ) {
			define( 'FLUENT_ABILITIES_PATH', $root . '/' );
		}
		if ( ! defined( 'FLUENT_ABILITIES_URL' ) ) {
			define( 'FLUENT_ABILITIES_URL', 'https://example.test/' );
		}

		// Shared infra the ability files depend on (compat alias,
		// helpers, schemas, security). Loaded directly — NOT via the main
		// plugin file, whose prelude (updater HTTP, tier-gate) collides
		// with the unit stubs. tier-gate is deliberately stubbed for unit
		// mode, so it is intentionally skipped here.
		if ( ! class_exists( 'Fluent_Abilities_Registrar' ) ) {
			require_once $root . '/includes/compat.php';
		}
		foreach ( array( 'ability-categories', 'helpers', 'schemas', 'security' ) as $infra ) {
			require_once $root . '/includes/' . $infra . '.php';
		}

		global $_wp_registered_abilities;
		$_wp_registered_abilities = array();

		// Require ONLY the entrypoint files the plugin's own loader
		// requires directly — extracted from the main file at runtime so
		// the list is single-sourced (re-derived every run; a loader
		// reshape fails loud here, never silently drifts — a hardcoded
		// list IS the drift trap issue #110 calls out). Board/extended
		// sub-files that the loader does NOT require directly are pulled
		// in by their own parent entrypoint IN $reg scope; requiring them
		// standalone would fatal, so they are correctly excluded.
		// Some ability files echo a "Registered N …" banner on load.
		// Buffer it so the gate's CI output stays clean and PHPUnit's
		// strict-output mode is not tripped (boot is in setUpBeforeClass,
		// but buffering keeps logs deterministic regardless).
		self::$files = self::entrypointFiles( $root );
		ob_start();
		foreach ( self::$files as $file ) {
			require_once $file;
		}

		// Each entrypoint registered its add_action('wp_abilities_api_init').
		// Fire it once. The stub wp_register_ability() stores the args
		// array; it does NOT construct WP_Ability and NEVER invokes
		// execute_callback — schemas are reflected, callbacks are not run.
		do_action( 'wp_abilities_api_init' );
		ob_end_clean();

		self::$abilities = $_wp_registered_abilities;
		return self::$abilities;
	}

	/** @return string[] Absolute entrypoint paths required during boot(). */
	public static function bootedFiles(): array {
		self::boot();
		return self::$files;
	}

	/**
	 * Statically extract the loader's DIRECT require list from the main
	 * plugin file (single source of truth — no duplication, no drift).
	 *
	 * Resolves both literal `FLUENT_ABILITIES_PATH . 'includes/x.php'`
	 * requires and the templated `"includes/{$module}/abilities.php"` /
	 * `"includes/{$dir}/{$sub}.php"` forms (module keys from the
	 * `$modules` map; `$sub` from the adjacent `foreach ( array( … ) )`).
	 * Fails loud if zero entrypoints resolve.
	 *
	 * @return string[] Absolute, existing, de-duplicated, sorted.
	 */
	private static function entrypointFiles( string $root ): array {
		$full_src = (string) file_get_contents( $root . '/abilities-for-fluent-plugins.php' );

		// Scope to the module-loader closure ONLY. The file's prelude
		// (compat/helpers/schemas/security/license/tier-gate/updater
		// requires) must be excluded — those are loaded separately above,
		// and tier-gate.php is intentionally stubbed for unit mode.
		$pos = strpos( $full_src, "add_action( 'plugins_loaded'" );
		if ( false === $pos ) {
			throw new RuntimeException(
				'ContractBootstrap: plugins_loaded module loader not found in main plugin file '
				. '(loader shape changed — fail-loud, no silent drift).'
			);
		}
		$main = substr( $full_src, $pos );

		$rel = array();

		// Module keys: the `'crm' => 'FLUENTCRM',` style map → each
		// module's includes/{module}/abilities.php entrypoint.
		if ( preg_match( '/\$modules\s*=\s*array\((.*?)\);/s', $main, $mm ) ) {
			if ( preg_match_all( "/'([a-z\\-]+)'\\s*=>\\s*'[A-Z0-9_]+'/", $mm[1], $keys ) ) {
				foreach ( $keys[1] as $module ) {
					$rel[] = "includes/{$module}/abilities.php";
				}
			}
		}

		// Literal requires: FLUENT_ABILITIES_PATH . 'includes/....php'
		if ( preg_match_all( "/FLUENT_ABILITIES_PATH\\s*\\.\\s*'(includes\\/[^']+\\.php)'/", $main, $lit ) ) {
			foreach ( $lit[1] as $p ) {
				$rel[] = $p;
			}
		}

		// Templated sub-file lists: a `foreach ( array( 'a','b',… ) as
		// $sub )` whose body builds `"includes/<dir>/{$sub}.php"`. Match
		// the sub-list and its position, then locate the nearest
		// following literal-dir template (offset scan — NO brace matching,
		// so the `}` inside `{$sub}` cannot truncate the capture, the bug
		// a naive body regex hits).
		if ( preg_match_all(
			'/array\(\s*((?:\'[a-z0-9\-]+\'\s*,?\s*)+)\)\s*as\s*\$sub\s*\)/',
			$main,
			$lists,
			PREG_SET_ORDER | PREG_OFFSET_CAPTURE
		) ) {
			foreach ( $lists as $m ) {
				$items_src = $m[1][0];
				$after     = (int) $m[0][1] + strlen( $m[0][0] );
				$window    = substr( $main, $after, 600 );
				if ( ! preg_match( '#"includes/([a-z\-]+)/\{\$sub\}\.php"#', $window, $dirm ) ) {
					continue;
				}
				if ( preg_match_all( "/'([a-z0-9\\-]+)'/", $items_src, $subs ) ) {
					foreach ( $subs[1] as $sub ) {
						$rel[] = "includes/{$dirm[1]}/{$sub}.php";
					}
				}
			}
		}

		$abs = array();
		foreach ( array_unique( $rel ) as $p ) {
			$full = $root . '/' . $p;
			if ( is_file( $full ) ) {
				$abs[ realpath( $full ) ] = true;
			}
		}
		$out = array_keys( $abs );
		sort( $out );

		if ( empty( $out ) ) {
			throw new RuntimeException(
				'ContractBootstrap: no entrypoints resolved from the main plugin loader '
				. '— the loader shape changed; update entrypointFiles() (fail-loud, no silent drift).'
			);
		}
		return $out;
	}
}
