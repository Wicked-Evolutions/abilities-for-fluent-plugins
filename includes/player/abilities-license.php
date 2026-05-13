<?php
/**
 * FluentPlayer Abilities — License (Pro)
 *
 * 3 abilities in the `fluent-player` category covering license activation,
 * deactivation, and detail readout. Wholly Pro; `manage_options` capability
 * override per research §5.17 (license operations are admin-only).
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register license abilities. Called via wp_abilities_api_init at priority 100.
 *
 * Extracted as a named function so unit tests (which stub add_action as a no-op)
 * can invoke registration directly.
 */
function fluent_abilities_player_register_license_abilities() {

	if ( ! defined( 'FLUENT_PLAYER_PRO_VERSION' ) ) {
		return;
	}

	$reg = new Fluent_Abilities_Registrar( 'player' );

	// SECURITY NOTE: response contains license key + activation tokens — flag for mcp.public=false + redaction in v1.2 meta-override.
	$reg->read( 'fluent-player/get-license-details', array(
		'label'         => 'Get license details',
		'description'   => 'Get FluentPlayer Pro license status, activation site, expiry, and (redacted) key.',
		'category'      => 'fluent-player',
		'capability'    => 'manage_options',
		'output_schema' => fluent_abilities_schema_item_output( array(
			'license_key'   => array( 'type' => 'string', 'description' => 'Redacted license key.' ),
			'status'        => array( 'type' => 'string' ),
			'expires'       => array( 'type' => array( 'string', 'null' ) ),
			'activated_on'  => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback'      => function ( $input ) {
			if ( ! class_exists( '\FluentPlayerPro\App\Http\Controllers\LicenseController' ) ) {
				return fluent_abilities_error( 'missing_class', 'FluentPlayerPro LicenseController not found.' );
			}
			try {
				$controller = new \FluentPlayerPro\App\Http\Controllers\LicenseController();
				return $controller->getLicenseDetails();
			} catch ( \Throwable $e ) {
				return fluent_abilities_error( 'execution_failed', $e->getMessage() );
			}
		},
	) );

	// SECURITY NOTE: input contains secret license key — flag for mcp.public=false + redaction in v1.2 meta-override.
	$reg->write( 'fluent-player/activate-license', array(
		'label'         => 'Activate license',
		'description'   => 'Activate a FluentPlayer Pro license against the vendor.',
		'category'      => 'fluent-player',
		'capability'    => 'manage_options',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'license_key' ),
			'properties' => array(
				'license_key' => array(
					'type'        => 'string',
					'description' => 'FluentPlayer Pro license key (treated as a secret).',
				),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'message' => array( 'type' => 'string' ),
			'status'  => array( 'type' => 'string' ),
		) ),
		'annotations'   => array( 'idempotent' => false ),
		'callback'      => function ( $input ) {
			$license_key = isset( $input['license_key'] ) ? trim( (string) $input['license_key'] ) : '';
			if ( '' === $license_key ) {
				return fluent_abilities_error( 'ability_invalid_input', 'license_key is required.' );
			}
			if ( ! class_exists( '\FluentPlayerPro\App\Http\Controllers\LicenseController' ) ) {
				return fluent_abilities_error( 'missing_class', 'FluentPlayerPro LicenseController not found.' );
			}
			try {
				$_REQUEST['license_key'] = $license_key;
				$_POST['license_key']    = $license_key;
				$controller              = new \FluentPlayerPro\App\Http\Controllers\LicenseController();
				$result                  = $controller->activateLicense();
				$message                 = is_array( $result ) ? ( $result['message'] ?? 'License activated.' ) : 'License activated.';
				$status                  = is_array( $result ) ? ( $result['status'] ?? 'active' ) : 'active';
				return array(
					'success' => true,
					'message' => $message,
					'status'  => $status,
				);
			} catch ( \Throwable $e ) {
				return fluent_abilities_error( 'execution_failed', $e->getMessage() );
			}
		},
	) );

	// SECURITY NOTE: removes Pro entitlement; admin-only — flag for mcp.public=false in v1.2 meta-override.
	$reg->delete( 'fluent-player/deactivate-license', array(
		'label'         => 'Deactivate license',
		'description'   => 'Deactivate the FluentPlayer Pro license on this site.',
		'category'      => 'fluent-player',
		'capability'    => 'manage_options',
		'output_schema' => fluent_abilities_schema_success_output( array(
			'message' => array( 'type' => 'string' ),
		) ),
		'annotations'   => array( 'idempotent' => false ),
		'callback'      => function ( $input ) {
			if ( ! class_exists( '\FluentPlayerPro\App\Http\Controllers\LicenseController' ) ) {
				return fluent_abilities_error( 'missing_class', 'FluentPlayerPro LicenseController not found.' );
			}
			try {
				$controller = new \FluentPlayerPro\App\Http\Controllers\LicenseController();
				$result     = $controller->deactivateLicense();
				$message    = is_array( $result ) ? ( $result['message'] ?? 'License deactivated.' ) : 'License deactivated.';
				return array(
					'success' => true,
					'message' => $message,
				);
			} catch ( \Throwable $e ) {
				return fluent_abilities_error( 'execution_failed', $e->getMessage() );
			}
		},
	) );
}
add_action( 'wp_abilities_api_init', 'fluent_abilities_player_register_license_abilities', 100 );
