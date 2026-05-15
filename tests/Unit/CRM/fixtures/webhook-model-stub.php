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

	public $id;
	public $value;

	public function store( $data ) {
		self::$captured_payload = $data;
		$this->id    = self::$next_id;
		$this->value = array_merge( $data, array( 'url' => 'https://stub.invalid/webhook' ) );
		return $this;
	}
}

if ( ! class_exists( '\\FluentCrm\\App\\Models\\Webhook', false ) ) {
	class_alias( 'FluentCRMTestWebhookStub', 'FluentCrm\\App\\Models\\Webhook' );
}
