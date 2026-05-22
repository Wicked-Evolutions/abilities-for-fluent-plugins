<?php
/**
 * DELIBERATELY-BAD #106/#107 anti-pattern sample. NEVER required as
 * code (it lives under tests/Contract/fixtures/, outside includes/ and
 * outside PSR-4 src/, so ContractBootstrap never loads it). Sole
 * purpose: a negative control proving CrashClassScanner actually
 * detects the crash class — if the scanner ever stops flagging this,
 * the gate is silently blind (the executable-#108 self-check).
 *
 * Each marked line reproduces an exact historical site shape.
 */

function known_bad_event_location( $input ) {
	$event = \FluentBooking\App\Models\CalendarSlot::find( (int) $input['event_id'] );
	$settings = is_array( $input['location_settings'] ) ? $input['location_settings'] : array();
	// #106 root shape — pre-encoded into a vendor-encoded attribute:
	$event->location_settings = maybe_serialize( $settings ); // BAD-1
	$event->save();
}

function known_bad_team_settings( $input ) {
	$event              = \FluentBooking\App\Models\CalendarSlot::find( 1 );
	$payload            = serialize( array( 'team_members' => array( 1, 2 ) ) );
	$event->settings    = $payload;                           // BAD-2 (var-traced)
	$event->save();
}

function known_bad_affiliate_create() {
	// Array-into-create shape:
	\FluentAffiliate\App\Models\Affiliate::create( array(
		'user_id'  => 5,
		'settings' => maybe_serialize( array( 'bank_details' => 'x' ) ), // BAD-3
	) );
}

function known_bad_calendar_update( $input ) {
	$calendar = \FluentBooking\App\Models\Calendar::find( 2 );
	$calendar->update( array(
		'settings' => wp_json_encode( array( 'team_hosts' => array( 9 ) ) ), // BAD-4
	) );
}
