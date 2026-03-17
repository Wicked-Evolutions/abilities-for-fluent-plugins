<?php
/**
 * FluentAffiliate Abilities — Settings
 *
 * Referral configuration, email settings, integrations, and registration fields.
 *
 * 10 abilities in the 'fluent-affiliate' category.
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {

	$reg = new Fluent_Abilities_Registrar( 'affiliate' );

	// =========================================================================
	// REFERRAL SETTINGS
	// =========================================================================

	$reg->read( 'fluent-affiliate/get-referral-settings', array(
		'label'       => 'Get Referral Settings',
		'description' => 'Get the global referral configuration: default rate, rate type, cookie duration, tracking variable, and self-referral setting.',
		'category'    => 'fluent-affiliate',
		'callback'    => function() {
			$settings = \FluentAffiliate\App\Helper\Utility::getReferralSettings();

			return array(
				'referral_variable'     => $settings['referral_variable'] ?? 'ref',
				'rate_type'             => $settings['rate_type'] ?? 'percentage',
				'rate'                  => (float) ( $settings['rate'] ?? 0 ),
				'cookie_duration'       => (int) ( $settings['cookie_duration'] ?? 30 ),
				'disable_self_referral' => ! empty( $settings['disable_self_referral'] ),
				'exclude_shipping'      => ! empty( $settings['exclude_shipping'] ),
				'exclude_tax'           => ! empty( $settings['exclude_tax'] ),
				'exclude_discount'      => ! empty( $settings['exclude_discount'] ),
				'currency'              => \FluentAffiliate\App\Helper\Utility::getCurrency() ?? '',
			);
		},
	) );

	$reg->write( 'fluent-affiliate/update-referral-settings', array(
		'label'       => 'Update Referral Settings',
		'description' => 'Update global referral configuration: default rate, rate type, cookie duration, tracking variable. Changes affect all new referrals.',
		'category'    => 'fluent-affiliate',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'rate' => array(
					'type'        => 'number',
					'description' => 'Default commission rate (e.g. 25 for 25%)',
				),
				'rate_type' => array(
					'type'        => 'string',
					'description' => 'Default rate type: percentage or flat',
				),
				'cookie_duration' => array(
					'type'        => 'integer',
					'description' => 'Cookie duration in days',
				),
				'referral_variable' => array(
					'type'        => 'string',
					'description' => 'URL query parameter for affiliate tracking (default: ref)',
				),
				'disable_self_referral' => array(
					'type'        => 'boolean',
					'description' => 'Prevent affiliates from earning commissions on their own purchases',
				),
				'exclude_shipping' => array(
					'type'        => 'boolean',
					'description' => 'Exclude shipping from commission calculation',
				),
				'exclude_tax' => array(
					'type'        => 'boolean',
					'description' => 'Exclude tax from commission calculation',
				),
				'exclude_discount' => array(
					'type'        => 'boolean',
					'description' => 'Exclude discount from commission calculation',
				),
			),
		),
		'callback' => function( $input ) {
			$current = \FluentAffiliate\App\Helper\Utility::getReferralSettings();

			$update = $current;
			if ( isset( $input['rate'] ) ) {
				$update['rate'] = (float) $input['rate'];
			}
			if ( isset( $input['rate_type'] ) ) {
				$update['rate_type'] = sanitize_text_field( $input['rate_type'] );
			}
			if ( isset( $input['cookie_duration'] ) ) {
				$update['cookie_duration'] = (int) $input['cookie_duration'];
			}
			if ( isset( $input['referral_variable'] ) ) {
				$update['referral_variable'] = sanitize_text_field( $input['referral_variable'] );
			}
			if ( isset( $input['disable_self_referral'] ) ) {
				$update['disable_self_referral'] = $input['disable_self_referral'] ? 'yes' : 'no';
			}
			if ( isset( $input['exclude_shipping'] ) ) {
				$update['exclude_shipping'] = $input['exclude_shipping'] ? 'yes' : 'no';
			}
			if ( isset( $input['exclude_tax'] ) ) {
				$update['exclude_tax'] = $input['exclude_tax'] ? 'yes' : 'no';
			}
			if ( isset( $input['exclude_discount'] ) ) {
				$update['exclude_discount'] = $input['exclude_discount'] ? 'yes' : 'no';
			}

			\FluentAffiliate\App\Helper\Utility::updateReferralSettings( $update );

			return array(
				'success' => true,
				'message' => 'Referral settings updated.',
			);
		},
	) );

	// =========================================================================
	// EMAIL CONFIGURATION
	// =========================================================================

	$reg->read( 'fluent-affiliate/get-email-config', array(
		'label'       => 'Get Email Configuration',
		'description' => 'Get the FluentAffiliate email notification configuration.',
		'category'    => 'fluent-affiliate',
		'callback'    => function() {
			$config = fluentAffiliate_get_option( 'email_config', array() );
			$config = is_array( $config ) ? $config : array();

			return array(
				'from_name'      => $config['from_name'] ?? '',
				'from_email'     => $config['from_email'] ?? '',
				'admin_email'    => $config['admin_email'] ?? '',
				'notifications'  => $config['notifications'] ?? array(),
			);
		},
	) );

	$reg->write( 'fluent-affiliate/update-email-config', array(
		'label'       => 'Update Email Configuration',
		'description' => 'Update email notification settings: from name, from email, admin email.',
		'category'    => 'fluent-affiliate',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'from_name' => array(
					'type'        => 'string',
					'description' => 'Sender name for notification emails',
				),
				'from_email' => array(
					'type'        => 'string',
					'description' => 'Sender email for notifications',
				),
				'admin_email' => array(
					'type'        => 'string',
					'description' => 'Admin notification email address',
				),
			),
		),
		'callback' => function( $input ) {
			$current = fluentAffiliate_get_option( 'email_config', array() );
			$current = is_array( $current ) ? $current : array();

			if ( isset( $input['from_name'] ) ) {
				$current['from_name'] = sanitize_text_field( $input['from_name'] );
			}
			if ( isset( $input['from_email'] ) ) {
				$current['from_email'] = sanitize_email( $input['from_email'] );
			}
			if ( isset( $input['admin_email'] ) ) {
				$current['admin_email'] = sanitize_email( $input['admin_email'] );
			}

			fluentAffiliate_update_option( 'email_config', $current );

			return array(
				'success' => true,
				'message' => 'Email configuration updated.',
			);
		},
	) );

	$reg->read( 'fluent-affiliate/list-email-templates', array(
		'label'       => 'List Notification Email Templates',
		'description' => 'Get the notification email templates configured in FluentAffiliate.',
		'category'    => 'fluent-affiliate',
		'callback'    => function() {
			$emails = fluentAffiliate_get_option( 'notification_emails', array() );
			$emails = is_array( $emails ) ? $emails : array();

			$items = array();
			foreach ( $emails as $key => $email ) {
				$items[] = array(
					'key'     => $key,
					'subject' => is_array( $email ) ? ( $email['subject'] ?? '' ) : '',
					'enabled' => is_array( $email ) ? ! empty( $email['enabled'] ) : false,
					'type'    => is_array( $email ) ? ( $email['type'] ?? '' ) : '',
				);
			}

			return array(
				'templates' => $items,
				'total'     => count( $items ),
			);
		},
	) );

	// =========================================================================
	// INTEGRATIONS
	// =========================================================================

	$reg->read( 'fluent-affiliate/list-integrations', array(
		'label'       => 'List Integrations',
		'description' => 'List all available FluentAffiliate integration connectors and their enabled/disabled status.',
		'category'    => 'fluent-affiliate',
		'callback'    => function() {
			$integrations = apply_filters( 'fluent_affiliate/get_integrations', array() );

			$items = array();
			foreach ( $integrations as $key => $integration ) {
				$is_enabled = \FluentAffiliate\App\Helper\Utility::isConnectorEnabled( $key );
				$items[] = array(
					'key'         => is_string( $key ) ? $key : ( $integration['key'] ?? '' ),
					'title'       => $integration['title'] ?? '',
					'description' => $integration['description'] ?? '',
					'enabled'     => $is_enabled,
					'is_pro'      => ! empty( $integration['is_pro'] ),
				);
			}

			return array(
				'integrations' => $items,
				'total'        => count( $items ),
			);
		},
	) );

	$reg->read( 'fluent-affiliate/get-integration-config', array(
		'label'       => 'Get Integration Config',
		'description' => 'Get the configuration for a specific FluentAffiliate integration.',
		'category'    => 'fluent-affiliate',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'key' => array(
					'type'        => 'string',
					'description' => 'Integration key (e.g., woo, fluentcart, edd)',
				),
			),
			'required' => array( 'key' ),
		),
		'callback' => function( $input ) {
			$key    = sanitize_text_field( $input['key'] );
			$config = fluentAffiliate_get_option( 'integration_' . $key, array() );
			$config = is_array( $config ) ? $config : array();

			$is_enabled = \FluentAffiliate\App\Helper\Utility::isConnectorEnabled( $key );

			return array(
				'key'     => $key,
				'enabled' => $is_enabled,
				'config'  => $config,
			);
		},
	) );

	$reg->write( 'fluent-affiliate/update-integration-status', array(
		'label'       => 'Update Integration Status',
		'description' => 'Enable or disable a FluentAffiliate integration connector.',
		'category'    => 'fluent-affiliate',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'key' => array(
					'type'        => 'string',
					'description' => 'Integration key (e.g., woo, fluentcart, edd)',
				),
				'enabled' => array(
					'type'        => 'boolean',
					'description' => 'True to enable, false to disable',
				),
			),
			'required' => array( 'key', 'enabled' ),
		),
		'callback' => function( $input ) {
			$key     = sanitize_text_field( $input['key'] );
			$enabled = ! empty( $input['enabled'] );

			$statuses = fluentAffiliate_get_option( 'integration_statuses', array() );
			$statuses = is_array( $statuses ) ? $statuses : array();

			$statuses[ $key ] = $enabled ? 'yes' : 'no';
			fluentAffiliate_update_option( 'integration_statuses', $statuses );

			return array(
				'success' => true,
				'key'     => $key,
				'enabled' => $enabled,
				'message' => 'Integration ' . ( $enabled ? 'enabled' : 'disabled' ) . '.',
			);
		},
	) );

	// =========================================================================
	// REGISTRATION
	// =========================================================================

	$reg->read( 'fluent-affiliate/get-registration-fields', array(
		'label'       => 'Get Registration Form Fields',
		'description' => 'Get the affiliate signup registration form field configuration.',
		'category'    => 'fluent-affiliate',
		'callback'    => function() {
			$fields = fluentAffiliate_get_option( 'registration_fields', array() );
			$fields = is_array( $fields ) ? $fields : array();

			return array(
				'fields' => $fields,
				'total'  => count( $fields ),
			);
		},
	) );

} );
