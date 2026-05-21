<?php
/**
 * Unit tests — FluentCRM extended Templates + Email-Patterns + Editor-Patterns
 * clusters (§5.6, §5.7, §5.8).
 *
 * @package Fluent_Abilities\Tests\Unit\CRM
 */

use PHPUnit\Framework\TestCase;

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) { return $text; }
}

require_once dirname( __DIR__, 3 ) . '/includes/crm/extended-templates-and-patterns.php';

class FluentCRMTemplatesAndPatternsAbilitiesTest extends TestCase {

	private const SLUGS = array(
		// §5.6 Templates
		'fluent-crm/get-template'                     => 'read',
		'fluent-crm/create-template'                  => 'write',
		'fluent-crm/update-template'                  => 'write',
		'fluent-crm/delete-template'                  => 'delete',
		'fluent-crm/duplicate-template'               => 'write',
		'fluent-crm/list-templates-all'               => 'read',
		'fluent-crm/list-smart-codes'                 => 'read',
		// fluent-crm/set-global-email-style REMOVED in v1.4.0 P7 close
		// (handler reads `config`, ability forwarded `style`; input
		// silently discarded since v2.0.0). see docs/P7-CLOSE.md
		'fluent-crm/list-built-in-templates'          => 'read',
		'fluent-crm/do-bulk-action-templates'         => 'write',
		// §5.7 Email patterns
		'fluent-crm/create-email-pattern'             => 'write',
		'fluent-crm/get-email-pattern'                => 'read',
		'fluent-crm/update-email-pattern'             => 'write',
		'fluent-crm/delete-email-pattern'             => 'delete',
		'fluent-crm/get-email-pattern-wp-format'      => 'write',
		'fluent-crm/list-email-pattern-categories'    => 'read',
		'fluent-crm/delete-email-pattern-category'    => 'delete',
		// §5.8 Editor patterns
		'fluent-crm/list-editor-patterns'             => 'read',
		'fluent-crm/create-editor-pattern'            => 'write',
		'fluent-crm/manage-editor-pattern'            => 'write',
		'fluent-crm/list-editor-pattern-categories'   => 'read',
		'fluent-crm/list-editor-cart-products'        => 'read',
	);

	protected function setUp(): void {
		global $_wp_registered_abilities;
		$_wp_registered_abilities = array();
		update_option( 'fluent_abilities_enabled_modules', array( 'crm' ) );
		$GLOBALS['_test_current_user_id'] = 1;
		$GLOBALS['_test_user_caps']       = array( 'fluent_crm_read', 'fluent_crm_write', 'fluent_crm_delete' );
		fluent_abilities_crm_register_extended_templates_and_patterns();
	}

	protected function tearDown(): void {
		unset( $GLOBALS['_test_user_caps'], $GLOBALS['_test_current_user_id'] );
		delete_option( 'fluent_abilities_enabled_modules' );
	}

	public function test_all_slugs_register() {
		$abilities = wp_get_abilities();
		foreach ( array_keys( self::SLUGS ) as $slug ) {
			$this->assertArrayHasKey( $slug, $abilities, "Missing: {$slug}" );
		}
	}

	public function test_annotations_match_tier() {
		$abilities = wp_get_abilities();
		foreach ( self::SLUGS as $slug => $tier ) {
			$ann = $abilities[ $slug ]['meta']['annotations'];
			$this->assertSame( $tier, $ann['permission'], "{$slug} wrong permission" );
			$this->assertSame( 'read' === $tier, $ann['readonly'], "{$slug} wrong readonly" );
			$this->assertSame( 'delete' === $tier, $ann['destructive'], "{$slug} wrong destructive" );
		}
	}

	public function test_delete_permission_callback_rejects_user_with_only_read_cap() {
		$abilities                  = wp_get_abilities();
		$GLOBALS['_test_user_caps'] = array( 'fluent_crm_read' );
		$cb                         = $abilities['fluent-crm/delete-template']['permission_callback'];
		$this->assertFalse( $cb() );
	}

	public function test_create_template_requires_title() {
		$abilities = wp_get_abilities();
		$this->assertContains( 'title', $abilities['fluent-crm/create-template']['input_schema']['required'] );
	}

	public function test_callbacks_all_invokable() {
		$abilities = wp_get_abilities();
		foreach ( array_keys( self::SLUGS ) as $slug ) {
			$this->assertIsCallable( $abilities[ $slug ]['execute_callback'] );
		}
	}

	public function test_smart_codes_is_read_only_and_load_bearing() {
		$abilities = wp_get_abilities();
		$ann       = $abilities['fluent-crm/list-smart-codes']['meta']['annotations'];
		$this->assertTrue( $ann['readonly'] );
	}
}
