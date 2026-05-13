<?php
/**
 * Unit tests — FluentCRM Pro Companies cluster (§5.23).
 *
 * @package Fluent_Abilities\Tests\Unit\CRM
 */

use PHPUnit\Framework\TestCase;

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) { return $text; }
}

require_once dirname( __DIR__, 3 ) . '/includes/crm/extended-pro-companies.php';

class FluentCRMProCompaniesAbilitiesTest extends TestCase {

	private const SLUGS = array(
		'fluent-crm/get-company'                                  => 'read',
		'fluent-crm/create-company'                               => 'write',
		'fluent-crm/update-company'                               => 'write',
		'fluent-crm/delete-company'                               => 'delete',
		'fluent-crm/search-companies'                             => 'read',
		'fluent-crm/search-unattached-contacts-for-company'       => 'read',
		'fluent-crm/update-companies-property'                    => 'write',
		'fluent-crm/attach-subscribers-to-company'                => 'write',
		'fluent-crm/detach-subscribers-from-company'              => 'write',
		'fluent-crm/do-bulk-action-companies'                     => 'write',
		'fluent-crm/list-company-notes'                           => 'read',
		'fluent-crm/create-company-note'                          => 'write',
		'fluent-crm/update-company-note'                          => 'write',
		'fluent-crm/delete-company-note'                          => 'delete',
		'fluent-crm/import-companies-csv'                         => 'write',
		'fluent-crm/get-company-custom-fields'                    => 'write',
		'fluent-crm/get-company-custom-tab-view'                  => 'read',
	);

	protected function setUp(): void {
		global $_wp_registered_abilities;
		$_wp_registered_abilities = array();
		update_option( 'fluent_abilities_enabled_modules', array( 'crm' ) );
		$GLOBALS['_test_current_user_id'] = 1;
		$GLOBALS['_test_user_caps']       = array( 'fluent_crm_read', 'fluent_crm_write', 'fluent_crm_delete' );
		fluent_abilities_crm_register_extended_pro_companies();
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
			$this->assertSame( $tier, $abilities[ $slug ]['meta']['annotations']['permission'], "{$slug} tier" );
		}
	}

	public function test_delete_company_rejects_read_only_user() {
		$abilities                  = wp_get_abilities();
		$GLOBALS['_test_user_caps'] = array( 'fluent_crm_read' );
		$cb                         = $abilities['fluent-crm/delete-company']['permission_callback'];
		$this->assertFalse( $cb() );
	}

	public function test_create_company_requires_name() {
		$abilities = wp_get_abilities();
		$this->assertContains( 'name', $abilities['fluent-crm/create-company']['input_schema']['required'] );
	}

	public function test_attach_subscribers_requires_company_id_and_subscriber_ids() {
		$abilities = wp_get_abilities();
		$req       = $abilities['fluent-crm/attach-subscribers-to-company']['input_schema']['required'];
		$this->assertContains( 'company_id', $req );
		$this->assertContains( 'subscriber_ids', $req );
	}

	public function test_callbacks_all_invokable() {
		$abilities = wp_get_abilities();
		foreach ( array_keys( self::SLUGS ) as $slug ) {
			$this->assertIsCallable( $abilities[ $slug ]['execute_callback'] );
		}
	}
}
