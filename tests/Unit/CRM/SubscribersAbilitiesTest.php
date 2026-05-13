<?php
/**
 * Unit tests — FluentCRM extended Subscriber clusters (§5.1 + §5.2).
 *
 * @package Fluent_Abilities\Tests\Unit\CRM
 */

use PHPUnit\Framework\TestCase;

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) { return $text; }
}

require_once dirname( __DIR__, 3 ) . '/includes/crm/extended-subscribers.php';

class FluentCRMSubscribersAbilitiesTest extends TestCase {

	private const SLUGS = array(
		// §5.1
		'fluent-crm/get-contact-purchase-history'    => 'read',
		'fluent-crm/get-contact-form-submissions'    => 'read',
		'fluent-crm/get-contact-support-tickets'     => 'read',
		'fluent-crm/get-contact-info-widgets'        => 'read',
		'fluent-crm/get-contact-dynamic-item-view'   => 'read',
		'fluent-crm/get-contact-external-view'       => 'write',
		'fluent-crm/get-contact-url-metrics'         => 'read',
		'fluent-crm/list-subscriber-tracking-events' => 'read',
		'fluent-crm/list-subscribers-prev-next-ids'  => 'read',
		'fluent-crm/search-contacts-fast'            => 'read',
		// §5.2
		'fluent-crm/update-subscribers-property'     => 'write',
		'fluent-crm/sync-subscribers-segments'       => 'write',
		'fluent-crm/do-bulk-action-contacts'         => 'write',
		'fluent-crm/bulk-add-update-contacts'        => 'write',
		'fluent-crm/export-subscribers'              => 'read',
	);

	protected function setUp(): void {
		global $_wp_registered_abilities;
		$_wp_registered_abilities = array();
		update_option( 'fluent_abilities_enabled_modules', array( 'crm' ) );
		$GLOBALS['_test_current_user_id'] = 1;
		$GLOBALS['_test_user_caps']       = array( 'fluent_crm_read', 'fluent_crm_write', 'fluent_crm_delete' );
		fluent_abilities_crm_register_extended_subscribers();
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

	public function test_write_permission_rejects_user_without_write_cap() {
		$abilities                  = wp_get_abilities();
		$GLOBALS['_test_user_caps'] = array( 'fluent_crm_read' );
		$cb                         = $abilities['fluent-crm/bulk-add-update-contacts']['permission_callback'];
		$this->assertFalse( $cb() );
	}

	public function test_id_required_on_extension_reads() {
		$abilities = wp_get_abilities();
		foreach ( array(
			'fluent-crm/get-contact-purchase-history',
			'fluent-crm/get-contact-form-submissions',
			'fluent-crm/get-contact-url-metrics',
		) as $slug ) {
			$this->assertContains( 'id', $abilities[ $slug ]['input_schema']['required'], "{$slug} should require id" );
		}
	}

	public function test_bulk_add_update_requires_subscribers() {
		$abilities = wp_get_abilities();
		$this->assertContains( 'subscribers', $abilities['fluent-crm/bulk-add-update-contacts']['input_schema']['required'] );
	}

	public function test_do_bulk_action_requires_action_name_and_subscriber_ids() {
		$abilities = wp_get_abilities();
		$req       = $abilities['fluent-crm/do-bulk-action-contacts']['input_schema']['required'];
		$this->assertContains( 'action_name', $req );
		$this->assertContains( 'subscriber_ids', $req );
	}

	public function test_callbacks_all_invokable() {
		$abilities = wp_get_abilities();
		foreach ( array_keys( self::SLUGS ) as $slug ) {
			$this->assertIsCallable( $abilities[ $slug ]['execute_callback'] );
		}
	}
}
