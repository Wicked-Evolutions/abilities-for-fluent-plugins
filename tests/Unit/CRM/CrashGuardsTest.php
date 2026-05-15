<?php
/**
 * Unit tests — v1.4.0 Package 2 (Crash blockers) — CRM portion.
 *
 * Covers the 5 P-K TypeError sites in FluentCRM: every callback now wraps the
 * proxy call in try/catch and returns WP_Error('vendor_precondition_failed',
 * …) instead of letting a vendor-side PHP TypeError propagate as a fatal.
 *
 *   - fluent-crm/list-funnel-templates           (extended-funnels.php)
 *   - fluent-crm/list-dynamic-segment-custom-fields (extended-pro-marketing.php)
 *   - fluent-crm/list-campaigns-pro-products     (extended-pro-marketing.php)
 *   - fluent-crm/sync-subscribers-segments       (extended-subscribers.php)
 *   - fluent-crm/create-company-note             (extended-pro-companies.php)
 *
 * Mechanism: tests/stubs/wordpress-stubs.php stubs rest_do_request to throw a
 * \TypeError when $GLOBALS['_test_rest_throw'] is truthy. We drive the
 * callback under that condition and assert WP_Error.
 *
 * @package Fluent_Abilities\Tests\Unit\CRM
 */

use PHPUnit\Framework\TestCase;

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) { return $text; }
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $key ) );
	}
}

require_once dirname( __DIR__, 3 ) . '/includes/crm/extended-funnels.php';
require_once dirname( __DIR__, 3 ) . '/includes/crm/extended-pro-marketing.php';
require_once dirname( __DIR__, 3 ) . '/includes/crm/extended-subscribers.php';
require_once dirname( __DIR__, 3 ) . '/includes/crm/extended-pro-companies.php';

class FluentCRMCrashGuardsTest extends TestCase {

	private const GUARDED_SLUGS = array(
		'fluent-crm/list-funnel-templates',
		'fluent-crm/list-dynamic-segment-custom-fields',
		'fluent-crm/list-campaigns-pro-products',
		'fluent-crm/sync-subscribers-segments',
		'fluent-crm/create-company-note',
	);

	protected function setUp(): void {
		global $_wp_registered_abilities, $_test_rest_log, $_test_rest_response, $_test_rest_throw;
		$_wp_registered_abilities = array();
		$_test_rest_log           = array();
		$_test_rest_response      = array();
		$_test_rest_throw         = null;
		update_option( 'fluent_abilities_enabled_modules', array( 'crm' ) );
		$GLOBALS['_test_current_user_id'] = 1;
		$GLOBALS['_test_user_caps']       = array( 'fluent_crm_read', 'fluent_crm_write', 'fluent_crm_delete' );

		fluent_abilities_crm_register_extended_funnels();
		fluent_abilities_crm_register_extended_pro_marketing();
		fluent_abilities_crm_register_extended_subscribers();
		fluent_abilities_crm_register_extended_pro_companies();
	}

	protected function tearDown(): void {
		global $_test_rest_throw;
		$_test_rest_throw = null;
		unset( $GLOBALS['_test_user_caps'], $GLOBALS['_test_current_user_id'] );
		delete_option( 'fluent_abilities_enabled_modules' );
	}

	public function test_all_guarded_slugs_register() {
		$abilities = wp_get_abilities();
		foreach ( self::GUARDED_SLUGS as $slug ) {
			$this->assertArrayHasKey( $slug, $abilities, "Missing P-K guarded slug: {$slug}" );
		}
	}

	/**
	 * @dataProvider guarded_slug_inputs
	 */
	public function test_guard_returns_wp_error_when_vendor_throws( string $slug, array $input ) {
		global $_test_rest_throw;
		$_test_rest_throw = 'simulated vendor TypeError';

		$abilities = wp_get_abilities();
		$cb        = $abilities[ $slug ]['execute_callback'];
		$result    = $cb( $input );

		$this->assertInstanceOf(
			WP_Error::class,
			$result,
			"P-K guard for {$slug} must return WP_Error when vendor throws, not let the fatal propagate"
		);
		$this->assertSame( 'vendor_precondition_failed', $result->get_error_code() );
	}

	/**
	 * @dataProvider guarded_slug_inputs
	 */
	public function test_guard_passes_through_when_vendor_succeeds( string $slug, array $input ) {
		global $_test_rest_throw, $_test_rest_response;
		$_test_rest_throw    = null;
		$_test_rest_response = array( 'ok' => true );

		$abilities = wp_get_abilities();
		$cb        = $abilities[ $slug ]['execute_callback'];
		$result    = $cb( $input );

		$this->assertNotInstanceOf( WP_Error::class, $result, "P-K guard for {$slug} must not over-fire on success" );
	}

	public static function guarded_slug_inputs(): array {
		return array(
			'list-funnel-templates' => array(
				'fluent-crm/list-funnel-templates',
				array(),
			),
			'list-dynamic-segment-custom-fields' => array(
				'fluent-crm/list-dynamic-segment-custom-fields',
				array(),
			),
			'list-campaigns-pro-products' => array(
				'fluent-crm/list-campaigns-pro-products',
				array(),
			),
			'sync-subscribers-segments' => array(
				'fluent-crm/sync-subscribers-segments',
				array( 'subscriber_ids' => array( 1 ), 'add_tags' => array() ),
			),
			'create-company-note' => array(
				'fluent-crm/create-company-note',
				array( 'id' => 42, 'description' => 'test note' ),
			),
		);
	}
}
