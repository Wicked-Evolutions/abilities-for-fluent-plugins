<?php
/**
 * Unit Tests — Vendor-Map Manifest Coverage (V11 drift detector)
 *
 * Enforces the four binding CI-checks from PRINCIPLES-VENDOR.md
 * ("CI checks" section) across every docs/vendor-map/fluent-*.json:
 *
 *   1. Every fluent-* ability touched in the sprint has a vendor-map row
 *      (or an explicit exception). Enforced here as: a curated set of
 *      P1–P5 touched slugs must each resolve to a row — a regression
 *      guard, not a 1000-ability proof (that is the platform contract's
 *      ScopeCoverageDriftTest analog; out of scope per PRINCIPLES-VENDOR).
 *   2. Every row has non-empty `authority` / `doc` / `vendor_version_checked`.
 *   3. `direct_storage: true` rows carry a rationale.
 *   4. Write/delete rows declare a `read_back` path (object or an explicit
 *      "N/A — …" string for carry-forward / non-state-change fixes).
 *
 * Pure-unit: parses JSON only, no WP/DB. Mirrors the contract's stated
 * intent — "not a perfect proof, but a drift detector".
 *
 * @package Fluent_Abilities\Tests\Unit
 */

use PHPUnit\Framework\TestCase;

class VendorMapCoverageTest extends TestCase {

	/** @var string */
	private static $dir;

	public static function setUpBeforeClass(): void {
		self::$dir = dirname( __DIR__, 2 ) . '/docs/vendor-map';
	}

	/** @return array<string,array> slug => row, across all plugin files. */
	private function allRows(): array {
		$rows  = array();
		$files = glob( self::$dir . '/fluent-*.json' );
		$this->assertNotEmpty( $files, 'No docs/vendor-map/fluent-*.json files found.' );
		foreach ( $files as $f ) {
			$data = json_decode( (string) file_get_contents( $f ), true );
			$this->assertIsArray( $data, "Invalid JSON: {$f}" );
			$this->assertArrayHasKey( 'abilities', $data, "Missing 'abilities': {$f}" );
			foreach ( $data['abilities'] as $i => $row ) {
				$this->assertArrayHasKey( 'ability', $row, "Row {$i} missing 'ability' in {$f}" );
				$slug = $row['ability'];
				$this->assertArrayNotHasKey( $slug, $rows, "Duplicate vendor-map row for {$slug}" );
				$row['__file']   = basename( $f );
				$rows[ $slug ]   = $row;
			}
		}
		return $rows;
	}

	/** CI-check #2 — authority / doc / vendor_version_checked present. */
	public function test_every_row_has_authority_doc_version(): void {
		foreach ( $this->allRows() as $slug => $row ) {
			foreach ( array( 'authority', 'doc', 'vendor_version_checked' ) as $k ) {
				$this->assertArrayHasKey( $k, $row, "{$slug}: missing '{$k}'" );
				$this->assertNotSame( '', trim( (string) ( is_array( $row[ $k ] ) ? json_encode( $row[ $k ] ) : $row[ $k ] ) ), "{$slug}: empty '{$k}'" );
			}
		}
	}

	/** CI-check #3 — direct_storage:true carries a rationale. */
	public function test_direct_storage_rows_carry_rationale(): void {
		foreach ( $this->allRows() as $slug => $row ) {
			if ( ( $row['direct_storage'] ?? false ) === true ) {
				$has = ( ! empty( $row['rationale'] ) )
					|| ( false !== stripos( (string) ( $row['notes'] ?? '' ), 'direct_storage' ) )
					|| ( false !== stripos( (string) ( $row['notes'] ?? '' ), 'raw ' ) );
				$this->assertTrue( $has, "{$slug}: direct_storage:true without a rationale (key or notes)." );
			}
		}
	}

	/** CI-check #4 — write/delete rows declare a read_back. */
	public function test_write_delete_rows_declare_read_back(): void {
		$verbs = array(
			'create-', 'update-', 'delete-', 'set-', 'add-', 'remove-', 'attach-',
			'detach-', 'duplicate-', 'clone-', 'bulk-', 'do-bulk', 'move-',
			'assign-', 'upload-', 'import-', 'schedule-', 'change-', 'reorder-',
			'extend-', 'regenerate-', 'cancel-', 'pause-', 'resume-', 'activate-',
			'deactivate-', 'apply-', 'sync-', 'send-', 'emit-', 'cast-', 'follow-',
			'unfollow-', 'revoke-', 'mark-', 'toggle-', 'enable-', 'disable-',
		);
		foreach ( $this->allRows() as $slug => $row ) {
			$verb = substr( (string) strrchr( $slug, '/' ), 1 );
			$is_write = false;
			foreach ( $verbs as $p ) {
				if ( 0 === strpos( $verb, $p ) ) { $is_write = true; break; }
			}
			if ( ! $is_write ) {
				continue;
			}
			$rb = $row['read_back'] ?? null;
			$ok = ( is_array( $rb ) && ! empty( $rb ) )
				|| ( is_string( $rb ) && '' !== trim( $rb ) );
			$this->assertTrue( $ok, "{$slug}: write/delete row missing a read_back (path object or explicit 'N/A — reason')." );
		}
	}

	/**
	 * CI-check #1 — sprint-touched coverage regression guard.
	 * A representative P1–P5 touched slug per package/pattern must resolve
	 * to a vendor-map row. If a reconcile/refactor drops one, CI fails.
	 */
	public function test_sprint_touched_slugs_are_covered(): void {
		$touched = array(
			'fluent-crm/update-contact-custom-fields',   // P1 V7/V8
			'fluent-crm/create-webhook',                 // P1 P-I
			'fluent-crm/list-funnel-templates',          // P2 P-K
			'fluent-cart/create-custom-order',           // P3b F-CART-03
			'fluent-cart/update-order-address-id',       // P3b F-CART-06
			'fluent-crm/update-webhook',                 // P3.5 P-I
			'fluent-forms/create-form',                  // P3c F-FORMS-01
			'fluent-crm/get-company',                    // P4a P-G
			'fluent-crm/list-recurring-campaigns',       // P4a P-J
			'fluent-cart/get-coupon',                    // P4a P-H special (PENDING)
			'fluent-crm/get-template',                   // P4b Add.15(b)
			'fluent-crm/get-report-top-campaigns',       // P4b separable-defect (PENDING)
			'fluent-boards/upload-csv',                  // P5 P-B anyOf
			'fluent-boards/list-tasks-by-stage',         // P5 P-D
			'fluent-crm/update-campaign',                // P5 P-C editorial
			'fluent-community/update-space',             // P5 P-C editorial
			'fluent-player/get-media-metadata',          // P5 P-C editorial
		);
		$rows = $this->allRows();
		foreach ( $touched as $slug ) {
			$this->assertArrayHasKey( $slug, $rows, "Sprint-touched slug has no vendor-map row: {$slug}" );
		}
	}
}
