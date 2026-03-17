<?php
/**
 * PHPUnit Bootstrap — Abilities for Fluent Plugins
 *
 * Two modes:
 *  1. Unit tests (no WP_TESTS_DIR) — loads stubs only. Fast, no database.
 *  2. Integration tests (WP_TESTS_DIR set) — loads WordPress test suite.
 *
 * To run integration tests locally:
 *   export WP_TESTS_DIR=/tmp/wordpress-tests-lib
 *   bin/install-wp-tests.sh fluent_abilities_test root '' localhost latest
 *   vendor/bin/phpunit --testsuite Integration
 */

define( 'FLUENT_ABILITIES_TESTS', true );

$vendor_autoload = dirname( __DIR__ ) . '/vendor/autoload.php';
if ( file_exists( $vendor_autoload ) ) {
	require_once $vendor_autoload;
} else {
	spl_autoload_register( function( $class ) {
		$prefix = 'WickedEvolutions\\AbilitiesForFluent\\';
		if ( strpos( $class, $prefix ) !== 0 ) {
			return;
		}
		$relative = substr( $class, strlen( $prefix ) );
		$file     = dirname( __DIR__ ) . '/src/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	} );
}

$wp_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( $wp_tests_dir ) {
	// ── Integration mode ──────────────────────────────────────────────────────
	if ( ! file_exists( $wp_tests_dir . '/includes/functions.php' ) ) {
		echo "ERROR: WP_TESTS_DIR ($wp_tests_dir) does not contain WordPress test suite.\n";
		echo "Run: bin/install-wp-tests.sh\n";
		exit( 1 );
	}

	require_once $wp_tests_dir . '/includes/functions.php';

	tests_add_filter( 'muplugins_loaded', function() {
		require_once dirname( __DIR__ ) . '/abilities-for-fluent-plugins.php';
	} );

	require_once $wp_tests_dir . '/includes/bootstrap.php';

} else {
	// ── Unit mode ─────────────────────────────────────────────────────────────
	require_once __DIR__ . '/stubs/wordpress-stubs.php';

	// Load plugin infrastructure safe without full WP.
	// security.php is excluded — its functions are stubbed above and it uses
	// add_action() hooks not needed in unit tests.
	require_once dirname( __DIR__ ) . '/includes/helpers.php';
	require_once dirname( __DIR__ ) . '/includes/schemas.php';
	require_once dirname( __DIR__ ) . '/includes/compat.php';
}
