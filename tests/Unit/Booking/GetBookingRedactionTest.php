<?php
/**
 * Security regression test for #140 — fluent-booking/get-booking must not
 * disclose privileged host-only fields (Zoom "start as host" token/link and the
 * host's Google Calendar links) through its unscoped read output, while still
 * returning attendee-safe fields (join_url, meeting password, booking fields).
 *
 * Runs in a separate process with a configurable wpFluent stub so the real
 * get-booking callback is exercised end-to-end against a booking whose meta /
 * location_details carry the privileged fields.
 *
 * @package Fluent_Abilities\Tests\Unit\Booking
 */

use PHPUnit\Framework\TestCase;

// Configurable wpFluent stub: returns the canned booking row + meta rows the
// get-booking callback reads. Table-aware so each query gets the right fixture.
if ( ! class_exists( 'FAB140QueryStub' ) ) {
	class FAB140QueryStub {
		private $table;
		public function __construct( $t ) { $this->table = $t; }
		public function where( ...$a ) { return $this; }
		public function whereIn( ...$a ) { return $this; }
		public function orderBy( ...$a ) { return $this; }
		public function select( ...$a ) { return $this; }
		public function offset( $n ) { return $this; }
		public function limit( $n ) { return $this; }
		public function count() { return 0; }
		public function first() {
			if ( 'fcal_bookings' === $this->table ) { return $GLOBALS['_fab140_booking'] ?? null; }
			if ( 'fcal_calendar_events' === $this->table ) { return (object) array( 'id' => 3, 'title' => 'Consult' ); }
			return null;
		}
		public function get() {
			if ( 'fcal_booking_meta' === $this->table ) { return $GLOBALS['_fab140_meta'] ?? array(); }
			return array(); // fcal_booking_hosts empty -> no get_userdata needed
		}
	}
	class FAB140WpFluentStub {
		public function table( $t ) { return new FAB140QueryStub( $t ); }
	}
}
if ( ! function_exists( 'wpFluent' ) ) {
	function wpFluent() { return new FAB140WpFluentStub(); }
}

class GetBookingRedactionTest extends TestCase {

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_get_booking_redacts_host_secrets_but_keeps_attendee_fields() {
		ini_set( 'log_errors', '0' );
		ini_set( 'error_log', '/dev/null' );

		// Booking row with a location_details payload carrying a host-only start link.
		$GLOBALS['_fab140_booking'] = (object) array(
			'id'               => 550,
			'hash'             => 'abc123',
			'calendar_id'      => 7,
			'event_id'         => 3,
			'group_id'         => null,
			'host_user_id'     => 1,
			'person_user_id'   => null,
			'person_contact_id'=> null,
			'person_time_zone' => 'UTC',
			'first_name'       => 'Attendee',
			'last_name'        => 'Person',
			'email'            => 'attendee@example.test',
			'phone'            => null,
			'country'          => null,
			'message'          => null,
			'start_time'       => '2026-06-01 10:00:00',
			'end_time'         => '2026-06-01 10:30:00',
			'slot_minutes'     => 30,
			'status'           => 'scheduled',
			'booking_type'     => null,
			'payment_status'   => null,
			'source'           => 'web',
			'ip_address'       => null,
			'location_details' => serialize( array(
				'type'                       => 'zoom',
				'join_url'                   => 'https://zoom.us/j/PUBLIC',
				'online_platform_start_link' => 'https://zoom.us/s/PUBLIC?zak=HOSTTOKEN',
			) ),
			'created_at'       => '2026-05-01 09:00:00',
			'updated_at'       => '2026-05-01 09:00:00',
		);

		// Meta rows carrying the Zoom host token and host calendar links.
		$GLOBALS['_fab140_meta'] = array(
			(object) array( 'meta_key' => '__zoom_meeting_details', 'value' => serialize( array(
				'id'        => '123',
				'join_url'  => 'https://zoom.us/j/PUBLIC',
				'password'  => 'attendeepw',
				'start_url' => 'https://zoom.us/s/123?zak=HOSTTOKEN',
				'zak'       => 'HOSTTOKEN',
			) ) ),
			(object) array( 'meta_key' => '__google_calendar_event', 'value' => serialize( array(
				'event_id'           => 'evt_abc',
				'remote_link'        => 'https://calendar.google.com/event?eid=SECRET',
				'remote_calendar_id' => 'helena@willow.se',
			) ) ),
		);

		require_once dirname( __DIR__, 3 ) . '/includes/booking/abilities.php';

		global $_wp_registered_abilities;
		$_wp_registered_abilities = array();
		update_option( 'fluent_abilities_enabled_modules', array( 'booking' ) );
		$GLOBALS['_test_current_user_id'] = 1;
		$GLOBALS['_test_user_caps']       = array( 'manage_options' );

		do_action( 'wp_abilities_api_init' );

		$abilities = wp_get_abilities();
		$this->assertArrayHasKey( 'fluent-booking/get-booking', $abilities, 'get-booking must be registered' );
		$cb     = $abilities['fluent-booking/get-booking']['execute_callback'];
		$result = $cb( array( 'id' => 550 ) );

		$this->assertIsArray( $result, 'get-booking should return the booking array' );
		$this->assertSame( 550, $result['id'] );

		// ── Privileged host-only fields must be gone, at any depth ──
		$json = wp_json_encode( $result );
		foreach ( array( 'start_url', 'online_platform_start_link', 'remote_link', 'remote_calendar_id', 'HOSTTOKEN' ) as $leak ) {
			$this->assertStringNotContainsString( $leak, $json, "get-booking output must not disclose '{$leak}' (#140)" );
		}
		$this->assertArrayNotHasKey( 'start_url', $result['meta']['__zoom_meeting_details'] );
		$this->assertArrayNotHasKey( 'zak', $result['meta']['__zoom_meeting_details'] );
		$this->assertArrayNotHasKey( 'remote_link', $result['meta']['__google_calendar_event'] );
		$this->assertArrayNotHasKey( 'remote_calendar_id', $result['meta']['__google_calendar_event'] );
		$this->assertArrayNotHasKey( 'online_platform_start_link', $result['location_details'] );

		// ── Attendee-safe fields must survive ──
		$this->assertSame( 'https://zoom.us/j/PUBLIC', $result['meta']['__zoom_meeting_details']['join_url'] );
		$this->assertSame( 'attendeepw', $result['meta']['__zoom_meeting_details']['password'] );
		$this->assertSame( 'https://zoom.us/j/PUBLIC', $result['location_details']['join_url'] );
		$this->assertSame( 'scheduled', $result['status'] );
		$this->assertSame( 'attendee@example.test', $result['email'] );
	}
}
