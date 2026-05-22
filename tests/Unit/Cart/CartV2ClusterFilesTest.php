<?php
/**
 * Unit Tests — FluentCart v2.0.0 cluster files
 *
 * Validates that the 13 new cluster files added in Phase B of the Fluent
 * Suite Registrar Bundle Sprint:
 *   - parse as valid PHP
 *   - declare exactly the slug count expected per research §4
 *   - use only `fluent-cart/<verb>-<noun>` slugs
 *   - do NOT propagate KD-1 (wrong CPT `fct_product`) or KD-2 (drift columns)
 *
 * @package Fluent_Abilities\Tests\Unit\Cart
 */

use PHPUnit\Framework\TestCase;

class CartV2ClusterFilesTest extends TestCase {

	/**
	 * Map cluster file => expected ability count from research §4.
	 */
	private function clusterCounts(): array {
		return array(
			'order-management-abilities.php'        => 14, // 4.1 (4) + 4.2 (6) + 4.3 (4)
			'customer-extended-abilities.php'       => 13, // 4.4 (5) + 4.5 (3) + 4.6 (5)
			'subscription-extended-abilities.php'   => 4,  // 4.7
			'product-extended-abilities.php'        => 9, // 4.8 (5) + 4.9 (5) + 4.10 (5)
			// 4.11 (Pro product-upgrade-paths) deferred per #65 — vendor surface
			// (\FluentCartPro\App\Modules\Promotional\Models\UpgradePath) not present
			// in FluentCart Pro 1.3.26. Will land after research/vendor reconciliation.
			'attribute-abilities.php'               => 8,  // 4.12
			'coupon-extended-abilities.php'         => 5,  // 4.13
			'license-extended-abilities.php'        => 11, // 4.14 (7) + 4.15 (4) (Pro)
			'settings-abilities.php'                => 12, // 4.16
			'tax-abilities.php'                     => 8,  // 4.17 (7 in research §4 + delete-tax-rate added round-4-redux)
			'shipping-abilities.php'                => 6,  // 4.18
			'activity-abilities.php'                => 3,  // 4.19
			'reports-abilities.php'                 => 7,  // 4.20
		);
	}

	private function cartDir(): string {
		return dirname( __DIR__, 3 ) . '/includes/cart';
	}

	/**
	 * @dataProvider clusterFileProvider
	 */
	public function test_cluster_file_parses_as_valid_php( string $file ): void {
		$path = $this->cartDir() . '/' . $file;
		$this->assertFileExists( $path, "Cluster file $file should exist." );

		$source = file_get_contents( $path );
		$this->assertNotEmpty( $source );

		// Tokenize — a syntax error would surface here as a ParseError caught by phpunit.
		$tokens = @token_get_all( $source, TOKEN_PARSE );
		$this->assertNotEmpty( $tokens, "Tokeniser failed for $file (likely PHP syntax error)." );
	}

	/**
	 * @dataProvider clusterFileProvider
	 */
	public function test_cluster_file_declares_expected_slug_count( string $file, int $expected ): void {
		$path   = $this->cartDir() . '/' . $file;
		$source = file_get_contents( $path );

		// Extract every fluent-cart/<verb>-<noun> slug used as the first argument to
		// $reg->read/write/delete(). Anchored to the registrar API to avoid counting
		// slugs that appear only in descriptions or comments.
		preg_match_all( "/\\\$reg->(?:read|write|delete)\\(\\s*'(fluent-cart\\/[^']+)'/", $source, $matches );
		$slugs = array_unique( $matches[1] );

		$this->assertCount(
			$expected,
			$slugs,
			"Cluster $file should register $expected unique fluent-cart/* slugs via \$reg->read|write|delete()."
		);
	}

	/**
	 * @dataProvider clusterFileProvider
	 */
	public function test_cluster_file_uses_correct_registrar_module_key( string $file ): void {
		$source = file_get_contents( $this->cartDir() . '/' . $file );
		$this->assertMatchesRegularExpression(
			"/new\\s+Fluent_Abilities_Registrar\\(\\s*'cart'\\s*\\)/",
			$source,
			"Cluster $file must use new Fluent_Abilities_Registrar('cart')."
		);
	}

	/**
	 * @dataProvider clusterFileProvider
	 */
	public function test_cluster_file_does_not_propagate_kd1_wrong_cpt( string $file ): void {
		// KD-1: v1.1.3 create-product uses 'fct_product' (wrong); new code must use 'fluent-products'.
		$source = file_get_contents( $this->cartDir() . '/' . $file );
		$this->assertStringNotContainsString(
			"'fct_product'",
			$source,
			"Cluster $file must NOT use the broken 'fct_product' CPT (KD-1). Use 'fluent-products'."
		);
		$this->assertStringNotContainsString(
			'"fct_product"',
			$source,
			"Cluster $file must NOT use the broken 'fct_product' CPT (KD-1)."
		);
	}

	public function test_kd2_orders_use_total_amount_column(): void {
		// KD-2 §6.1.3: fct_orders.total_amount is the canonical column (NOT `total`).
		// order-management-abilities.php is the file that does CRUD on orders.
		$source = file_get_contents( $this->cartDir() . '/order-management-abilities.php' );
		$this->assertStringContainsString(
			'total_amount',
			$source,
			'order-management-abilities.php must reference total_amount (fct_orders canonical column per KD-2).'
		);

		// Also assert the customer-extended file (list-customer-orders surfaces total_amount).
		$source = file_get_contents( $this->cartDir() . '/customer-extended-abilities.php' );
		$this->assertStringContainsString( 'total_amount', $source );
	}

	public function test_kd2_customer_drift_uses_correct_columns(): void {
		$source = file_get_contents( $this->cartDir() . '/customer-extended-abilities.php' );

		// fct_customers has purchase_count + ltv + aov (NOT total_order_count + lifetime_value per KD-2).
		$this->assertStringContainsString( 'purchase_count', $source );
		$this->assertStringContainsString( 'ltv', $source );
		$this->assertStringContainsString( 'aov', $source );
		$this->assertStringNotContainsString( 'total_order_count', $source );
		$this->assertStringNotContainsString( 'lifetime_value', $source );
	}

	public function test_kd2_transaction_uses_total_not_amount(): void {
		// fct_order_transactions: column is `total` (BIGINT cents), not `amount` per KD-2 §6.1.4.
		$source = file_get_contents( $this->cartDir() . '/order-management-abilities.php' );

		// Build a regex that targets OrderTransaction write paths. Verify `'total'`
		// or `total =>` appears alongside OrderTransaction references.
		$this->assertMatchesRegularExpression(
			"/OrderTransaction[\\s\\S]+?'total'/",
			$source,
			"OrderTransaction references should use 'total' column, not 'amount'."
		);
	}

	public function test_kd2_shipping_method_uses_amount_not_cost(): void {
		// fct_shipping_methods: column is `amount` (DECIMAL), not `cost` per KD-2 §6.1.8.
		$source = file_get_contents( $this->cartDir() . '/shipping-abilities.php' );

		$this->assertStringContainsString( "'amount'", $source );
		$this->assertMatchesRegularExpression(
			"/'amount'\\s*=>/",
			$source,
			"Shipping methods should write to 'amount' column."
		);
		// `cost` may appear only inside docblock referring to the historical KD-2 mistake.
		// Ensure it's not a real column reference.
		$this->assertDoesNotMatchRegularExpression(
			"/'cost'\\s*=>/",
			$source,
			"Shipping methods must NOT write to 'cost' (does not exist; KD-2)."
		);
	}

	public function test_v2_file_loader_array_contains_all_new_clusters(): void {
		$write_abilities = file_get_contents( $this->cartDir() . '/write-abilities.php' );

		foreach ( array_keys( $this->clusterCounts() ) as $file ) {
			$basename_no_ext = pathinfo( $file, PATHINFO_FILENAME );
			$this->assertStringContainsString(
				"'$basename_no_ext'",
				$write_abilities,
				"write-abilities.php \$cart_ability_files array must reference $basename_no_ext"
			);
		}
	}

	public function test_pro_gated_files_check_pro_classes(): void {
		// Per existing v1.1.3 license-abilities.php pattern: Pro-only files must early-return
		// when the Pro class isn't available.
		$pro_files = array(
			'license-extended-abilities.php' => 'FluentCartPro\\\\App\\\\Modules\\\\Licensing\\\\Models\\\\License',
		);
		foreach ( $pro_files as $file => $needle ) {
			$source = file_get_contents( $this->cartDir() . '/' . $file );
			$this->assertMatchesRegularExpression(
				"/$needle/",
				$source,
				"Pro-gated cluster $file must reference $needle for class_exists check"
			);
			$this->assertMatchesRegularExpression(
				"/if\\s*\\(\\s*!\\s*(?:class_exists|defined)/",
				$source,
				"Pro-gated cluster $file must early-return when Pro module is missing"
			);
		}
	}

	public function clusterFileProvider(): array {
		$cases = array();
		foreach ( $this->clusterCounts() as $file => $count ) {
			$cases[ $file ] = array( $file, $count );
		}
		return $cases;
	}
}
