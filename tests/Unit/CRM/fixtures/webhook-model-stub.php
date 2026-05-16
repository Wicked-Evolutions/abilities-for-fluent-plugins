<?php
/**
 * Stub of FluentCrm\App\Models\Webhook for SafetyFixesTest. The real vendor
 * class is not loaded in unit-test mode; we register an alias so the V7
 * callback can call ->store() and the test can inspect the payload that
 * reached the persistence layer.
 *
 * @package Fluent_Abilities\Tests\Unit\CRM\Fixtures
 */

class FluentCRMTestWebhookStub {
	public static $captured_payload = null;
	public static $next_id          = 0;
	// When false, find() returns null (simulate "webhook not found").
	public static $find_returns     = true;

	public $id;
	public $value;

	public function store( $data ) {
		self::$captured_payload = $data;
		$this->id    = self::$next_id;
		$this->value = array_merge( $data, array( 'url' => 'https://stub.invalid/webhook' ) );
		return $this;
	}

	public function find( $id ) {
		if ( ! self::$find_returns ) {
			return null;
		}
		$w        = new self();
		$w->id    = (int) $id;
		$w->value = array( 'name' => 'existing', 'url' => 'https://stub.invalid/webhook' );
		return $w;
	}

	public function saveChanges( $data ) {
		// Mirror the vendor: capture exactly what reached the persistence layer.
		self::$captured_payload = $data;
		$this->value = array_merge( (array) $this->value, $data );
		return $this;
	}
}

if ( ! class_exists( '\\FluentCrm\\App\\Models\\Webhook', false ) ) {
	class_alias( 'FluentCRMTestWebhookStub', 'FluentCrm\\App\\Models\\Webhook' );
}
