<?php
/**
 * Unit tests — v1.4.0 Package 1 (Safety hotfix).
 *
 * Covers:
 *  - V7: fluent-crm/create-webhook callback whitelists top-level input keys to
 *    schema-declared names before passing to the vendor REST layer; adapter/MCP
 *    transport envelope (method/params/jsonrpc/id/toolUseId/_links/_embedded)
 *    must NOT reach the vendor request params.
 *  - V8: fluent-crm/update-contact-custom-fields carries `destructive: true`
 *    annotation AND rejects `fields: []` without `confirm_full_replace: true`
 *    with a typed WP_Error (no vendor write performed); accepts explicit
 *    confirmation and forwards a whitelisted payload.
 *
 * Probe site stop-condition reference: SPRINT BRIEF CAPTURE — v1.4.0 Cold-Start
 * Re-test §14 entry (a) — 8 production custom field definitions wiped on
 * helenawillow during Phase 2 of the cold-start re-test.
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

require_once dirname( __DIR__, 3 ) . '/includes/crm/extended-misc-medium.php';
require_once dirname( __DIR__, 3 ) . '/includes/crm/extended-misc-small.php';

class FluentCRMSafetyFixesTest extends TestCase {

	protected function setUp(): void {
		global $_wp_registered_abilities, $_test_rest_log, $_test_rest_response;
		$_wp_registered_abilities = array();
		$_test_rest_log           = array();
		$_test_rest_response      = array( 'id' => 999, 'message' => 'ok' );
		update_option( 'fluent_abilities_enabled_modules', array( 'crm' ) );
		$GLOBALS['_test_current_user_id'] = 1;
		$GLOBALS['_test_user_caps']       = array( 'fluent_crm_read', 'fluent_crm_write', 'fluent_crm_delete' );
		fluent_abilities_crm_register_extended_misc_medium();
		fluent_abilities_crm_register_extended_misc_small();
	}

	protected function tearDown(): void {
		global $_test_rest_log, $_test_rest_response;
		$_test_rest_log      = array();
		$_test_rest_response = array();
		unset( $GLOBALS['_test_user_caps'], $GLOBALS['_test_current_user_id'] );
		delete_option( 'fluent_abilities_enabled_modules' );
	}

	// =========================================================================
	// V8 — fluent-crm/update-contact-custom-fields
	// =========================================================================

	public function test_update_contact_custom_fields_carries_destructive_annotation() {
		$abilities = wp_get_abilities();
		$ann       = $abilities['fluent-crm/update-contact-custom-fields']['meta']['annotations'];
		$this->assertTrue( $ann['destructive'], 'V8: destructive annotation must be true on full-replace ability' );
		$this->assertSame( 'write', $ann['permission'] );
	}

	public function test_update_contact_custom_fields_description_warns_about_full_replace() {
		$abilities = wp_get_abilities();
		$desc      = $abilities['fluent-crm/update-contact-custom-fields']['description'];
		$this->assertStringContainsStringIgnoringCase( 'destructive', $desc );
		$this->assertStringContainsStringIgnoringCase( 'full replace', $desc );
		$this->assertStringContainsStringIgnoringCase( 'clears all', $desc );
		$this->assertStringContainsString( 'confirm_full_replace', $desc );
	}

	public function test_update_contact_custom_fields_schema_declares_confirm_flag() {
		$abilities = wp_get_abilities();
		$props     = $abilities['fluent-crm/update-contact-custom-fields']['input_schema']['properties'];
		$this->assertArrayHasKey( 'confirm_full_replace', $props );
		$this->assertSame( 'boolean', $props['confirm_full_replace']['type'] );
		$this->assertStringContainsStringIgnoringCase( 'empty array', $props['confirm_full_replace']['description'] );
	}

	public function test_update_contact_custom_fields_empty_array_without_confirm_returns_wp_error() {
		global $_test_rest_log;
		$abilities = wp_get_abilities();
		$cb        = $abilities['fluent-crm/update-contact-custom-fields']['execute_callback'];
		$result    = $cb( array( 'fields' => array() ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'fluent_crm_custom_fields_destructive_unconfirmed', $result->get_error_code() );
		$this->assertCount( 0, $_test_rest_log, 'V8: no vendor write may occur when destructive call is unconfirmed' );
	}

	public function test_update_contact_custom_fields_empty_array_with_confirm_proceeds() {
		global $_test_rest_log;
		$abilities = wp_get_abilities();
		$cb        = $abilities['fluent-crm/update-contact-custom-fields']['execute_callback'];
		$result    = $cb( array( 'fields' => array(), 'confirm_full_replace' => true ) );
		$this->assertNotInstanceOf( WP_Error::class, $result );
		$this->assertCount( 1, $_test_rest_log );
		$this->assertSame( 'PUT', $_test_rest_log[0]['method'] );
		$this->assertSame( '/fluent-crm/v2/custom-fields/contacts', $_test_rest_log[0]['route'] );
		$this->assertSame( array( 'fields' => array() ), $_test_rest_log[0]['params'] );
		// Confirmation flag must NOT be forwarded to vendor.
		$this->assertArrayNotHasKey( 'confirm_full_replace', $_test_rest_log[0]['params'] );
	}

	public function test_update_contact_custom_fields_missing_fields_returns_wp_error() {
		global $_test_rest_log;
		$abilities = wp_get_abilities();
		$cb        = $abilities['fluent-crm/update-contact-custom-fields']['execute_callback'];
		$result    = $cb( array() );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'fluent_crm_custom_fields_missing', $result->get_error_code() );
		$this->assertCount( 0, $_test_rest_log );
	}

	public function test_update_contact_custom_fields_non_empty_set_proceeds() {
		global $_test_rest_log;
		$abilities = wp_get_abilities();
		$cb        = $abilities['fluent-crm/update-contact-custom-fields']['execute_callback'];
		$fields    = array(
			array( 'slug' => 'company', 'label' => 'Company', 'type' => 'text' ),
		);
		$result    = $cb( array( 'fields' => $fields ) );
		$this->assertNotInstanceOf( WP_Error::class, $result );
		$this->assertCount( 1, $_test_rest_log );
		$this->assertSame( array( 'fields' => $fields ), $_test_rest_log[0]['params'] );
	}

	public function test_update_contact_custom_fields_strips_unknown_top_level_keys() {
		global $_test_rest_log;
		$abilities = wp_get_abilities();
		$cb        = $abilities['fluent-crm/update-contact-custom-fields']['execute_callback'];
		$fields    = array( array( 'slug' => 'company', 'label' => 'Company', 'type' => 'text' ) );
		$cb( array(
			'fields'  => $fields,
			'method'  => 'tools/call',
			'jsonrpc' => '2.0',
			'id'      => 'mcp-1',
			'_links'  => array( 'self' => 'http://example' ),
		) );
		$this->assertCount( 1, $_test_rest_log );
		$this->assertSame( array( 'fields' ), array_keys( $_test_rest_log[0]['params'] ) );
	}

	// =========================================================================
	// V7 — fluent-crm/create-webhook
	//
	// The callback bypasses rest_do_request and calls the vendor public model
	// FluentCrm\App\Models\Webhook::store() directly. Reason: vendor
	// Request::all() re-reads php://input and merges over WP_REST_Request
	// params, defeating a whitelist applied at the REST-controller layer.
	// Unit tests below assert: (a) typed error when the vendor class is
	// absent; (b) typed error on missing required fields; (c) whitelist
	// payload reaches Webhook::store() with no envelope keys.
	// =========================================================================

	public function test_create_webhook_returns_wp_error_when_vendor_class_absent() {
		// Default unit-test environment: \FluentCrm\App\Models\Webhook is NOT
		// loaded. The callback must surface a typed WP_Error, not fatal.
		$this->assertFalse( class_exists( '\\FluentCrm\\App\\Models\\Webhook', false ), 'precondition: vendor class must not exist' );
		$abilities = wp_get_abilities();
		$cb        = $abilities['fluent-crm/create-webhook']['execute_callback'];
		$result    = $cb( array( 'name' => 'x', 'status' => 'subscribed' ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'fluent_crm_unavailable', $result->get_error_code() );
	}

	public function test_create_webhook_whitelists_envelope_keys() {
		// Drive the callback against a stub \FluentCrm\App\Models\Webhook that
		// captures the $payload passed to ::store(). Assert envelope keys do
		// not reach the persistence layer.
		require_once __DIR__ . '/fixtures/webhook-model-stub.php';
		FluentCRMTestWebhookStub::$captured_payload = null;
		FluentCRMTestWebhookStub::$next_id          = 42;

		$abilities = wp_get_abilities();
		$cb        = $abilities['fluent-crm/create-webhook']['execute_callback'];
		$result    = $cb( array(
			'name'      => 'Probe webhook',
			'status'    => 'subscribed',
			'lists'     => array( 1, 2 ),
			'tags'      => array(),
			'companies' => array(),
			'provider'  => 'default',
			'extra'     => array( 'note' => 'hello' ),
			// Adapter / MCP transport envelope — must be stripped.
			'method'    => 'tools/call',
			'params'    => array( 'name' => 'x' ),
			'jsonrpc'   => '2.0',
			'id'        => 'mcp-1',
			'toolUseId' => 'tu-1',
			'_links'    => array( 'self' => 'http://example' ),
			'_embedded' => array( 'foo' => 'bar' ),
		) );

		$this->assertNotInstanceOf( WP_Error::class, $result );
		$this->assertSame( 42, $result['id'] );

		$persisted_keys = array_keys( FluentCRMTestWebhookStub::$captured_payload );
		sort( $persisted_keys );
		$this->assertSame(
			array( 'companies', 'extra', 'lists', 'name', 'provider', 'status', 'tags' ),
			$persisted_keys,
			'V7: only schema-declared keys may reach Webhook::store()'
		);
		foreach ( array( 'method', 'params', 'jsonrpc', 'id', 'toolUseId', '_links', '_embedded' ) as $leaked_key ) {
			$this->assertArrayNotHasKey(
				$leaked_key,
				FluentCRMTestWebhookStub::$captured_payload,
				"V7: {$leaked_key} must not be persisted"
			);
		}
	}

	public function test_create_webhook_minimal_input_persists_required_fields_only() {
		require_once __DIR__ . '/fixtures/webhook-model-stub.php';
		FluentCRMTestWebhookStub::$captured_payload = null;
		FluentCRMTestWebhookStub::$next_id          = 7;

		$abilities = wp_get_abilities();
		$cb        = $abilities['fluent-crm/create-webhook']['execute_callback'];
		$result    = $cb( array( 'name' => 'Probe', 'status' => 'subscribed' ) );
		$this->assertSame(
			array( 'name' => 'Probe', 'status' => 'subscribed' ),
			FluentCRMTestWebhookStub::$captured_payload
		);
		$this->assertSame( 7, $result['id'] );
	}

	public function test_create_webhook_rejects_missing_required_field() {
		require_once __DIR__ . '/fixtures/webhook-model-stub.php';
		FluentCRMTestWebhookStub::$captured_payload = null;

		$abilities = wp_get_abilities();
		$cb        = $abilities['fluent-crm/create-webhook']['execute_callback'];
		$result    = $cb( array( 'name' => 'Probe' ) ); // status missing.
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'fluent_crm_webhook_missing_field', $result->get_error_code() );
		$this->assertNull( FluentCRMTestWebhookStub::$captured_payload, 'no persistence may occur when required field is missing' );
	}

	// =========================================================================
	// V7 — fluent-crm/update-webhook (Package 3.5, P-I family)
	//
	// Same leak shape as create-webhook: WebhookController::update calls
	// $webhook->saveChanges($request->all()) and Webhook::saveChanges()
	// array_merges the whole payload into the stored `value`. The callback now
	// whitelists to schema-declared keys and routes through the vendor public
	// model (V3 priority 2), bypassing rest_do_request.
	// =========================================================================

	/**
	 * Runs in a separate process so the FluentCrm\App\Models\Webhook
	 * class_alias registered by the create-webhook stub tests (same process,
	 * earlier in declaration order) does not leak in and defeat the
	 * absent-vendor precondition.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_update_webhook_returns_wp_error_when_vendor_class_absent() {
		require_once dirname( __DIR__, 3 ) . '/includes/crm/extended-misc-small.php';
		global $_wp_registered_abilities;
		$_wp_registered_abilities = array();
		fluent_abilities_crm_register_extended_misc_small();

		$this->assertFalse( class_exists( '\\FluentCrm\\App\\Models\\Webhook', false ), 'precondition: vendor class must not exist' );
		$abilities = wp_get_abilities();
		$cb        = $abilities['fluent-crm/update-webhook']['execute_callback'];
		$result    = $cb( array( 'id' => 5, 'name' => 'x' ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'fluent_crm_unavailable', $result->get_error_code() );
	}

	public function test_update_webhook_rejects_missing_id() {
		require_once __DIR__ . '/fixtures/webhook-model-stub.php';
		FluentCRMTestWebhookStub::$captured_payload = null;

		$abilities = wp_get_abilities();
		$cb        = $abilities['fluent-crm/update-webhook']['execute_callback'];
		$result    = $cb( array( 'name' => 'no id here' ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'fluent_crm_webhook_missing_field', $result->get_error_code() );
		$this->assertNull( FluentCRMTestWebhookStub::$captured_payload, 'no persistence when id missing' );
	}

	public function test_update_webhook_returns_wp_error_when_not_found() {
		require_once __DIR__ . '/fixtures/webhook-model-stub.php';
		FluentCRMTestWebhookStub::$captured_payload = null;
		FluentCRMTestWebhookStub::$find_returns     = false;

		$abilities = wp_get_abilities();
		$cb        = $abilities['fluent-crm/update-webhook']['execute_callback'];
		$result    = $cb( array( 'id' => 999, 'name' => 'x' ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'fluent_crm_webhook_not_found', $result->get_error_code() );
		$this->assertNull( FluentCRMTestWebhookStub::$captured_payload, 'no persistence when webhook not found' );

		FluentCRMTestWebhookStub::$find_returns = true; // reset for other tests.
	}

	public function test_update_webhook_whitelists_envelope_keys() {
		require_once __DIR__ . '/fixtures/webhook-model-stub.php';
		FluentCRMTestWebhookStub::$captured_payload = null;
		FluentCRMTestWebhookStub::$find_returns     = true;

		$abilities = wp_get_abilities();
		$cb        = $abilities['fluent-crm/update-webhook']['execute_callback'];
		$result    = $cb( array(
			'id'        => 12,
			'name'      => 'Updated webhook',
			'lists'     => array( 3 ),
			'tags'      => array(),
			'companies' => array(),
			'extra'     => array( 'note' => 'hi' ),
			// Adapter / MCP transport envelope — must be stripped.
			'method'    => 'tools/call',
			'params'    => array( 'name' => 'x' ),
			'jsonrpc'   => '2.0',
			'toolUseId' => 'tu-2',
			'_links'    => array( 'self' => 'http://example' ),
			'_embedded' => array( 'foo' => 'bar' ),
		) );

		$this->assertNotInstanceOf( WP_Error::class, $result );
		$this->assertTrue( $result['success'] );

		$persisted_keys = array_keys( FluentCRMTestWebhookStub::$captured_payload );
		sort( $persisted_keys );
		$this->assertSame(
			array( 'companies', 'extra', 'lists', 'name', 'tags' ),
			$persisted_keys,
			'V7: only schema-declared keys may reach Webhook::saveChanges()'
		);
		foreach ( array( 'id', 'method', 'params', 'jsonrpc', 'toolUseId', '_links', '_embedded' ) as $leaked_key ) {
			$this->assertArrayNotHasKey(
				$leaked_key,
				FluentCRMTestWebhookStub::$captured_payload,
				"V7: {$leaked_key} must not reach saveChanges()"
			);
		}
	}

	public function test_update_webhook_does_not_use_proxy() {
		// Source guard: the callback must NOT route through rest_do_request
		// (the proxy) — that path cannot enforce V7 (vendor Request::all()
		// re-reads php://input).
		$src = file_get_contents( dirname( __DIR__, 3 ) . '/includes/crm/extended-misc-small.php' );
		$pos = strpos( $src, "'fluent-crm/update-webhook'" );
		$this->assertNotFalse( $pos );
		$block = substr( $src, $pos, 3600 );
		$this->assertStringNotContainsString(
			"\$proxy( 'PUT', '/fluent-crm/v2/webhooks/'",
			$block,
			'update-webhook must not route the write through the rest_do_request proxy (V7 cannot be enforced there)'
		);
		$this->assertStringContainsString(
			'->saveChanges( $payload )',
			$block,
			'update-webhook must route through the vendor public model with the whitelisted payload (V3 priority 2)'
		);
	}
}
