<?php
/**
 * Unit tests — FluentPlayer P8 shared-root proxy helpers (Addendum 28;
 * J-authorized ledger Addendum 32). These are plain, directly-testable
 * functions (the reviewer ruling: live-only is NOT sufficient; the P2
 * anonymous-closure precedent does NOT transfer here — analogous to P7.1's
 * discriminator which was gated on unit coverage).
 *
 * Covers:
 *  (a) fluent_abilities_player_vendor_error()  — HTTP status → typed-error
 *      class mapping + message extraction.
 *  (b) fluent_abilities_player_detect_disabled_leak() — fires on a true
 *      HTTP-200 disabled-state leak.
 *  (c) RISK EDGE — a genuine success carrying a domain entity plus a
 *      coincidental "not enabled"-ish string does NOT misfire (returns null).
 *
 * @package Fluent_Abilities\Tests\Unit\Player
 */

use PHPUnit\Framework\TestCase;

class PlayerSharedRootTest extends TestCase {

	public static function setUpBeforeClass(): void {
		if ( ! defined( 'FLUENT_PLAYER_VERSION' ) ) {
			define( 'FLUENT_PLAYER_VERSION', '1.0.5' );
		}
		if ( ! defined( 'FLUENT_PLAYER_PRO_VERSION' ) ) {
			define( 'FLUENT_PLAYER_PRO_VERSION', '1.0.5' );
		}
		require_once dirname( __DIR__, 3 ) . '/includes/player/abilities.php';
	}

	// ── (a) fluent_abilities_player_vendor_error() ────────────────────────────

	/** @dataProvider statusClassProvider */
	public function test_vendor_error_status_to_typed_code( $status, $expected_code ) {
		$err = fluent_abilities_player_vendor_error( $status, array( 'message' => 'boom' ) );
		$this->assertInstanceOf( 'WP_Error', $err );
		$this->assertSame( $expected_code, $err->get_error_code() );
		$this->assertSame( 'boom', $err->get_error_message() );
	}

	public function statusClassProvider(): array {
		return array(
			'404 → not_found'                  => array( 404, 'not_found' ),
			'403 → forbidden'                  => array( 403, 'forbidden' ),
			'401 → forbidden'                  => array( 401, 'forbidden' ),
			'422 → vendor_precondition_failed' => array( 422, 'vendor_precondition_failed' ),
			'400 → vendor_precondition_failed' => array( 400, 'vendor_precondition_failed' ),
			'500 → vendor_error'               => array( 500, 'vendor_error' ),
			'418 → vendor_error (other)'       => array( 418, 'vendor_error' ),
		);
	}

	public function test_vendor_error_flattens_errors_map_when_no_message() {
		$err = fluent_abilities_player_vendor_error( 422, array(
			'errors' => array(
				'settings.src' => array( 'The settings.src field is required.' ),
				'settings.viewType' => array( 'Invalid view type.' ),
			),
		) );
		$this->assertSame( 'vendor_precondition_failed', $err->get_error_code() );
		$msg = $err->get_error_message();
		$this->assertStringContainsString( 'settings.src: The settings.src field is required.', $msg );
		$this->assertStringContainsString( 'settings.viewType: Invalid view type.', $msg );
	}

	public function test_vendor_error_synthesises_message_when_body_empty() {
		$err = fluent_abilities_player_vendor_error( 500, array() );
		$this->assertSame( 'vendor_error', $err->get_error_code() );
		$this->assertStringContainsString( 'HTTP 500', $err->get_error_message() );
	}

	// ── (b) fluent_abilities_player_detect_disabled_leak() — fires ────────────

	public function test_leak_detector_fires_on_bare_not_enabled_message() {
		$this->assertSame(
			'Mux integration is not enabled',
			fluent_abilities_player_detect_disabled_leak( array( 'message' => 'Mux integration is not enabled' ) )
		);
	}

	public function test_leak_detector_fires_on_mux_webhook_success_wrap() {
		// MuxController::handleWebhook → {success:true, result:{message:"...not configured"}}
		$out = fluent_abilities_player_detect_disabled_leak( array(
			'success' => true,
			'result'  => array( 'message' => 'Mux is not configured' ),
		) );
		$this->assertSame( 'Mux is not configured', $out );
	}

	public function test_leak_detector_matches_each_marker_phrase() {
		foreach ( array(
			'BunnyCDN Stream integration is not enabled',
			'Service is not configured',
			'Form plugin is not active',
			'Integration is disabled',
		) as $m ) {
			$this->assertSame( $m, fluent_abilities_player_detect_disabled_leak( array( 'message' => $m ) ), $m );
		}
	}

	// ── (b) negative — does NOT fire when it should not ───────────────────────

	public function test_leak_detector_null_on_non_array() {
		$this->assertNull( fluent_abilities_player_detect_disabled_leak( 'nope' ) );
		$this->assertNull( fluent_abilities_player_detect_disabled_leak( null ) );
	}

	public function test_leak_detector_null_when_message_not_a_disabled_marker() {
		// A real precondition message that is NOT an integration-disabled leak.
		$this->assertNull( fluent_abilities_player_detect_disabled_leak( array( 'message' => 'Channel ID is required' ) ) );
	}

	public function test_leak_detector_null_when_no_message_key() {
		$this->assertNull( fluent_abilities_player_detect_disabled_leak( array( 'video' => array( 'id' => 5 ) ) ) );
	}

	// ── (c) RISK EDGE — the pinned prose claim: no misfire on real success ────

	public function test_risk_edge_real_success_with_coincidental_phrase_does_not_misfire() {
		// A genuine success payload that ALSO carries a coincidental
		// "not enabled"-ish string. The domain entity ('video') is present
		// and non-empty → detector MUST return null (no misfire), exactly the
		// prose guarantee the shared-root justification rests on.
		$success = array(
			'message' => 'Note: signed playback is not enabled for this asset',
			'video'   => array( 'id' => 42, 'title' => 'Real Asset', 'status' => 'ready' ),
		);
		$this->assertNull( fluent_abilities_player_detect_disabled_leak( $success ) );
	}

	public function test_risk_edge_entity_under_result_wrap_does_not_misfire() {
		$success = array(
			'success' => true,
			'result'  => array(
				'message' => 'mp4 support is not enabled by default',
				'asset'   => array( 'id' => 'abc123' ),
			),
		);
		$this->assertNull( fluent_abilities_player_detect_disabled_leak( $success ) );
	}

	public function test_risk_edge_empty_entity_is_still_a_leak() {
		// An empty entity value is NOT a real entity — a lone disabled
		// message with only empty placeholders is still a leak.
		$this->assertSame(
			'Bunny Stream integration is not enabled',
			fluent_abilities_player_detect_disabled_leak( array(
				'message' => 'Bunny Stream integration is not enabled',
				'data'    => array(),
			) )
		);
	}
}
