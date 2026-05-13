<?php
/**
 * Unit Tests — FluentCart v2.0.0 Registrar semantics
 *
 * Verifies that the Registrar pattern used by every v2.0.0 cluster file
 * produces the expected ability shape for read / write / delete operations
 * and that permission_callback rejects when the user lacks the declared
 * capability. Runs a representative slug per operation type.
 *
 * @package Fluent_Abilities\Tests\Unit\Cart
 */

use PHPUnit\Framework\TestCase;
use WickedEvolutions\AbilitiesForFluent\Core\Registrar;

class CartV2RegistrarSemanticsTest extends TestCase {

	protected function setUp(): void {
		global $_wp_registered_abilities, $_wp_options_store;
		$_wp_registered_abilities = array();
		$_wp_options_store        = array();
		unset( $GLOBALS['_test_user_caps'] );
	}

	// ── Read path ─────────────────────────────────────────────────────────────

	public function test_v2_read_registers_under_fluent_cart_category(): void {
		$reg = new Registrar( 'cart' );
		$reg->read( 'fluent-cart/list-order-transactions', array(
			'label'         => 'List Order Transactions',
			'description'   => 'Scoped to one order.',
			'callback'      => function () {
				return array( 'transactions' => array(), 'total' => 0, 'page' => 1, 'per_page' => 20 );
			},
			'output_schema' => array( 'type' => 'object' ),
		) );

		$abilities = wp_get_abilities();
		$this->assertArrayHasKey( 'fluent-cart/list-order-transactions', $abilities );
		$ability = $abilities['fluent-cart/list-order-transactions'];
		$this->assertSame( 'fluent-cart', $ability['category'] );
		$this->assertTrue( $ability['meta']['annotations']['readonly'] );
		$this->assertFalse( $ability['meta']['annotations']['destructive'] );
		$this->assertSame( 'read', $ability['meta']['annotations']['permission'] );
		$this->assertSame( 'pro', $ability['meta']['tier'] );
	}

	public function test_v2_read_callback_returns_payload_shape(): void {
		$reg = new Registrar( 'cart' );
		$reg->read( 'fluent-cart/get-customer-stats', array(
			'label'       => 'Get Customer Stats',
			'description' => 'Aggregate purchase stats for one customer.',
			'callback'    => function () {
				return array(
					'id'             => 42,
					'purchase_count' => 5,
					'ltv'            => 123.45,
					'aov'            => 24.69,
				);
			},
		) );

		$abilities = wp_get_abilities();
		$result    = ( $abilities['fluent-cart/get-customer-stats']['execute_callback'] )( (object) array() );
		$this->assertSame( 5, $result['purchase_count'] );
		$this->assertSame( 123.45, $result['ltv'] );
	}

	// ── Write path ────────────────────────────────────────────────────────────

	public function test_v2_write_registers_with_write_annotation(): void {
		$reg = new Registrar( 'cart' );
		$reg->write( 'fluent-cart/create-customer', array(
			'label'       => 'Create Customer',
			'description' => 'Create a fct_customers row.',
			'callback'    => function ( $input ) {
				return array( 'success' => true, 'id' => 1, 'email' => $input['email'] ?? '' );
			},
			'annotations' => array( 'idempotent' => false ),
		) );

		$abilities = wp_get_abilities();
		$ability   = $abilities['fluent-cart/create-customer'];
		$this->assertFalse( $ability['meta']['annotations']['readonly'] );
		$this->assertFalse( $ability['meta']['annotations']['destructive'] );
		$this->assertSame( 'write', $ability['meta']['annotations']['permission'] );
		$this->assertFalse( $ability['meta']['annotations']['idempotent'] );
	}

	public function test_v2_write_with_manage_options_capability(): void {
		$reg = new Registrar( 'cart' );
		$reg->write( 'fluent-cart/create-custom-order', array(
			'label'       => 'Create Custom Order',
			'description' => 'Admin-side manual order.',
			'callback'    => function () {
				return array( 'success' => true );
			},
			'capability' => 'manage_options',
		) );

		$abilities = wp_get_abilities();
		$this->assertArrayHasKey( 'fluent-cart/create-custom-order', $abilities );

		// Permission callback must use manage_options.
		$pcb = $abilities['fluent-cart/create-custom-order']['permission_callback'];

		$GLOBALS['_test_user_caps'] = array( 'manage_options' );
		$this->assertTrue( $pcb(), 'Admin with manage_options should pass.' );

		$GLOBALS['_test_user_caps'] = array( 'edit_posts' );
		$this->assertFalse( $pcb(), 'User without manage_options must be rejected.' );
	}

	// ── Delete path ───────────────────────────────────────────────────────────

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_v2_delete_registers_with_destructive_annotation(): void {
		$reg = new Registrar( 'cart' );
		$reg->delete( 'fluent-cart/delete-order-item', array(
			'label'       => 'Delete Order Item',
			'description' => 'Delete a line item.',
			'callback'    => function ( $input ) {
				return array( 'success' => true, 'id' => (int) ( $input['id'] ?? 0 ) );
			},
			'annotations' => array( 'idempotent' => false ),
		) );

		$abilities = wp_get_abilities();
		$ability   = $abilities['fluent-cart/delete-order-item'];
		$this->assertTrue( $ability['meta']['annotations']['destructive'] );
		$this->assertSame( 'delete', $ability['meta']['annotations']['permission'] );
		$this->assertFalse( $ability['meta']['annotations']['idempotent'] );
	}

	// ── Permission failure ────────────────────────────────────────────────────

	public function test_v2_permission_callback_rejects_unauthorized_user(): void {
		$reg = new Registrar( 'cart' );
		$reg->write( 'fluent-cart/update-payment-method', array(
			'label'       => 'Update Payment Method',
			'description' => 'Updates a payment-method config.',
			'callback'    => function () {
				return array( 'success' => true );
			},
			'capability' => 'manage_options',
		) );

		$abilities = wp_get_abilities();
		$pcb       = $abilities['fluent-cart/update-payment-method']['permission_callback'];

		$GLOBALS['_test_user_caps'] = array(); // no caps
		$this->assertFalse( $pcb(), 'Anonymous / unprivileged user must be rejected for write-payment-method.' );
	}

	// ── Schema validation ────────────────────────────────────────────────────

	public function test_v2_pagination_schema_helper_returns_page_and_per_page(): void {
		$schema = fluent_abilities_pagination_schema();
		$this->assertArrayHasKey( 'page', $schema );
		$this->assertArrayHasKey( 'per_page', $schema );
		$this->assertSame( 'integer', $schema['page']['type'] );
		$this->assertSame( 'integer', $schema['per_page']['type'] );
	}

	public function test_v2_list_output_schema_uses_custom_items_key(): void {
		$schema = fluent_abilities_schema_list_output( 'transactions', array(
			'id' => array( 'type' => 'integer' ),
		) );
		$this->assertArrayHasKey( 'transactions', $schema['properties'] );
		$this->assertSame( 'array', $schema['properties']['transactions']['type'] );
	}

	public function test_v2_delete_tax_rate_registers_with_destructive_annotation_and_manage_options(): void {
		// Round-4-redux addition: cluster 4.17 gained delete-tax-rate to close the
		// vendor-surface gap (TaxRateController::delete($id)). Verify it ships as a
		// destructive write protected by manage_options.
		$reg = new Registrar( 'cart' );
		$reg->delete( 'fluent-cart/delete-tax-rate', array(
			'label'       => 'Delete Tax Rate',
			'description' => 'Delete by id.',
			'callback'    => function ( $input ) {
				return array( 'success' => true, 'id' => (int) ( $input['id'] ?? 0 ) );
			},
			'capability'  => 'manage_options',
			'annotations' => array( 'idempotent' => false ),
		) );

		$abilities = wp_get_abilities();
		$this->assertArrayHasKey( 'fluent-cart/delete-tax-rate', $abilities );
		$annotations = $abilities['fluent-cart/delete-tax-rate']['meta']['annotations'];
		$this->assertTrue( $annotations['destructive'] );
		$this->assertSame( 'delete', $annotations['permission'] );
		$this->assertFalse( $annotations['idempotent'] );

		$pcb = $abilities['fluent-cart/delete-tax-rate']['permission_callback'];
		$GLOBALS['_test_user_caps'] = array( 'manage_options' );
		$this->assertTrue( $pcb() );
		$GLOBALS['_test_user_caps'] = array( 'edit_posts' );
		$this->assertFalse( $pcb() );
	}

	public function test_v2_success_output_schema_includes_success_bool(): void {
		$schema = fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) );
		$this->assertSame( 'boolean', $schema['properties']['success']['type'] );
		$this->assertArrayHasKey( 'id', $schema['properties'] );
	}
}
