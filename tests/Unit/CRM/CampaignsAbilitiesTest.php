<?php
/**
 * Unit tests — FluentCRM extended Campaign clusters (§5.3 + §5.4 + §5.5).
 *
 * @package Fluent_Abilities\Tests\Unit\CRM
 */

use PHPUnit\Framework\TestCase;

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) { return $text; }
}

require_once dirname( __DIR__, 3 ) . '/includes/crm/extended-campaigns.php';

class FluentCRMCampaignsAbilitiesTest extends TestCase {

	private const SLUGS = array(
		// §5.3
		'fluent-crm/send-test-email-campaign'                => 'write',
		'fluent-crm/preview-campaign-email-html'             => 'write',
		'fluent-crm/preview-campaign-recipient-email'        => 'read',
		'fluent-crm/estimate-campaign-contacts'              => 'read',
		'fluent-crm/update-single-campaign-property'         => 'write',
		'fluent-crm/advance-campaign-step'                   => 'write',
		'fluent-crm/pause-campaign'                          => 'write',
		'fluent-crm/resume-campaign'                         => 'write',
		'fluent-crm/duplicate-campaign'                      => 'write',
		'fluent-crm/update-campaign-title'                   => 'write',
		// §5.4
		'fluent-crm/draft-campaign-recipients'               => 'write',
		'fluent-crm/get-campaign-estimated-recipient-count'  => 'read',
		'fluent-crm/list-campaign-emails'                    => 'read',
		'fluent-crm/delete-campaign-emails'                  => 'delete',
		'fluent-crm/schedule-campaign'                       => 'write',
		'fluent-crm/unschedule-campaign'                     => 'write',
		'fluent-crm/get-campaign-processing-stat'            => 'read',
		'fluent-crm/get-campaign-share-url'                  => 'read',
		'fluent-crm/get-campaign-status'                     => 'read',
		'fluent-crm/get-campaign-overview-stats'             => 'read',
		// §5.5
		'fluent-crm/get-campaign-link-report'                => 'read',
		'fluent-crm/get-campaign-revenues'                   => 'read',
		'fluent-crm/resync-campaign-revenues'                => 'write',
		'fluent-crm/list-campaign-unsubscribers'             => 'read',
		'fluent-crm/get-campaign-contacts-by-segment'        => 'read',
		'fluent-crm/update-campaign-labels'                  => 'write',
		'fluent-crm/do-bulk-action-campaigns'                => 'write',
		'fluent-crm/do-bulk-action-tags'                     => 'write',
	);

	protected function setUp(): void {
		global $_wp_registered_abilities;
		$_wp_registered_abilities = array();
		update_option( 'fluent_abilities_enabled_modules', array( 'crm' ) );
		$GLOBALS['_test_current_user_id'] = 1;
		$GLOBALS['_test_user_caps']       = array( 'fluent_crm_read', 'fluent_crm_write', 'fluent_crm_delete' );
		fluent_abilities_crm_register_extended_campaigns();
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

	public function test_delete_campaign_emails_rejects_user_with_only_read_cap() {
		$abilities                  = wp_get_abilities();
		$GLOBALS['_test_user_caps'] = array( 'fluent_crm_read' );
		$cb                         = $abilities['fluent-crm/delete-campaign-emails']['permission_callback'];
		$this->assertFalse( $cb() );
	}

	public function test_lifecycle_state_writes_require_id() {
		$abilities = wp_get_abilities();
		foreach ( array(
			'fluent-crm/pause-campaign',
			'fluent-crm/resume-campaign',
			'fluent-crm/duplicate-campaign',
			'fluent-crm/schedule-campaign',
			'fluent-crm/unschedule-campaign',
		) as $slug ) {
			$this->assertContains( 'id', $abilities[ $slug ]['input_schema']['required'], "{$slug} should require id" );
		}
	}

	public function test_send_test_requires_campaign_id_and_to_email() {
		$abilities = wp_get_abilities();
		$req       = $abilities['fluent-crm/send-test-email-campaign']['input_schema']['required'];
		$this->assertContains( 'campaign_id', $req );
		$this->assertContains( 'to_email', $req );
	}

	public function test_callbacks_all_invokable() {
		$abilities = wp_get_abilities();
		foreach ( array_keys( self::SLUGS ) as $slug ) {
			$this->assertIsCallable( $abilities[ $slug ]['execute_callback'] );
		}
	}
}
