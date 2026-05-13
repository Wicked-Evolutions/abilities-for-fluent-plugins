<?php
/**
 * FluentBooking — Pro License management (cluster 4.18).
 *
 * Wraps fluent-booking-pro/app/Http/Controllers/LicenseController.php and
 * fluent-booking-pro/app/Services/PluginManager/FluentLicensing.php.
 *
 *   - fluent-booking/get-license-info       (read)
 *   - fluent-booking/activate-license       (write)
 *   - fluent-booking/deactivate-license     (write)
 *
 * Capability override: manage_options (license keys are admin-only).
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

function fluent_booking_register_license_abilities() {

	$reg = new Fluent_Abilities_Registrar( 'booking' );

	// =========================================================================
	// 4.18.1 — GET LICENSE INFO
	// =========================================================================

	$reg->read( 'fluent-booking/get-license-info', array(
		'label'       => 'Get FluentBooking Pro License Info',
		'description' => 'Return the FluentBooking Pro license status and metadata (license key, status, expiry, customer, last check).',
		'capability'  => 'manage_options',
		'output_schema' => fluent_abilities_schema_item_output( array(
			'license_key'      => array( 'type' => array( 'string', 'null' ) ),
			'status'           => array( 'type' => array( 'string', 'null' ) ),
			'expires'          => array( 'type' => array( 'string', 'null' ) ),
			'customer_email'   => array( 'type' => array( 'string', 'null' ) ),
			'customer_name'    => array( 'type' => array( 'string', 'null' ) ),
			'is_active'        => array( 'type' => 'boolean' ),
			'last_checked_at'  => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$info = get_option( '__fluent_booking_pro_license', array() );
			if ( ! is_array( $info ) ) {
				$info = array();
			}

			$status = (string) ( $info['status'] ?? '' );

			return array(
				'license_key'     => isset( $info['license'] ) ? (string) $info['license'] : ( isset( $info['key'] ) ? (string) $info['key'] : null ),
				'status'          => $status !== '' ? $status : null,
				'expires'         => isset( $info['expires'] ) ? (string) $info['expires'] : null,
				'customer_email'  => isset( $info['customer_email'] ) ? (string) $info['customer_email'] : null,
				'customer_name'   => isset( $info['customer_name'] ) ? (string) $info['customer_name'] : null,
				'is_active'       => $status === 'valid' || $status === 'active',
				'last_checked_at' => isset( $info['last_checked_at'] ) ? (string) $info['last_checked_at'] : null,
			);
		},
	) );

	// =========================================================================
	// 4.18.2 — ACTIVATE LICENSE
	// =========================================================================

	$reg->write( 'fluent-booking/activate-license', array(
		'label'       => 'Activate FluentBooking Pro License',
		'description' => 'Activate a FluentBooking Pro license key against the licensing endpoint.',
		'capability'  => 'manage_options',
		'annotations' => array( 'idempotent' => true ),
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'license_key' ),
			'properties' => array(
				'license_key' => array( 'type' => 'string', 'description' => 'License key to activate' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'status'  => array( 'type' => array( 'string', 'null' ) ),
			'message' => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$key = sanitize_text_field( $input['license_key'] );
			if ( $key === '' ) {
				return fluent_abilities_error( 'ability_invalid_input', 'license_key is required' );
			}

			if ( ! class_exists( '\FluentBookingPro\App\Services\PluginManager\FluentLicensing' ) ) {
				return fluent_abilities_error( 'missing_class', 'FluentBooking Pro FluentLicensing service not found (Pro plugin not installed?)' );
			}

			$licensing = new \FluentBookingPro\App\Services\PluginManager\FluentLicensing();

			$result = null;
			if ( method_exists( $licensing, 'activateLicense' ) ) {
				$result = $licensing->activateLicense( $key );
			} elseif ( method_exists( $licensing, 'activate' ) ) {
				$result = $licensing->activate( $key );
			} else {
				return fluent_abilities_error( 'method_not_found', 'No activate method found on FluentLicensing service' );
			}

			if ( is_wp_error( $result ) ) {
				return fluent_abilities_error( $result->get_error_code() ?: 'activation_failed', $result->get_error_message() );
			}

			$info = get_option( '__fluent_booking_pro_license', array() );

			return array(
				'success' => true,
				'status'  => isset( $info['status'] ) ? (string) $info['status'] : null,
				'message' => is_string( $result ) ? $result : null,
			);
		},
	) );

	// =========================================================================
	// 4.18.3 — DEACTIVATE LICENSE
	// =========================================================================

	$reg->write( 'fluent-booking/deactivate-license', array(
		'label'       => 'Deactivate FluentBooking Pro License',
		'description' => 'Deactivate the currently-stored FluentBooking Pro license.',
		'capability'  => 'manage_options',
		'output_schema' => fluent_abilities_schema_success_output( array(
			'message' => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			if ( ! class_exists( '\FluentBookingPro\App\Services\PluginManager\FluentLicensing' ) ) {
				return fluent_abilities_error( 'missing_class', 'FluentBooking Pro FluentLicensing service not found' );
			}

			$licensing = new \FluentBookingPro\App\Services\PluginManager\FluentLicensing();
			$result    = null;
			if ( method_exists( $licensing, 'deactivateLicense' ) ) {
				$result = $licensing->deactivateLicense();
			} elseif ( method_exists( $licensing, 'deactivate' ) ) {
				$result = $licensing->deactivate();
			} else {
				return fluent_abilities_error( 'method_not_found', 'No deactivate method found on FluentLicensing service' );
			}

			if ( is_wp_error( $result ) ) {
				return fluent_abilities_error( $result->get_error_code() ?: 'deactivation_failed', $result->get_error_message() );
			}

			return array(
				'success' => true,
				'message' => is_string( $result ) ? $result : null,
			);
		},
	) );

}
add_action( 'wp_abilities_api_init', 'fluent_booking_register_license_abilities' );
