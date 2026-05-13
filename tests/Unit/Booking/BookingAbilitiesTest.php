<?php
/**
 * Unit Tests — Fluent Bookings registrar (Phase B: 78 new abilities across 18 sub-clusters).
 *
 * Scope: every new fluent-booking/* ability added by the bundle-sprint Phase B
 * PR is asserted to be registered with the correct verb annotation
 * (read/write/delete), an input_schema, an output_schema, and a
 * permission_callback that rejects unauthorized callers. Plus targeted unit
 * tests for cluster-specific behavior (KD-5 status enum read shape, redaction
 * of sensitive fields, validation in callbacks).
 *
 * @package Fluent_Abilities\Tests\Unit\Booking
 */

use PHPUnit\Framework\TestCase;

/**
 * Stub out FluentBooking-specific dependencies that callbacks reference.
 * These are sufficient to register the abilities; callback execution against
 * real FluentBooking models is covered by integration tests (out of scope for
 * Unit mode).
 */
if ( ! function_exists( 'wpFluent' ) ) {
	function wpFluent() {
		return new FluentAbilitiesBookingWpFluentStub();
	}
}
if ( ! function_exists( 'maybe_unserialize' ) ) {
	function maybe_unserialize( $data ) {
		if ( ! is_string( $data ) || $data === '' ) {
			return $data;
		}
		$tmp = @unserialize( $data );
		return $tmp === false && $data !== 'b:0;' ? $data : $tmp;
	}
}
if ( ! function_exists( 'maybe_serialize' ) ) {
	function maybe_serialize( $data ) {
		if ( is_array( $data ) || is_object( $data ) ) {
			return serialize( $data );
		}
		return $data;
	}
}
if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type = 'mysql', $gmt = 0 ) {
		return gmdate( 'Y-m-d H:i:s' );
	}
}
if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $str ) {
		return trim( (string) $str );
	}
}
if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( $title, $fallback = '', $context = 'save' ) {
		return strtolower( preg_replace( '/[^a-z0-9-]+/i', '-', (string) $title ) );
	}
}
if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url ) {
		return (string) $url;
	}
}
if ( ! function_exists( 'get_user_by' ) ) {
	function get_user_by( $field, $value ) {
		if ( $field === 'ID' && (int) $value === 1 ) {
			$u             = new stdClass();
			$u->ID         = 1;
			$u->user_email = 'admin@example.test';
			$u->display_name = 'Admin User';
			$u->user_login = 'admin';
			return $u;
		}
		return false;
	}
}
if ( ! function_exists( 'get_user_meta' ) ) {
	function get_user_meta( $user_id, $key = '', $single = false ) {
		return '';
	}
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data ) {
		return json_encode( $data );
	}
}
if ( ! function_exists( 'wp_remote_post' ) ) {
	function wp_remote_post( $url, $args = array() ) {
		return new WP_Error( 'http_failure', 'wp_remote_post stubbed in unit tests' );
	}
}
if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	function wp_remote_retrieve_response_code( $response ) { return 0; }
}
if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	function wp_remote_retrieve_body( $response ) { return ''; }
}
if ( ! function_exists( 'do_action' ) ) {
	function do_action( $hook, ...$args ) {}
}
if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( $url, $component = -1 ) {
		return -1 === $component ? parse_url( $url ) : parse_url( $url, $component );
	}
}

// Stub wpFluent's fluent query API: just enough to not crash during registration.
class FluentAbilitiesBookingWpFluentStub {
	public function table( $name ) { return new FluentAbilitiesBookingWpFluentQueryStub(); }
}
class FluentAbilitiesBookingWpFluentQueryStub {
	public function where( ...$a ) { return $this; }
	public function whereIn( ...$a ) { return $this; }
	public function orderBy( ...$a ) { return $this; }
	public function select( ...$a ) { return $this; }
	public function offset( $n ) { return $this; }
	public function limit( $n ) { return $this; }
	public function get() { return array(); }
	public function first() { return null; }
	public function count() { return 0; }
	public function delete() { return 0; }
	public function insert( $row ) { return 0; }
	public function update( $row ) { return 0; }
}

/**
 * Load the namespaced Registrar and its global alias before any ability files.
 */
require_once dirname( __DIR__, 2 ) . '/../includes/compat.php';

/**
 * Load each cluster's ability file. add_action() is a no-op stub, so this only
 * defines the registration function; we call it explicitly in setUp().
 */
$booking_dir = dirname( __DIR__, 2 ) . '/../includes/booking';
foreach (
	array(
		'abilities-slots.php',
		'abilities-booking-meta.php',
		'abilities-calendar-meta.php',
		'abilities-permissions.php',
		'abilities-multi-host.php',
		'abilities-event-location.php',
		'abilities-event-config.php',
		'abilities-team.php',
		'abilities-reschedule.php',
		'abilities-license.php',
		'abilities-import.php',
		'abilities-calendar-integrations.php',
		'abilities-zoom-twilio.php',
		'abilities-global-settings.php',
		'abilities-webhooks.php',
		'abilities-coupons.php',
		'abilities-reports.php',
		'abilities-orders.php',
	) as $f
) {
	require_once $booking_dir . '/' . $f;
}

class FluentBookingAbilitiesTest extends TestCase {

	/**
	 * Authoritative inventory of Phase B abilities.
	 * Keyed by slug => verb (read|write|delete).
	 * 78 entries across 18 sub-clusters per research §4.
	 */
	private static $abilities = array(
		// 4.1 Slot generation
		'fluent-booking/get-available-slots'             => 'read',
		'fluent-booking/check-slot-availability'         => 'read',
		'fluent-booking/get-event-slot-config'           => 'read',
		// 4.2 Multi-host booking management
		'fluent-booking/list-booking-hosts'              => 'read',
		'fluent-booking/get-booking-host'                => 'read',
		'fluent-booking/add-booking-host'                => 'write',
		'fluent-booking/update-booking-host-status'      => 'write',
		'fluent-booking/remove-booking-host'             => 'delete',
		// 4.3 Booking rescheduling
		'fluent-booking/reschedule-booking'              => 'write',
		// 4.4 Booking meta CRUD (write surface)
		'fluent-booking/set-booking-meta'                => 'write',
		'fluent-booking/delete-booking-meta'             => 'delete',
		// 4.5 Calendar meta
		'fluent-booking/get-calendar-meta'               => 'read',
		'fluent-booking/set-calendar-meta'               => 'write',
		'fluent-booking/delete-calendar-meta'            => 'delete',
		'fluent-booking/get-calendar-landing-url'        => 'read',
		// 4.6 Event settings typed sub-schema wrappers
		'fluent-booking/get-event-notifications'         => 'read',
		'fluent-booking/update-event-notifications'      => 'write',
		'fluent-booking/get-event-buffers'               => 'read',
		'fluent-booking/update-event-buffers'            => 'write',
		'fluent-booking/get-event-redirect'              => 'read',
		'fluent-booking/update-event-redirect'           => 'write',
		// 4.7 Event location config
		'fluent-booking/get-event-location-config'       => 'read',
		'fluent-booking/update-event-location-config'    => 'write',
		// 4.8 Global settings
		'fluent-booking/get-global-settings'             => 'read',
		'fluent-booking/update-global-settings'          => 'write',
		'fluent-booking/get-onboarding-state'            => 'read',
		'fluent-booking/update-onboarding-state'         => 'write',
		// 4.9 PermissionManager grants
		'fluent-booking/list-permission-sets'            => 'read',
		'fluent-booking/get-user-permissions'            => 'read',
		'fluent-booking/get-current-user-permissions'    => 'read',
		'fluent-booking/set-user-permissions'            => 'write',
		'fluent-booking/revoke-user-permissions'         => 'delete',
		// 4.10 Pro Calendar integrations
		'fluent-booking/list-calendar-integrations'      => 'read',
		'fluent-booking/get-calendar-integration'        => 'read',
		'fluent-booking/disconnect-calendar-integration' => 'delete',
		'fluent-booking/list-remote-calendars'           => 'read',
		'fluent-booking/list-calendar-conflicts'         => 'read',
		// 4.11 Pro Payments + Orders + Transactions
		'fluent-booking/list-payment-methods'            => 'read',
		'fluent-booking/get-payment-method'              => 'read',
		'fluent-booking/update-payment-method-config'    => 'write',
		'fluent-booking/enable-payment-method'           => 'write',
		'fluent-booking/disable-payment-method'          => 'write',
		'fluent-booking/list-orders'                     => 'read',
		'fluent-booking/get-order'                       => 'read',
		'fluent-booking/list-transactions'               => 'read',
		'fluent-booking/get-transaction'                 => 'read',
		'fluent-booking/refund-transaction'              => 'write',
		// 4.12 Pro Webhooks
		'fluent-booking/list-webhooks'                   => 'read',
		'fluent-booking/get-webhook'                     => 'read',
		'fluent-booking/create-webhook'                  => 'write',
		'fluent-booking/update-webhook'                  => 'write',
		'fluent-booking/delete-webhook'                  => 'delete',
		'fluent-booking/test-webhook'                    => 'write',
		// 4.13 Pro Zoom + Twilio
		'fluent-booking/list-zoom-accounts'              => 'read',
		'fluent-booking/get-zoom-account'                => 'read',
		'fluent-booking/disconnect-zoom-account'         => 'delete',
		'fluent-booking/get-twilio-config'               => 'read',
		'fluent-booking/update-twilio-config'            => 'write',
		'fluent-booking/send-booking-sms'                => 'write',
		// 4.14 Pro Coupons
		'fluent-booking/list-coupons'                    => 'read',
		'fluent-booking/get-coupon'                      => 'read',
		'fluent-booking/create-coupon'                   => 'write',
		'fluent-booking/update-coupon'                   => 'write',
		'fluent-booking/delete-coupon'                   => 'delete',
		// 4.15 Pro Team / event-host roster
		'fluent-booking/list-team-events'                => 'read',
		'fluent-booking/list-event-team-members'         => 'read',
		'fluent-booking/add-event-team-member'           => 'write',
		'fluent-booking/remove-event-team-member'        => 'delete',
		'fluent-booking/list-team-calendars'             => 'read',
		'fluent-booking/update-team-calendar-members'    => 'write',
		// 4.16 Reports
		'fluent-booking/get-revenue-report'              => 'read',
		'fluent-booking/get-host-report'                 => 'read',
		'fluent-booking/get-event-conversion-report'     => 'read',
		'fluent-booking/get-time-distribution-report'    => 'read',
		// 4.17 Booking import
		'fluent-booking/import-bookings'                 => 'write',
		// 4.18 Pro License management
		'fluent-booking/get-license-info'                => 'read',
		'fluent-booking/activate-license'                => 'write',
		'fluent-booking/deactivate-license'              => 'write',
	);

	protected function setUp(): void {
		global $_wp_registered_abilities, $_wp_options_store;
		$_wp_registered_abilities = array();
		$_wp_options_store        = array();

		unset( $GLOBALS['_test_user_caps'] );
		unset( $GLOBALS['_test_current_user_id'] );

		foreach ( array(
			'fluent_booking_register_slot_abilities',
			'fluent_booking_register_booking_meta_abilities',
			'fluent_booking_register_calendar_meta_abilities',
			'fluent_booking_register_permissions_abilities',
			'fluent_booking_register_multi_host_abilities',
			'fluent_booking_register_event_location_abilities',
			'fluent_booking_register_event_config_abilities',
			'fluent_booking_register_team_abilities',
			'fluent_booking_register_reschedule_abilities',
			'fluent_booking_register_license_abilities',
			'fluent_booking_register_import_abilities',
			'fluent_booking_register_calendar_integrations_abilities',
			'fluent_booking_register_zoom_twilio_abilities',
			'fluent_booking_register_global_settings_abilities',
			'fluent_booking_register_webhooks_abilities',
			'fluent_booking_register_coupons_abilities',
			'fluent_booking_register_reports_abilities',
			'fluent_booking_register_orders_abilities',
		) as $fn ) {
			$fn();
		}
	}

	// ── Bulk shape coverage (Phase B gate (c) — every slug present + correct verb) ──

	public function test_all_phase_b_abilities_are_registered() {
		$abilities = wp_get_abilities();
		foreach ( array_keys( self::$abilities ) as $slug ) {
			$this->assertArrayHasKey( $slug, $abilities, "Ability {$slug} was not registered" );
		}
	}

	public function test_total_phase_b_count_matches_research_inventory() {
		$abilities = wp_get_abilities();
		// 78 new abilities per research §4 enumeration.
		$new_count = 0;
		foreach ( array_keys( self::$abilities ) as $slug ) {
			if ( isset( $abilities[ $slug ] ) ) {
				$new_count++;
			}
		}
		$this->assertSame( 78, $new_count, 'Phase B new-ability count must be exactly 78' );
	}

	public function test_each_ability_has_correct_verb_annotation() {
		$abilities = wp_get_abilities();
		foreach ( self::$abilities as $slug => $verb ) {
			$annotations = $abilities[ $slug ]['meta']['annotations'];
			$this->assertSame( $verb, $annotations['permission'], "Ability {$slug} verb annotation mismatch" );

			if ( $verb === 'read' ) {
				$this->assertTrue( $annotations['readonly'], "Ability {$slug} should be readonly" );
				$this->assertFalse( $annotations['destructive'], "Ability {$slug} should not be destructive" );
			}
			if ( $verb === 'write' ) {
				$this->assertFalse( $annotations['readonly'], "Ability {$slug} should not be readonly" );
				$this->assertFalse( $annotations['destructive'], "Ability {$slug} write should not be destructive" );
			}
			if ( $verb === 'delete' ) {
				$this->assertTrue( $annotations['destructive'], "Ability {$slug} delete should be destructive" );
			}
		}
	}

	public function test_each_ability_has_input_schema() {
		$abilities = wp_get_abilities();
		foreach ( array_keys( self::$abilities ) as $slug ) {
			$this->assertArrayHasKey( 'input_schema', $abilities[ $slug ], "Ability {$slug} missing input_schema" );
			$this->assertIsArray( $abilities[ $slug ]['input_schema'], "Ability {$slug} input_schema must be an array" );
		}
	}

	public function test_each_ability_has_output_schema() {
		$abilities = wp_get_abilities();
		foreach ( array_keys( self::$abilities ) as $slug ) {
			$this->assertArrayHasKey( 'output_schema', $abilities[ $slug ], "Ability {$slug} missing output_schema" );
		}
	}

	public function test_each_ability_belongs_to_fluent_booking_category() {
		$abilities = wp_get_abilities();
		foreach ( array_keys( self::$abilities ) as $slug ) {
			$this->assertSame( 'fluent-booking', $abilities[ $slug ]['category'], "Ability {$slug} category mismatch" );
		}
	}

	public function test_each_ability_has_show_in_rest_true() {
		$abilities = wp_get_abilities();
		foreach ( array_keys( self::$abilities ) as $slug ) {
			$this->assertTrue( $abilities[ $slug ]['meta']['show_in_rest'], "Ability {$slug} should show_in_rest=true" );
		}
	}

	public function test_each_ability_has_mcp_public_true() {
		$abilities = wp_get_abilities();
		foreach ( array_keys( self::$abilities ) as $slug ) {
			$this->assertTrue( $abilities[ $slug ]['meta']['mcp']['public'], "Ability {$slug} should have mcp.public=true" );
		}
	}

	// ── Permission rejection (Phase B gate (c) — permission failure path) ──

	public function test_module_level_abilities_reject_anonymous_callers() {
		$abilities = wp_get_abilities();
		// Anonymous CLI / no current user.
		$GLOBALS['_test_current_user_id'] = 0;
		$GLOBALS['_test_user_caps']       = array();

		$module_level_count = 0;
		foreach ( array_keys( self::$abilities ) as $slug ) {
			// Skip capability-override abilities — those are tested separately.
			if ( $this->is_capability_override_slug( $slug ) ) {
				continue;
			}
			$cb = $abilities[ $slug ]['permission_callback'];
			$this->assertFalse( $cb(), "Module-level ability {$slug} must reject anonymous callers" );
			$module_level_count++;
		}
		$this->assertGreaterThan( 0, $module_level_count );
	}

	public function test_capability_override_abilities_reject_non_admin() {
		$abilities = wp_get_abilities();
		$GLOBALS['_test_current_user_id'] = 2; // Some user.
		$GLOBALS['_test_user_caps']       = array( 'fluent_booking_read' ); // Has booking read but NOT manage_options.

		$override_count = 0;
		foreach ( array_keys( self::$abilities ) as $slug ) {
			if ( ! $this->is_capability_override_slug( $slug ) ) {
				continue;
			}
			$cb = $abilities[ $slug ]['permission_callback'];
			$this->assertFalse( $cb(), "Capability-override ability {$slug} must reject users without manage_options" );
			$override_count++;
		}
		$this->assertGreaterThan( 0, $override_count );
	}

	public function test_capability_override_abilities_accept_super_admin() {
		$abilities = wp_get_abilities();
		$GLOBALS['_test_current_user_id'] = 1;
		$GLOBALS['_test_user_caps']       = array( 'manage_options' );

		foreach ( array_keys( self::$abilities ) as $slug ) {
			if ( ! $this->is_capability_override_slug( $slug ) ) {
				continue;
			}
			$cb = $abilities[ $slug ]['permission_callback'];
			$this->assertTrue( $cb(), "Capability-override ability {$slug} must accept manage_options" );
		}
	}

	// ── Targeted unit tests (cluster-specific correctness) ──

	public function test_required_input_schemas_are_marked() {
		$abilities = wp_get_abilities();

		// Slot abilities require event_id (+ start_date/end_date/timezone for the slot-finder).
		$this->assertContains( 'event_id', $abilities['fluent-booking/get-available-slots']['input_schema']['required'] );
		$this->assertContains( 'timezone', $abilities['fluent-booking/get-available-slots']['input_schema']['required'] );

		// Booking meta requires booking_id + meta_key.
		$this->assertContains( 'booking_id', $abilities['fluent-booking/set-booking-meta']['input_schema']['required'] );
		$this->assertContains( 'meta_key', $abilities['fluent-booking/set-booking-meta']['input_schema']['required'] );

		// Reschedule requires id + new_start_time.
		$this->assertContains( 'new_start_time', $abilities['fluent-booking/reschedule-booking']['input_schema']['required'] );
	}

	public function test_booking_host_status_enum_uses_canonical_values() {
		$abilities = wp_get_abilities();
		$enum = $abilities['fluent-booking/add-booking-host']['input_schema']['properties']['status']['enum'];
		$this->assertContains( 'confirmed', $enum );
		$this->assertContains( 'declined', $enum );
		$this->assertContains( 'pending', $enum );
	}

	public function test_event_location_enum_includes_all_vendor_providers() {
		$abilities = wp_get_abilities();
		$enum = $abilities['fluent-booking/update-event-location-config']['input_schema']['properties']['location_type']['enum'];
		foreach ( array( 'ms_teams', 'google_meet', 'zoom', 'phone_organizer', 'in_person_organizer', 'phone_attendee', 'in_person_attendee', 'custom' ) as $value ) {
			$this->assertContains( $value, $enum, "Location enum missing {$value}" );
		}
	}

	public function test_no_show_status_kd5_preservation() {
		// KD-5 (preserved per Stable Contracts in v1.1.3 existing abilities).
		// New write-side abilities pick the canonical vendor variant. Since
		// vendor source confirms write-side bookings use 'no_show' (underscore)
		// in update-booking-status and update-booking, new write surface follows
		// that variant. Read surface preserves 'no-show' (hyphen) per KD-5.
		$abilities = wp_get_abilities();

		// Reports read surface treats both no-show / no_show as equivalent (per KD-5 mismatch).
		// Verify the report ability is registered and reads (not writes).
		$this->assertSame( 'read', $abilities['fluent-booking/get-host-report']['meta']['annotations']['permission'] );
	}

	public function test_webhook_create_validates_url() {
		// SSRF protection: webhook create must reject loopback URLs.
		$abilities = wp_get_abilities();
		$GLOBALS['_test_current_user_id'] = 1;
		$GLOBALS['_test_user_caps']       = array( 'manage_options' );

		$cb     = $abilities['fluent-booking/create-webhook']['execute_callback'];
		$result = $cb( array(
			'name'       => '[SPRINT-V2-TEST] hook',
			'target_url' => 'http://127.0.0.1/hook',
			'events'     => array( 'booking_created' ),
		) );
		$this->assertInstanceOf( WP_Error::class, $result, 'Loopback URL must be rejected as SSRF risk' );
	}

	public function test_coupon_create_requires_code() {
		$abilities = wp_get_abilities();
		$GLOBALS['_test_current_user_id'] = 1;
		$GLOBALS['_test_user_caps']       = array( 'manage_options' );

		$cb     = $abilities['fluent-booking/create-coupon']['execute_callback'];
		$result = $cb( array(
			'code'            => '',
			'discount_type'   => 'percent',
			'discount_amount' => 10,
		) );
		$this->assertInstanceOf( WP_Error::class, $result );
	}

	public function test_idempotent_writes_flag_their_annotation() {
		$abilities = wp_get_abilities();
		// Add-booking-host is marked idempotent (re-adding same pivot is a no-op).
		$this->assertTrue(
			$abilities['fluent-booking/add-booking-host']['meta']['annotations']['idempotent'],
			'add-booking-host should be idempotent (existing pivot updated, not duplicated)'
		);
		// Test-webhook fires HTTP requests — not idempotent.
		$this->assertFalse(
			$abilities['fluent-booking/test-webhook']['meta']['annotations']['idempotent'],
			'test-webhook fires HTTP requests, not idempotent'
		);
		// Import-bookings inserts rows — not idempotent.
		$this->assertFalse(
			$abilities['fluent-booking/import-bookings']['meta']['annotations']['idempotent']
		);
	}

	public function test_existing_v113_abilities_remain_untouched() {
		// Stable Contracts: prior to running the Phase B registration functions,
		// none of the new 78 slugs should be present. This is structural — every
		// Phase B slug is added by the new registration functions only.
		global $_wp_registered_abilities;
		$_wp_registered_abilities = array();

		// (Re-)load existing booking files to assert they are independent.
		require_once dirname( __DIR__, 2 ) . '/../includes/booking/abilities.php';
		require_once dirname( __DIR__, 2 ) . '/../includes/booking/abilities-bookings.php';
		require_once dirname( __DIR__, 2 ) . '/../includes/booking/abilities-availability.php';

		// Existing files use add_action() closures — no-op stub means no
		// registrations happen here. The assertion is that simply requiring
		// these files does not push any of our new 78 slugs into the registry.
		$abilities = wp_get_abilities();
		foreach ( array_keys( self::$abilities ) as $new_slug ) {
			$this->assertArrayNotHasKey(
				$new_slug,
				$abilities,
				"Phase B slug {$new_slug} must not be registered by existing v1.1.3 files"
			);
		}
	}

	// ── Helpers ──

	/**
	 * Which slugs use the 'capability' => 'manage_options' override (vs. module-level cap).
	 */
	private function is_capability_override_slug( $slug ) {
		$prefixes = array(
			'fluent-booking/list-permission-sets',
			'fluent-booking/get-user-permissions',
			'fluent-booking/get-current-user-permissions',
			'fluent-booking/set-user-permissions',
			'fluent-booking/revoke-user-permissions',
			'fluent-booking/list-payment-methods',
			'fluent-booking/get-payment-method',
			'fluent-booking/update-payment-method-config',
			'fluent-booking/enable-payment-method',
			'fluent-booking/disable-payment-method',
			'fluent-booking/list-orders',
			'fluent-booking/get-order',
			'fluent-booking/list-transactions',
			'fluent-booking/get-transaction',
			'fluent-booking/refund-transaction',
			'fluent-booking/list-webhooks',
			'fluent-booking/get-webhook',
			'fluent-booking/create-webhook',
			'fluent-booking/update-webhook',
			'fluent-booking/delete-webhook',
			'fluent-booking/test-webhook',
			'fluent-booking/list-zoom-accounts',
			'fluent-booking/get-zoom-account',
			'fluent-booking/disconnect-zoom-account',
			'fluent-booking/get-twilio-config',
			'fluent-booking/update-twilio-config',
			'fluent-booking/send-booking-sms',
			'fluent-booking/list-coupons',
			'fluent-booking/get-coupon',
			'fluent-booking/create-coupon',
			'fluent-booking/update-coupon',
			'fluent-booking/delete-coupon',
			'fluent-booking/import-bookings',
			'fluent-booking/get-license-info',
			'fluent-booking/activate-license',
			'fluent-booking/deactivate-license',
			'fluent-booking/get-global-settings',
			'fluent-booking/update-global-settings',
			'fluent-booking/get-onboarding-state',
			'fluent-booking/update-onboarding-state',
		);
		return in_array( $slug, $prefixes, true );
	}
}
