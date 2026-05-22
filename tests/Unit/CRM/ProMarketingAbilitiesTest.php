<?php
/**
 * Unit tests — FluentCRM Pro marketing clusters (§5.24, §5.25, §5.26, §5.27, §5.28).
 *
 * @package Fluent_Abilities\Tests\Unit\CRM
 */

use PHPUnit\Framework\TestCase;

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) { return $text; }
}
if ( ! function_exists( 'rawurlencode' ) ) {
	// rawurlencode is a PHP builtin — should already exist; defensive only.
}

require_once dirname( __DIR__, 3 ) . '/includes/crm/extended-pro-marketing.php';

class FluentCRMProMarketingAbilitiesTest extends TestCase {

	private const SLUGS = array(
		// §5.24
		'fluent-crm/list-sequences-for-subscriber'              => 'read',
		'fluent-crm/duplicate-sequence'                         => 'write',
		'fluent-crm/duplicate-sequence-email'                   => 'write',
		'fluent-crm/update-sequence-email-delay'                => 'write',
		'fluent-crm/manage-sequence-subscribers'                => 'write',
		'fluent-crm/reapply-sequence'                           => 'write',
		// §5.25
		'fluent-crm/list-recurring-campaigns'                   => 'read',
		'fluent-crm/create-recurring-campaign'                  => 'write',
		'fluent-crm/get-recurring-campaign'                     => 'read',
		'fluent-crm/update-recurring-campaign-data'             => 'write',
		'fluent-crm/change-recurring-campaign-status'           => 'write',
		'fluent-crm/update-recurring-campaign-settings'         => 'write',
		'fluent-crm/duplicate-recurring-campaign'               => 'write',
		'fluent-crm/list-recurring-campaign-emails'             => 'read',
		'fluent-crm/get-recurring-campaign-email'               => 'read',
		'fluent-crm/update-recurring-campaign-email'            => 'write',
		'fluent-crm/update-recurring-campaign-labels'           => 'write',
		// §5.26
		'fluent-crm/list-dynamic-segments'                      => 'read',
		'fluent-crm/get-dynamic-segment-stats'                  => 'read',
		'fluent-crm/estimate-dynamic-segment-contacts'          => 'read',
		'fluent-crm/update-dynamic-segment'                     => 'write',
		'fluent-crm/delete-dynamic-segment'                     => 'delete',
		'fluent-crm/duplicate-dynamic-segment'                  => 'write',
		'fluent-crm/list-dynamic-segment-custom-fields'         => 'read',
		// §5.27
		'fluent-crm/resend-failed-campaign-emails'              => 'write',
		'fluent-crm/resend-unopened-campaign-emails'            => 'write',
		'fluent-crm/resend-campaign-emails'                     => 'write',
		'fluent-crm/tag-actions-on-campaign'                    => 'write',
		'fluent-crm/list-campaigns-pro-posts'                   => 'read',
		'fluent-crm/list-campaigns-pro-post-taxonomies'         => 'read',
		'fluent-crm/list-campaigns-pro-products'                => 'read',
		// §5.28
		'fluent-crm/activate-smart-link'                        => 'write',
	);

	protected function setUp(): void {
		global $_wp_registered_abilities;
		$_wp_registered_abilities = array();
		update_option( 'fluent_abilities_enabled_modules', array( 'crm' ) );
		$GLOBALS['_test_current_user_id'] = 1;
		$GLOBALS['_test_user_caps']       = array( 'fluent_crm_read', 'fluent_crm_write', 'fluent_crm_delete' );
		fluent_abilities_crm_register_extended_pro_marketing();
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
			$this->assertSame( $tier, $abilities[ $slug ]['meta']['annotations']['permission'], "{$slug} wrong tier" );
		}
	}

	public function test_delete_dynamic_segment_rejects_read_only() {
		$abilities                  = wp_get_abilities();
		$GLOBALS['_test_user_caps'] = array( 'fluent_crm_read' );
		$cb                         = $abilities['fluent-crm/delete-dynamic-segment']['permission_callback'];
		$this->assertFalse( $cb() );
	}

	public function test_create_recurring_requires_title_and_settings() {
		// Post-Pattern-B audit (commit landing with this test update): vendor's
		// RecurringCampaignController::createCampaign requires {campaign:{title,
		// settings:{scheduling_settings:{time,type}}}} — frequency is replaced
		// by settings.scheduling_settings.type per vendor source.
		$abilities = wp_get_abilities();
		$req       = $abilities['fluent-crm/create-recurring-campaign']['input_schema']['required'];
		$this->assertContains( 'title', $req );
		$this->assertContains( 'settings', $req );
		$nested_req = $abilities['fluent-crm/create-recurring-campaign']['input_schema']['properties']['settings']['properties']['scheduling_settings']['required'];
		$this->assertContains( 'time', $nested_req );
		$this->assertContains( 'type', $nested_req );
	}

	public function test_callbacks_all_invokable() {
		$abilities = wp_get_abilities();
		foreach ( array_keys( self::SLUGS ) as $slug ) {
			$this->assertIsCallable( $abilities[ $slug ]['execute_callback'] );
		}
	}
}
