<?php
/**
 * Unit tests — v1.4.0 Package 3 — Booking write fixes.
 *
 * Covers V9 returned-id fix (P-N, F-BOOK-04): add-booking-note must use
 * wpFluent()->table()->insertGetId() so the returned activity_id is the
 * actual auto-increment id, not the boolean-as-int 1 that insert() returns.
 *
 * @package Fluent_Abilities\Tests\Unit\Booking
 */

use PHPUnit\Framework\TestCase;

class P3WriteFixesTest extends TestCase {

	// ── Source-level V9 guard: add-booking-note ───────────────────────────────

	public function test_add_booking_note_uses_insertGetId_not_insert() {
		$file = dirname( __DIR__, 3 ) . '/includes/booking/abilities-bookings.php';
		$src  = file_get_contents( $file );

		$this->assertStringContainsString(
			"'fcal_booking_activity' )->insertGetId(",
			$src,
			'add-booking-note must use insertGetId() on fcal_booking_activity (P-N fix)'
		);

		$this->assertStringNotContainsString(
			"'fcal_booking_activity' )->insert(",
			$src,
			'add-booking-note must NOT use insert() on fcal_booking_activity — returns boolean, not last insert id'
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
