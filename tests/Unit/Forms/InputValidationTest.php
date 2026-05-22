<?php
/**
 * Verifies the execute_callback for representative Fluent Forms abilities
 * returns a WP_Error envelope (rest_forbidden / ability_invalid_input /
 * plugin_missing) when invoked with insufficient permissions or invalid input.
 *
 * Tests at the unit-test boundary — we exercise the early-return validation
 * paths in each callback, not the full vendor-class business logic. Vendor
 * model interactions (Form::find(), Submission::save(), etc.) are covered by
 * the integration testsuite + live verification (see PR body evidence).
 *
 * @package Fluent_Abilities\Tests\Unit\Forms
 */

require_once __DIR__ . '/FormsAbilitiesTestCase.php';

class FluentFormsInputValidationTest extends FormsAbilitiesTestCase {

	public function test_callback_returns_rest_forbidden_when_user_lacks_write_cap() {
		$GLOBALS['_test_current_user_id'] = 1;
		$GLOBALS['_test_user_caps']       = array( 'fluent_forms_read' );

		// Test a write callback's in-body permission guard fires.
		$result = $this->invoke_execute_callback( 'fluent-forms/create-form', array( 'title' => 'X' ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'rest_forbidden', $result->get_error_code() );
	}

	public function test_callback_returns_invalid_input_for_missing_required_field() {
		$GLOBALS['_test_current_user_id'] = 1;
		$GLOBALS['_test_user_caps']       = array( 'fluent_forms_read', 'fluent_forms_write', 'fluent_forms_delete' );

		// create-form requires title.
		$result = $this->invoke_execute_callback( 'fluent-forms/create-form', array() );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code() );

		// update-form requires form_id.
		$result = $this->invoke_execute_callback( 'fluent-forms/update-form', array() );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code() );

		// delete-form requires form_id.
		$result = $this->invoke_execute_callback( 'fluent-forms/delete-form', array() );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code() );

		// update-submission-status requires submission_id + status.
		$result = $this->invoke_execute_callback( 'fluent-forms/update-submission-status', array( 'submission_id' => 1 ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code() );

		// update-submission-status rejects invalid status value.
		$result = $this->invoke_execute_callback( 'fluent-forms/update-submission-status', array( 'submission_id' => 1, 'status' => 'bogus' ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code() );

		// add-submission-note requires submission_id + content.
		$result = $this->invoke_execute_callback( 'fluent-forms/add-submission-note', array( 'submission_id' => 1 ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code() );

		// toggle-integration-status rejects invalid status enum value.
		$result = $this->invoke_execute_callback( 'fluent-forms/toggle-integration-status', array( 'integration_name' => 'mailchimp', 'status' => 'invalid' ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code() );

		// set-role-capability rejects non-FF capability.
		$result = $this->invoke_execute_callback( 'fluent-forms/set-role-capability', array( 'role' => 'administrator', 'capability' => 'manage_options', 'enabled' => true ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code() );

		// add-manager requires user_id + permissions.
		$result = $this->invoke_execute_callback( 'fluent-forms/add-manager', array( 'user_id' => 1 ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code() );

		// retry-scheduled-action requires action_id or action_ids.
		$result = $this->invoke_execute_callback( 'fluent-forms/retry-scheduled-action', array() );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code() );

		// import-form requires data.title.
		$result = $this->invoke_execute_callback( 'fluent-forms/import-form', array( 'data' => array() ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code() );

		// export-entries requires form_id + format enum.
		$result = $this->invoke_execute_callback( 'fluent-forms/export-entries', array( 'form_id' => 1 ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code() );

		// import-entries requires column_mapping.
		$result = $this->invoke_execute_callback( 'fluent-forms/import-entries', array( 'form_id' => 1 ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code() );
	}

	public function test_delete_callbacks_reject_when_required_id_is_missing() {
		$GLOBALS['_test_current_user_id'] = 1;
		$GLOBALS['_test_user_caps']       = array( 'fluent_forms_read', 'fluent_forms_write', 'fluent_forms_delete' );

		foreach ( array(
			'fluent-forms/delete-form',
			'fluent-forms/delete-submission',
			'fluent-forms/delete-submission-note',
			'fluent-forms/delete-submission-logs',
			'fluent-forms/delete-form-notification',
			'fluent-forms/delete-form-confirmation',
			'fluent-forms/delete-form-integration',
			'fluent-forms/remove-manager',
			'fluent-forms/reset-form-analytics',
		) as $slug ) {
			$result = $this->invoke_execute_callback( $slug, array() );
			$this->assertInstanceOf( WP_Error::class, $result, "{$slug} must return WP_Error when required id is missing." );
			$this->assertSame( 'ability_invalid_input', $result->get_error_code() );
		}
	}

	public function test_add_manager_normalizes_specific_forms_when_allowed_forms_empty() {
		// Mirror LegacyManagerScopes migration: has_specific_forms=true with empty
		// allowed_forms normalizes to false (per research §7.Q5 disposition (a)).
		$GLOBALS['_test_current_user_id'] = 1;
		$GLOBALS['_test_user_caps']       = array( 'fluent_forms_read', 'fluent_forms_write', 'fluent_forms_delete' );

		// get_user_by / update_user_meta / delete_user_meta stubbed in tests/stubs/wordpress-stubs.php.
		$result = $this->invoke_execute_callback( 'fluent-forms/add-manager', array(
			'user_id'            => 1,
			'permissions'        => array( 'fluentform_dashboard_access' ),
			'has_specific_forms' => true,
			'allowed_forms'      => array(),
		) );
		$this->assertIsArray( $result );
		$this->assertFalse( $result['has_specific_forms'], 'Empty allowed_forms with has_specific_forms=true should normalize to false.' );
	}

	public function test_delete_form_returns_plugin_missing_without_vendor_classes() {
		$GLOBALS['_test_current_user_id'] = 1;
		$GLOBALS['_test_user_caps']       = array( 'fluent_forms_read', 'fluent_forms_write', 'fluent_forms_delete' );

		if ( class_exists( '\\FluentForm\\App\\Models\\Form' ) ) {
			$this->markTestSkipped( 'Vendor class loaded; integration tests cover happy path.' );
		}

		// Stub $wpdb minimally so the call reaches the class_exists guard.
		global $wpdb;
		$wpdb = $this->createMockWpdb();
		$result = $this->invoke_execute_callback( 'fluent-forms/delete-form', array( 'form_id' => 1 ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'plugin_missing', $result->get_error_code() );
	}

	private function createMockWpdb() {
		return new class {
			public $prefix = 'wp_';
			public $insert_id = 0;
			public function prepare( $sql, ...$args ) { return $sql; }
			public function get_var( $sql, ...$args ) { return 0; }
			public function get_results( $sql, ...$args ) { return array(); }
			public function get_row( $sql, ...$args ) { return null; }
			public function get_col( $sql, ...$args ) { return array(); }
			public function insert( $table, $data, $format = null ) { return 1; }
			public function update( $table, $data, $where, $format = null, $where_format = null ) { return 1; }
			public function delete( $table, $where, $where_format = null ) { return 1; }
			public function query( $sql ) { return 0; }
			public function esc_like( $text ) { return $text; }
		};
	}
}
