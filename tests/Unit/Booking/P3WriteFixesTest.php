<?php
/**
 * Unit tests — v1.4.0 Package 3 — Booking write fixes.
 *
 * Covers:
 *  - V9 returned-id (P-N, F-BOOK-04 / F-COM-05): the returned activity_id /
 *    message_id is the real persisted id, not the boolean-as-int 1.
 *  - Package 3b duplicate-write (ledger Addendum 6): add-booking-note must
 *    write exactly ONE fcal_booking_activity row — routed through the vendor
 *    canonical BookingActivity model, with no second write path. Previously
 *    it did both a direct wpFluent insert AND
 *    do_action('fluent_booking/log_booking_note'), whose vendor LogHandler
 *    also persists a row (2 rows per call).
 *
 * @package Fluent_Abilities\Tests\Unit\Booking
 */

use PHPUnit\Framework\TestCase;

class P3WriteFixesTest extends TestCase {

	// ── Source-level: add-booking-note single canonical write ────────────────

	public function test_add_booking_note_routes_through_canonical_booking_activity_model() {
		$file = dirname( __DIR__, 3 ) . '/includes/booking/abilities-bookings.php';
		$src  = file_get_contents( $file );

		$this->assertStringContainsString(
			'\\FluentBooking\\App\\Models\\BookingActivity::create(',
			$src,
			'add-booking-note must write via the vendor canonical BookingActivity model (single write path)'
		);
	}

	public function test_add_booking_note_has_no_duplicate_write_path() {
		$file = dirname( __DIR__, 3 ) . '/includes/booking/abilities-bookings.php';
		$src  = file_get_contents( $file );

		// The do_action triggered the vendor LogHandler which ALSO persisted a
		// BookingActivity row → duplicate. It must be gone.
		$this->assertStringNotContainsString(
			"do_action( 'fluent_booking/log_booking_note'",
			$src,
			'add-booking-note must NOT fire fluent_booking/log_booking_note — the vendor LogHandler double-writes the row'
		);

		// And there must be no direct wpFluent write to fcal_booking_activity
		// alongside the canonical model write.
		$this->assertStringNotContainsString(
			"'fcal_booking_activity' )->insert",
			$src,
			'add-booking-note must NOT direct-insert into fcal_booking_activity (canonical model is the single write path)'
		);
	}

	public function test_add_booking_note_output_schema_declares_activity_id_as_integer() {
		// Verify output_schema declares activity_id as integer via source read
		// (avoids wpFluent stub collision with other test files in same process).
		$file = dirname( __DIR__, 3 ) . '/includes/booking/abilities-bookings.php';
		$src  = file_get_contents( $file );

		// output_schema for add-booking-note must include activity_id as integer.
		$this->assertStringContainsString(
			"'activity_id' => array( 'type' => 'integer' )",
			$src,
			'output_schema must declare activity_id as integer type'
		);
	}

	// ── Source-level V9 guard: send-message ──────────────────────────────────

	public function test_send_message_uses_insertGetId_not_insert() {
		$file = dirname( __DIR__, 3 ) . '/includes/messaging/abilities.php';
		$src  = file_get_contents( $file );

		$this->assertStringContainsString(
			"'fcom_chat_messages' )->insertGetId(",
			$src,
			'send-message must use insertGetId() on fcom_chat_messages (P-N fix)'
		);

		$this->assertStringNotContainsString(
			"'fcom_chat_messages' )->insert(",
			$src,
			'send-message must NOT use insert() on fcom_chat_messages — returns boolean, not last insert id'
		);
	}
}
