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
			$result = fluent_abilities_player_invoke_controller(
				'\FluentPlayerPro\App\Http\Controllers\LicenseController',
				'getLicenseDetails',
				is_array( $input ) ? $input : array()
			);
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			// Redact license_key, payment_id, customer_email, customer_name (per Reviewer pre-flight #1).
			return fluent_abilities_player_redact( $result );
		},
	) );

	// SECURITY NOTE: input contains secret license key — redacted in output (per Reviewer pre-flight #1).
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
			$input['license_key'] = $license_key;
			$result               = fluent_abilities_player_invoke_controller(
				'\FluentPlayerPro\App\Http\Controllers\LicenseController',
				'activateLicense',
				$input
			);
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			// Activation response may echo the license key back; redact before returning.
			$result = fluent_abilities_player_redact( is_array( $result ) ? $result : array() );
			return array(
				'success' => true,
				'message' => $result['message'] ?? 'License activated.',
				'status'  => $result['status'] ?? 'active',
			);
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
			$result = fluent_abilities_player_invoke_controller(
				'\FluentPlayerPro\App\Http\Controllers\LicenseController',
				'deactivateLicense',
				is_array( $input ) ? $input : array()
			);
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			return array(
				'success' => true,
				'message' => is_array( $result ) ? ( $result['message'] ?? 'License deactivated.' ) : 'License deactivated.',
			);
		},
	) );
}
add_action( 'wp_abilities_api_init', 'fluent_abilities_player_register_license_abilities', 100 );
