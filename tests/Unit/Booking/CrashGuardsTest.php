<?php
/**
 * Unit tests — v1.4.0 Package 2 (Crash blockers) — FluentBooking portion.
 *
 * Covers fluent-booking/get-available-slots V10 signature alignment (F-BOOK-02).
 * Vendor TimeSlotService constructor signature is __construct(Calendar $c,
 * CalendarSlot $s) per installed source app/Services/TimeSlotService.php:18 —
 * the prior registrar passed (CalendarSlot, string $timezone) which produced a
 * PHP TypeError on every call. Registrar now looks up the parent Calendar via
 * the event's calendar_id and passes both objects.
 *
 * The fluent-booking/create-event try/catch around AvailabilityService is
 * verified live on helenawillow only — the registrar uses an anonymous closure
 * attached via add_action, which can't be triggered from unit mode (add_action
 * is a no-op stub there). Live re-test is the proof for that fix.
 *
 * @package Fluent_Abilities\Tests\Unit\Booking
 */

use PHPUnit\Framework\TestCase;

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) { return $text; }
}
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value ) { return $value; }
}
if ( ! function_exists( 'do_action' ) ) {
	function do_action( $hook, ...$args ) {}
}
if ( ! function_exists( 'wpFluent' ) ) {
	function wpFluent() { return new FluentBookingCrashGuardsWpFluentStub(); }
}

class FluentBookingCrashGuardsWpFluentStub {
	public function table( $name ) { return new FluentBookingCrashGuardsQueryStub(); }
}
class FluentBookingCrashGuardsQueryStub {
	public function where( ...$a ) { return $this; }
	public function whereIn( ...$a ) { return $this; }
	public function orderBy( ...$a ) { return $this; }
	public function select( ...$a ) { return $this; }
	public function get() { return array(); }
	public function first() { return null; }
	public function count() { return 0; }
}

// ---------------------------------------------------------------------------
// Vendor class stubs aliased into the FluentBooking namespace. Allows the
// callback's class_exists() guards + static method calls to reach pass and
// dispatch into our test stubs.
// ---------------------------------------------------------------------------

class FluentBookingCrashGuardsCalendarStub {
	public $id      = 7;
	public $user_id = 1;
	public static $next_find = null;
	public static function find( $id ) { return self::$next_find; }
}

class FluentBookingCrashGuardsCalendarSlotStub {
	public $id          = 100;
	public $calendar_id = 7;
	public $event_type  = 'single';
	public $duration    = 30;
	public static $next_find = null;
	public static function find( $id ) { return self::$next_find; }
}

class FluentBookingCrashGuardsTimeSlotServiceStub {
	public static $last_constructor_args = null;
	public static $get_dates_return      = array();
	public function __construct( $calendar, $calendarSlot ) {
		self::$last_constructor_args = array( $calendar, $calendarSlot );
	}
	public function getDates( $from, $to, $duration, $isDoing, $tz ) {
		return self::$get_dates_return;
	}
}

if ( ! class_exists( 'FluentBooking\\App\\Models\\Calendar', false ) ) {
	class_alias( 'FluentBookingCrashGuardsCalendarStub', 'FluentBooking\\App\\Models\\Calendar' );
}
if ( ! class_exists( 'FluentBooking\\App\\Models\\CalendarSlot', false ) ) {
	class_alias( 'FluentBookingCrashGuardsCalendarSlotStub', 'FluentBooking\\App\\Models\\CalendarSlot' );
}
if ( ! class_exists( 'FluentBooking\\App\\Services\\TimeSlotService', false ) ) {
	class_alias( 'FluentBookingCrashGuardsTimeSlotServiceStub', 'FluentBooking\\App\\Services\\TimeSlotService' );
}

require_once dirname( __DIR__, 2 ) . '/../includes/compat.php';
require_once dirname( __DIR__, 3 ) . '/includes/booking/abilities-slots.php';

class FluentBookingCrashGuardsTest extends TestCase {

	protected function setUp(): void {
		global $_wp_registered_abilities;
		$_wp_registered_abilities = array();
		update_option( 'fluent_abilities_enabled_modules', array( 'booking' ) );
		$GLOBALS['_test_current_user_id'] = 1;
		$GLOBALS['_test_user_caps']       = array( 'fluent_booking_read', 'fluent_booking_write', 'fluent_booking_delete' );

		FluentBookingCrashGuardsCalendarStub::$next_find             = null;
		FluentBookingCrashGuardsCalendarSlotStub::$next_find         = null;
		FluentBookingCrashGuardsTimeSlotServiceStub::$last_constructor_args = null;
		FluentBookingCrashGuardsTimeSlotServiceStub::$get_dates_return      = array();

		fluent_booking_register_slot_abilities();
	}

	protected function tearDown(): void {
		unset( $GLOBALS['_test_user_caps'], $GLOBALS['_test_current_user_id'] );
		delete_option( 'fluent_abilities_enabled_modules' );
	}

	public function test_get_available_slots_registers() {
		$abilities = wp_get_abilities();
		$this->assertArrayHasKey( 'fluent-booking/get-available-slots', $abilities );
	}

	public function test_get_available_slots_returns_wp_error_when_event_not_found() {
		FluentBookingCrashGuardsCalendarSlotStub::$next_find = null;
		$abilities = wp_get_abilities();
		$cb        = $abilities['fluent-booking/get-available-slots']['execute_callback'];
		$result    = $cb( array(
			'event_id'   => 999,
			'start_date' => '2026-06-01',
			'end_date'   => '2026-06-07',
			'timezone'   => 'UTC',
		) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'not_found', $result->get_error_code() );
	}

	public function test_get_available_slots_returns_wp_error_when_parent_calendar_missing() {
		$slot                  = new FluentBookingCrashGuardsCalendarSlotStub();
		$slot->calendar_id     = 999;
		FluentBookingCrashGuardsCalendarSlotStub::$next_find = $slot;
		FluentBookingCrashGuardsCalendarStub::$next_find     = null;

		$abilities = wp_get_abilities();
		$cb        = $abilities['fluent-booking/get-available-slots']['execute_callback'];
		$result    = $cb( array(
			'event_id'   => 100,
			'start_date' => '2026-06-01',
			'end_date'   => '2026-06-07',
			'timezone'   => 'UTC',
		) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'not_found', $result->get_error_code() );
	}

	public function test_get_available_slots_passes_calendar_then_slot_to_constructor() {
		// V10 signature alignment: TimeSlotService::__construct(Calendar, CalendarSlot).
		$slot                  = new FluentBookingCrashGuardsCalendarSlotStub();
		$slot->id              = 100;
		$slot->calendar_id     = 7;
		$slot->event_type      = 'single';
		$slot->duration        = 30;
		$calendar              = new FluentBookingCrashGuardsCalendarStub();
		$calendar->id          = 7;
		FluentBookingCrashGuardsCalendarSlotStub::$next_find = $slot;
		FluentBookingCrashGuardsCalendarStub::$next_find     = $calendar;
		FluentBookingCrashGuardsTimeSlotServiceStub::$get_dates_return = array(
			'2026-06-01' => array( array( 'start' => '2026-06-01 09:00:00', 'end' => '2026-06-01 09:30:00' ) ),
		);

		$abilities = wp_get_abilities();
		$cb        = $abilities['fluent-booking/get-available-slots']['execute_callback'];
		$result    = $cb( array(
			'event_id'   => 100,
			'start_date' => '2026-06-01',
			'end_date'   => '2026-06-07',
			'timezone'   => 'UTC',
		) );

		$this->assertNotInstanceOf( WP_Error::class, $result );
		$this->assertNotNull( FluentBookingCrashGuardsTimeSlotServiceStub::$last_constructor_args );
		$args = FluentBookingCrashGuardsTimeSlotServiceStub::$last_constructor_args;
		$this->assertSame( $calendar, $args[0], 'V10 alignment: first ctor arg must be parent Calendar' );
		$this->assertSame( $slot, $args[1], 'V10 alignment: second ctor arg must be CalendarSlot' );
		$this->assertSame( 100, $result['event_id'] );
		$this->assertSame( 'single', $result['event_type'] );
		$this->assertSame( 30, $result['slot_minutes'] );
	}
}
