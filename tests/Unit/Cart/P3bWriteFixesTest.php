<?php
/**
 * Unit tests — v1.4.0 Package 3b — FluentCart write-correctness fixes.
 *
 * Source-level guards (the callbacks dispatch into vendor FluentCart models /
 * services that are not loadable in unit mode; behaviour is proven by the
 * dev-run live re-test on helenawillow, evidence in the PR body).
 *
 *  - F-CART-03 create-custom-order: per-item `subtotal` is set (the canonical
 *    field the vendor totals-calc reads) and the order total is re-derived
 *    via OrderService::getItemsAmountTotal, not just the local sum.
 *  - F-CART-04 list-attachable-users: the vendor-style `q` param is accepted
 *    as an alias of `search`.
 *  - F-CART-06 update-order-address-id: a missing stored customer address
 *    returns a typed vendor_precondition_failed error that names the
 *    precondition (callback-only; no schema/annotation change).
 *
 * @package Fluent_Abilities\Tests\Unit\Cart
 */

use PHPUnit\Framework\TestCase;

class P3bWriteFixesTest extends TestCase {

	private function order_mgmt(): string {
		return file_get_contents( dirname( __DIR__, 3 ) . '/includes/cart/order-management-abilities.php' );
	}

	private function customer_ext(): string {
		return file_get_contents( dirname( __DIR__, 3 ) . '/includes/cart/customer-extended-abilities.php' );
	}

	// ── F-CART-03 ────────────────────────────────────────────────────────────

	public function test_create_custom_order_sets_canonical_item_subtotal() {
		$src = $this->order_mgmt();
		$this->assertStringContainsString(
			"'subtotal'         => \$line_total,",
			$src,
			'create-custom-order must set the canonical per-item subtotal the vendor totals-calc reads (F-CART-03)'
		);
	}

	public function test_create_custom_order_routes_total_through_vendor_service() {
		$src = $this->order_mgmt();
		$this->assertStringContainsString(
			'OrderService::getItemsAmountTotal(',
			$src,
			'create-custom-order must re-derive the order total via the vendor totals-calc (F-CART-03, V3)'
		);
		$this->assertStringContainsString(
			'$order->total_amount = $effective_total;',
			$src,
			'the vendor-derived total must be persisted on the order'
		);
	}

	public function test_create_custom_order_has_vendor_guard() {
		$src = $this->order_mgmt();
		$this->assertStringContainsString(
			"return new WP_Error( 'vendor_helper_unavailable', 'FluentCart Order/OrderItem models are not available",
			$src,
			'create-custom-order must return a typed WP_Error if FluentCart models are absent (V10, no fatal)'
		);
	}

	// ── F-CART-04 ────────────────────────────────────────────────────────────

	public function test_list_attachable_users_accepts_q_alias() {
		$src = $this->customer_ext();
		$this->assertStringContainsString(
			"'q'      => array( 'type' => 'string'",
			$src,
			'list-attachable-users input_schema must declare the vendor-style `q` alias (F-CART-04)'
		);
		$this->assertStringContainsString(
			"elseif ( ! empty( \$input['q'] ) )",
			$src,
			'list-attachable-users callback must read `q` when `search` is absent (F-CART-04)'
		);
	}

	// ── F-CART-06 ────────────────────────────────────────────────────────────

	public function test_update_order_address_id_typed_precondition_error() {
		$src = $this->order_mgmt();
		$this->assertStringContainsString(
			"'vendor_precondition_failed',",
			$src,
			'update-order-address-id must return a typed precondition error (F-CART-06, V10)'
		);
		$this->assertStringContainsString(
			'must be a saved customer-address id from fluent-cart/list-customer-addresses',
			$src,
			'the precondition error must name what address_id should be (not a bare "not found")'
		);
	}

	public function test_update_order_address_id_schema_unchanged() {
		// F-CART-06 is callback-only. address_id schema description must remain
		// the original vendor-native pointer (no schema/annotation mutation).
		$src = $this->order_mgmt();
		$this->assertStringContainsString(
			"'address_id' => array( 'type' => 'integer', 'description' => 'fct_customer_addresses.id' )",
			$src,
			'update-order-address-id input_schema must be unchanged (callback-only fix, Principle 10)'
		);
	}
}
