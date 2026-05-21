<?php
/**
 * Layer 1 deterministic gate — VendorContractDiffTest (issue #110).
 *
 * Asserts declared schema field/shape facts against the INSTALLED
 * (pinned) vendor model source — columns ($fillable) and encoded
 * attributes ($casts/mutators). Catches the get-customer/get-coupon/
 * KD-2 "schema says X, vendor stores Y" drift class.
 *
 * HONEST SCOPE (binding #98/#105/#107-Item-4): this is a deterministic
 * contract ANCHOR over the PINNED crash-surface models only
 * (FluentBooking CalendarSlot/Calendar, FluentAffiliate Affiliate). It
 * is NOT a plugin-wide schema↔vendor reconciliation — the per-ability
 * vendor association across the full surface is the vendor-map's job
 * (docs/vendor-map, VendorMapCoverageTest) and its breadth expansion is
 * tracked by #108, NOT claimed here. What this DOES guarantee: if a
 * refreshed vendor snapshot renames/removes/changes the encoding of a
 * column the #106/#107-fixed abilities depend on, this fails loud and
 * names the drift, against pinned source, with zero site.
 *
 * @package Fluent_Abilities\Tests\Contract
 */

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunClassInSeparateProcess;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Support/ContractBootstrap.php';
require_once __DIR__ . '/Support/VendorCastMap.php';

#[RunClassInSeparateProcess]
#[PreserveGlobalState(false)]
final class VendorContractDiffTest extends TestCase {

	/** @var VendorCastMap */
	private static $map;

	/** @var array<string,array> */
	private static $abilities;

	public static function setUpBeforeClass(): void {
		self::$map       = new VendorCastMap( __DIR__ . '/fixtures/vendor' );
		self::$abilities = ContractBootstrap::boot();
	}

	/**
	 * The columns the #106/#107-fixed abilities write must still be
	 * vendor-fillable in the pinned source. A vendor rename/removal vs
	 * the pinned snapshot (caught only after a snapshot refresh — see
	 * PROVENANCE.json refresh_policy) fails here naming the column.
	 */
	public function test_crash_surface_columns_are_vendor_fillable(): void {
		$expect = array(
			'FluentBooking\App\Models\CalendarSlot' => array( 'location_settings', 'settings' ),
			'FluentBooking\App\Models\Calendar'     => array( 'settings' ),
			'FluentAffiliate\App\Models\Affiliate'  => array( 'settings' ),
		);
		foreach ( $expect as $fqcn => $cols ) {
			$fillable = self::$map->fillableOf( $fqcn );
			$this->assertNotEmpty( $fillable, "{$fqcn}: pinned \$fillable not parsed." );
			foreach ( $cols as $c ) {
				$this->assertContains(
					$c,
					$fillable,
					"{$fqcn}: column '{$c}' the fixed ability writes is no longer vendor-\$fillable "
					. '(pinned-source contract drift — refresh the snapshot and reconcile).'
				);
			}
		}
	}

	/**
	 * The vendor's encoding contract for the crash-surface columns is
	 * exactly the locked expectation. If a vendor change flips a column
	 * from mutator-encoded to plain (or adds a new serialized cast), our
	 * "pass the plain value" #106/#107 fix premise changes — surface it.
	 */
	public function test_vendor_encoding_contract_is_locked(): void {
		$map = self::$map->map();
		$this->assertSame(
			array( 'location_settings', 'settings' ),
			$map['FluentBooking\App\Models\CalendarSlot'] ?? array(),
			'CalendarSlot vendor-encoded attrs drifted from the #106 contract.'
		);
		$this->assertSame(
			array( 'settings' ),
			$map['FluentBooking\App\Models\Calendar'] ?? array(),
			'Calendar vendor-encoded attrs drifted from the #107 contract.'
		);
		$this->assertSame(
			array( 'settings' ),
			$map['FluentAffiliate\App\Models\Affiliate'] ?? array(),
			'Affiliate vendor-encoded attrs drifted from the #107 contract.'
		);
	}

	/**
	 * Concrete schema↔vendor field diff sample (the technique the
	 * get-customer/get-coupon/KD-2 class needs), scoped to a pinned
	 * model: the booking event-location ability declares
	 * `location_settings`, and that name is a real vendor CalendarSlot
	 * fillable column — schema and vendor agree on the field.
	 */
	public function test_event_location_schema_field_matches_vendor_column(): void {
		$slug = 'fluent-booking/update-event-location-config';
		if ( ! isset( self::$abilities[ $slug ] ) ) {
			$this->markTestSkipped( "{$slug} not registered (surface change) — RegistrationIntegrityTest owns that." );
		}
		$schema = self::$abilities[ $slug ]['input_schema'] ?? array();
		$props  = array_keys( $schema['properties'] ?? array() );

		$this->assertContains( 'location_settings', $props, "{$slug}: input_schema lost 'location_settings'." );
		$this->assertContains(
			'location_settings',
			self::$map->fillableOf( 'FluentBooking\App\Models\CalendarSlot' ),
			"Declared schema field 'location_settings' has no matching vendor CalendarSlot column "
			. '(schema↔vendor drift).'
		);
	}
}
