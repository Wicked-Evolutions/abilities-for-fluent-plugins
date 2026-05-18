<?php
/**
 * FluentBooking — Global settings + onboarding (cluster 4.8).
 *
 * Wraps SettingsController.php (global FluentBooking admin settings) and
 * OnboardingService.php (per-install onboarding state). Settings storage is the
 * `__fluent_booking_global_settings` option (verified via SettingsController).
 *
 *   - fluent-booking/get-global-settings        (read)
 *   - fluent-booking/update-global-settings     (write)
 *   - fluent-booking/get-onboarding-state       (read)
 *   - fluent-booking/update-onboarding-state    (write)
 *
 * Capability override: manage_options.
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

function fluent_booking_register_global_settings_abilities() {

	$reg = new Fluent_Abilities_Registrar( 'booking' );

	// =========================================================================
	// 4.8.1 — GET GLOBAL SETTINGS
	// =========================================================================

	$reg->read( 'fluent-booking/get-global-settings', array(
		'label'       => 'Get FluentBooking Global Settings',
		'description' => 'Return the FluentBooking global settings object (date format, time format, week start, default availability, branding, etc.).',
		'capability'  => 'manage_options',
		'output_schema' => fluent_abilities_schema_item_object_output(),
		'callback' => function( $input ) {
			// Data-missing root: the `__fluent_booking_global_settings` option
			// is unused/empty — FluentBooking persists settings in
			// `_fluent_booking_settings` and exposes them through the documented
			// vendor accessor Helper::getGlobalSettings(), which merges the
			// stored option with the full default object (currency/emailing/
			// administration/time_format/theme). Installed source:
			// fluent-booking/app/Services/Helper.php:2092 (reads
			// get_option('_fluent_booking_settings') at :2123). Route through
			// the vendor accessor rather than the wrong raw option.
			if ( ! class_exists( '\FluentBooking\App\Services\Helper' )
				|| ! method_exists( '\FluentBooking\App\Services\Helper', 'getGlobalSettings' ) ) {
				return fluent_abilities_error( 'vendor_helper_unavailable', 'FluentBooking Helper::getGlobalSettings is not available. FluentBooking must be active for this ability.' );
			}
			$settings = \FluentBooking\App\Services\Helper::getGlobalSettings();
			return array( 'settings' => fluent_abilities_safe_array( is_array( $settings ) ? $settings : array() ) );
		},
	) );

	// =========================================================================
	// 4.8.2 — UPDATE GLOBAL SETTINGS
	// =========================================================================

	$reg->write( 'fluent-booking/update-global-settings', array(
		'label'       => 'Update FluentBooking Global Settings',
		'description' => 'Partial-merge update of the FluentBooking global settings object. Top-level keys provided in input replace their equivalents on the stored option; omitted keys are preserved.',
		'capability'  => 'manage_options',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'settings' ),
			'properties' => array(
				'settings' => array(
					'type'        => array( 'object', 'array' ),
					'description' => 'Partial settings object (e.g. { "date_format": "Y-m-d", "branding": { ... } })',
				),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback' => function( $input ) {
			$current = get_option( '__fluent_booking_global_settings', array() );
			$current = is_array( $current ) ? $current : array();

			$partial = isset( $input['settings'] ) ? (array) $input['settings'] : array();
			$merged  = array_replace_recursive( $current, $partial );

			update_option( '__fluent_booking_global_settings', $merged );

			return array( 'success' => true );
		},
	) );

	// =========================================================================
	// 4.8.3 — GET ONBOARDING STATE
	// =========================================================================

	$reg->read( 'fluent-booking/get-onboarding-state', array(
		'label'       => 'Get FluentBooking Onboarding State',
		'description' => 'Return the per-install onboarding state (step, completed, skipped).',
		'capability'  => 'manage_options',
		'output_schema' => fluent_abilities_schema_item_output( array(
			'state' => array( 'type' => array( 'object', 'array', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$state = get_option( '__fluent_booking_pro_onboarding_state', null );
			if ( $state === null ) {
				$state = get_option( '__fluent_booking_onboarding_state', null );
			}
			return array( 'state' => $state === null ? null : fluent_abilities_safe_array( $state ) );
		},
	) );

	// =========================================================================
	// 4.8.4 — UPDATE ONBOARDING STATE
	// =========================================================================

	$reg->write( 'fluent-booking/update-onboarding-state', array(
		'label'       => 'Update FluentBooking Onboarding State',
		'description' => 'Replace the per-install onboarding state object.',
		'capability'  => 'manage_options',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'state' ),
			'properties' => array(
				'state' => array( 'type' => array( 'object', 'array' ) ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback' => function( $input ) {
			$state = isset( $input['state'] ) ? (array) $input['state'] : array();
			update_option( '__fluent_booking_onboarding_state', $state );
			return array( 'success' => true );
		},
	) );

}
add_action( 'wp_abilities_api_init', 'fluent_booking_register_global_settings_abilities' );

/**
 * Output schema helper used only by the settings cluster — a free-form object
 * envelope with a single `settings` key.
 *
 * @return array
 */
function fluent_abilities_schema_item_object_output() {
	return fluent_abilities_schema_item_output( array(
		'settings' => array( 'type' => array( 'object', 'array' ) ),
	) );
}
