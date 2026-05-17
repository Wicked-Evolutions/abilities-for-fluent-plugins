<?php
/**
 * FluentCart Abilities — Settings (six-key surface) (v2.0.0)
 *
 * Adds cluster 4.16 from FluentCart Ability Registrar Research v1.0
 * (2026-05-13) — 12 abilities. Existing v1.1.3 `get-settings` continues
 * to return the legacy `fluent_cart_settings` option; this cluster adds
 * the six domain-scoped sub-surfaces (store / permissions / modules /
 * payment-methods / confirmation / storage-drivers).
 *
 * All 12 abilities require manage_options.
 *
 * @package Fluent_Abilities
 * @since 2.0.0
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {

	$reg = new Fluent_Abilities_Registrar( 'cart' );

	$settings_read_args = function ( $option_key, $description ) {
		return array(
			'label'        => 'Get Settings',
			'description'  => $description . ' Returns the stored option.',
			'input_schema' => array( 'type' => 'object', 'properties' => array() ),
			'output_schema' => fluent_abilities_schema_item_output( array(
				'settings' => array( 'type' => 'object' ),
			) ),
			'capability' => 'manage_options',
			'callback'   => function ( $input ) use ( $option_key ) {
				$settings = get_option( $option_key, array() );
				if ( ! is_array( $settings ) ) {
					$settings = array();
				}
				return array( 'settings' => $settings );
			},
		);
	};

	$settings_write_args = function ( $option_key, $description ) {
		return array(
			'label'        => 'Update Settings',
			'description'  => $description . ' Merges provided fields into the stored option.',
			'input_schema' => array(
				'type'     => 'object',
				'required' => array( 'settings' ),
				'properties' => array(
					'settings' => array( 'type' => 'object', 'description' => 'Fields to merge into option ' . $option_key ),
				),
			),
			'output_schema' => fluent_abilities_schema_success_output( array(
				'option_key' => array( 'type' => 'string' ),
			) ),
			'capability' => 'manage_options',
			'callback'   => function ( $input ) use ( $option_key ) {
				$existing = get_option( $option_key, array() );
				if ( ! is_array( $existing ) ) {
					$existing = array();
				}
				$merged = array_merge( $existing, (array) $input['settings'] );
				update_option( $option_key, $merged );
				return array( 'success' => true, 'option_key' => $option_key );
			},
		);
	};

	// 4.16.1 / 4.16.2 — Store settings.
	$reg->read(
		'fluent-cart/get-store-settings',
		$settings_read_args( 'fluent_cart_settings', 'Store-level settings: currency, branding, slugs. Mirrors GET /settings/store.' )
	);
	$reg->write(
		'fluent-cart/update-store-settings',
		$settings_write_args( 'fluent_cart_settings', 'Store-level settings. Mirrors POST /settings/store.' )
	);

	// 4.16.3 / 4.16.4 — Permission settings.
	$reg->read(
		'fluent-cart/get-permission-settings',
		$settings_read_args( 'fluent_cart_permission_settings', 'Role / permission settings. Mirrors GET /settings/permissions.' )
	);
	$reg->write(
		'fluent-cart/update-permission-settings',
		$settings_write_args( 'fluent_cart_permission_settings', 'Role / permission settings. Mirrors POST /settings/permissions.' )
	);

	// 4.16.5 / 4.16.6 — Module toggles.
	$reg->read(
		'fluent-cart/get-module-settings',
		$settings_read_args( 'fluent_cart_modules', 'Module enable/disable toggles. Mirrors GET /settings/modules.' )
	);
	$reg->write(
		'fluent-cart/update-module-settings',
		$settings_write_args( 'fluent_cart_modules', 'Module enable/disable toggles. Mirrors POST /settings/modules.' )
	);

	// 4.16.9 / 4.16.10 — Confirmation pages.
	$reg->read(
		'fluent-cart/get-confirmation-settings',
		$settings_read_args( 'fluent_cart_confirmation_settings', 'Order confirmation page + shortcode config. Mirrors GET /settings/confirmation/shortcode.' )
	);
	$reg->write(
		'fluent-cart/update-confirmation-settings',
		$settings_write_args( 'fluent_cart_confirmation_settings', 'Order confirmation page + shortcode config. Mirrors POST /settings/confirmation.' )
	);

	// 4.16.7 — List payment methods (read).
	$reg->read( 'fluent-cart/list-payment-methods', array(
		'label'       => 'List Payment Methods',
		'description' => 'List configured payment methods. Mirrors GET /settings/payment-methods.',
		'input_schema' => array( 'type' => 'object', 'properties' => array() ),
		'output_schema' => fluent_abilities_schema_collection_output( 'payment_methods', array(
			'key'      => array( 'type' => 'string' ),
			'label'    => array( 'type' => array( 'string', 'null' ) ),
			'enabled'  => array( 'type' => 'boolean' ),
			'settings' => array( 'type' => 'object' ),
		) ),
		'capability' => 'manage_options',
		'callback'   => function( $input ) {
			$option = get_option( 'fluent_cart_payment_methods', array() );
			if ( ! is_array( $option ) ) {
				$option = array();
			}
			$items = array();
			foreach ( $option as $key => $cfg ) {
				$items[] = array(
					'key'      => (string) $key,
					'label'    => isset( $cfg['label'] ) ? (string) $cfg['label'] : null,
					'enabled'  => ! empty( $cfg['enabled'] ),
					'settings' => is_array( $cfg ) ? $cfg : array(),
				);
			}
			return array( 'payment_methods' => $items, 'total' => count( $items ) );
		},
	) );

	// 4.16.8 — Update payment method (write).
	$reg->write( 'fluent-cart/update-payment-method', array(
		'label'       => 'Update Payment Method',
		'description' => 'Update a single payment-method configuration. Note: the method is addressed by `key` (the method slug such as "stripe"/"paypal", used as the array key in the fluent_cart_payment_methods option) — there is no numeric `id` for a payment method; passing `id` has no effect. Mirrors POST /settings/payment-methods.',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'key', 'settings' ),
			'properties' => array(
				'key'      => array( 'type' => 'string', 'description' => 'Payment method slug (stripe, paypal, ...)' ),
				'settings' => array( 'type' => 'object', 'description' => 'Method-specific config (api keys, modes, etc.)' ),
				'enabled'  => array( 'type' => 'boolean' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'key' => array( 'type' => 'string' ),
		) ),
		'capability' => 'manage_options',
		'callback'   => function( $input ) {
			$option = get_option( 'fluent_cart_payment_methods', array() );
			if ( ! is_array( $option ) ) {
				$option = array();
			}
			$key = sanitize_text_field( $input['key'] );
			$current = isset( $option[ $key ] ) && is_array( $option[ $key ] ) ? $option[ $key ] : array();
			$current = array_merge( $current, (array) $input['settings'] );
			if ( isset( $input['enabled'] ) ) {
				$current['enabled'] = (bool) $input['enabled'];
			}
			$option[ $key ] = $current;
			update_option( 'fluent_cart_payment_methods', $option );
			return array( 'success' => true, 'key' => $key );
		},
	) );

	// 4.16.11 — List storage drivers (read).
	$reg->read( 'fluent-cart/list-storage-drivers', array(
		'label'       => 'List Storage Drivers',
		'description' => 'List configured storage drivers (R2 / S3 / Bunny / Stripe File Vault / etc.). Mirrors GET /settings/storage-drivers.',
		'input_schema' => array( 'type' => 'object', 'properties' => array() ),
		'output_schema' => fluent_abilities_schema_collection_output( 'drivers', array(
			'key'      => array( 'type' => 'string' ),
			'label'    => array( 'type' => array( 'string', 'null' ) ),
			'enabled'  => array( 'type' => 'boolean' ),
			'settings' => array( 'type' => 'object' ),
		) ),
		'capability' => 'manage_options',
		'callback'   => function( $input ) {
			$option = get_option( 'fluent_cart_storage_drivers', array() );
			if ( ! is_array( $option ) ) {
				$option = array();
			}
			$items = array();
			foreach ( $option as $key => $cfg ) {
				$items[] = array(
					'key'      => (string) $key,
					'label'    => isset( $cfg['label'] ) ? (string) $cfg['label'] : null,
					'enabled'  => ! empty( $cfg['enabled'] ),
					'settings' => is_array( $cfg ) ? $cfg : array(),
				);
			}
			return array( 'drivers' => $items, 'total' => count( $items ) );
		},
	) );

	// 4.16.12 — Update storage driver (write, complex per-driver auth).
	$reg->write( 'fluent-cart/update-storage-driver', array(
		'label'       => 'Update Storage Driver',
		'description' => 'Update a single storage driver configuration. Per-driver schema (bucket / region / access keys). Note: the driver is addressed by `key` (the driver slug such as "s3"/"r2"/"bunny", used as the array key in the fluent_cart_storage_drivers option) — the field is `key`, not `driver`; passing `driver` has no effect. Mirrors POST /settings/storage-drivers.',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'key', 'settings' ),
			'properties' => array(
				'key'      => array( 'type' => 'string', 'description' => 'Driver slug (s3, r2, bunny, stripe-vault, ...)' ),
				'settings' => array( 'type' => 'object', 'description' => 'Driver-specific config' ),
				'enabled'  => array( 'type' => 'boolean' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'key' => array( 'type' => 'string' ),
		) ),
		'capability' => 'manage_options',
		'callback'   => function( $input ) {
			$option = get_option( 'fluent_cart_storage_drivers', array() );
			if ( ! is_array( $option ) ) {
				$option = array();
			}
			$key = sanitize_text_field( $input['key'] );
			$current = isset( $option[ $key ] ) && is_array( $option[ $key ] ) ? $option[ $key ] : array();
			$current = array_merge( $current, (array) $input['settings'] );
			if ( isset( $input['enabled'] ) ) {
				$current['enabled'] = (bool) $input['enabled'];
			}
			$option[ $key ] = $current;
			update_option( 'fluent_cart_storage_drivers', $option );
			return array( 'success' => true, 'key' => $key );
		},
	) );

	$count = 12;
	error_log( "Abilities for Fluent: Registered {$count} Cart Settings abilities" );

}, 100 );
