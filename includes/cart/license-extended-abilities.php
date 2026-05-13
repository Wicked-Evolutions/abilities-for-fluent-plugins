<?php
/**
 * FluentCart Abilities — License Key Management + Site Activation (v2.0.0, Pro)
 *
 * Adds clusters 4.14 (7) and 4.15 (4) from FluentCart Ability Registrar
 * Research v1.0 (2026-05-13) — 11 abilities. All require FluentCart Pro.
 *
 * @package Fluent_Abilities
 * @since 2.0.0
 */

defined( 'ABSPATH' ) || exit;

// Pro gate: skip if FluentCart Pro Licensing module isn't loaded.
if ( ! class_exists( '\\FluentCartPro\\App\\Modules\\Licensing\\Models\\License' ) ) {
	return;
}

add_action( 'wp_abilities_api_init', function() {

	$reg = new Fluent_Abilities_Registrar( 'cart' );

	// =========================================================================
	// 4.14 LICENSE KEY MANAGEMENT (7)
	// =========================================================================

	$reg->read( 'fluent-cart/get-license', array(
		'label'       => 'Get License',
		'description' => 'Get a single license by ID, including prior orders. Mirrors LicenseController::getLicense.',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'id'               => array( 'type' => 'integer' ),
			'license_key'      => array( 'type' => array( 'string', 'null' ) ),
			'status'           => array( 'type' => array( 'string', 'null' ) ),
			'limit'            => array( 'type' => 'integer' ),
			'activation_count' => array( 'type' => 'integer' ),
			'product_id'       => array( 'type' => 'integer' ),
			'variation_id'     => array( 'type' => 'integer' ),
			'order_id'         => array( 'type' => 'integer' ),
			'customer_id'      => array( 'type' => 'integer' ),
			'subscription_id'  => array( 'type' => 'integer' ),
			'expiration_date'  => array( 'type' => array( 'string', 'null' ) ),
			'created_at'       => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$license = \FluentCartPro\App\Modules\Licensing\Models\License::find( (int) $input['id'] );
			if ( ! $license ) {
				return fluent_abilities_error( 'not_found', 'License not found.' );
			}
			return array(
				'id'               => (int) $license->id,
				'license_key'      => $license->license_key ?? null,
				'status'           => $license->status ?? null,
				'limit'            => (int) ( $license->limit ?? 0 ),
				'activation_count' => (int) ( $license->activation_count ?? 0 ),
				'product_id'       => (int) ( $license->product_id ?? 0 ),
				'variation_id'     => (int) ( $license->variation_id ?? 0 ),
				'order_id'         => (int) ( $license->order_id ?? 0 ),
				'customer_id'      => (int) ( $license->customer_id ?? 0 ),
				'subscription_id'  => (int) ( $license->subscription_id ?? 0 ),
				'expiration_date'  => $license->expiration_date ?? null,
				'created_at'       => $license->created_at ? (string) $license->created_at : null,
			);
		},
	) );

	$reg->read( 'fluent-cart/get-customer-licenses', array(
		'label'       => 'Get Customer Licenses',
		'description' => 'List licenses for one customer. Mirrors LicenseController::getCustomerLicenses.',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'customer_id' ),
			'properties' => array_merge( array(
				'customer_id' => array( 'type' => 'integer' ),
				'status'      => array( 'type' => 'string' ),
			), fluent_abilities_pagination_schema() ),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'licenses', array(
			'id'              => array( 'type' => 'integer' ),
			'license_key'     => array( 'type' => array( 'string', 'null' ) ),
			'status'          => array( 'type' => array( 'string', 'null' ) ),
			'product_id'      => array( 'type' => 'integer' ),
			'expiration_date' => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$pagination = fluent_abilities_pagination( $input );
			$query = \FluentCartPro\App\Modules\Licensing\Models\License::where( 'customer_id', (int) $input['customer_id'] );
			if ( ! empty( $input['status'] ) ) {
				$query->where( 'status', sanitize_text_field( $input['status'] ) );
			}
			$total = $query->count();
			$rows  = $query->orderBy( 'id', 'DESC' )
				->offset( $pagination['offset'] )->limit( $pagination['per_page'] )->get();
			$items = array();
			foreach ( $rows as $l ) {
				$items[] = array(
					'id'              => (int) $l->id,
					'license_key'     => $l->license_key ?? null,
					'status'          => $l->status ?? null,
					'product_id'      => (int) ( $l->product_id ?? 0 ),
					'expiration_date' => $l->expiration_date ?? null,
				);
			}
			return array(
				'licenses' => $items,
				'total'    => $total,
				'page'     => $pagination['page'],
				'per_page' => $pagination['per_page'],
			);
		},
	) );

	$reg->write( 'fluent-cart/regenerate-license-key', array(
		'label'       => 'Regenerate License Key',
		'description' => 'Generate a new license_key for a license, invalidating the previous one. Mirrors LicenseController::regenerateLicenseKey.',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'          => array( 'type' => 'integer' ),
			'license_key' => array( 'type' => 'string' ),
		) ),
		'annotations' => array( 'idempotent' => false ),
		'callback'    => function( $input ) {
			$license = \FluentCartPro\App\Modules\Licensing\Models\License::find( (int) $input['id'] );
			if ( ! $license ) {
				return fluent_abilities_error( 'not_found', 'License not found.' );
			}
			$license->license_key = strtoupper( wp_generate_password( 32, false ) );
			$license->save();
			do_action( 'fluent_cart_sl/license_regenerated', $license );
			return array( 'success' => true, 'id' => (int) $license->id, 'license_key' => (string) $license->license_key );
		},
	) );

	$reg->write( 'fluent-cart/extend-license-validity', array(
		'label'       => 'Extend License Validity',
		'description' => 'Set expiration_date for a license. Accepts a YYYY-MM-DD date or the literal \'lifetime\'. Mirrors LicenseController::extendValidity.',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'id', 'expiration_date' ),
			'properties' => array(
				'id'              => array( 'type' => 'integer' ),
				'expiration_date' => array( 'type' => 'string', 'description' => 'YYYY-MM-DD or "lifetime"' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'              => array( 'type' => 'integer' ),
			'expiration_date' => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$license = \FluentCartPro\App\Modules\Licensing\Models\License::find( (int) $input['id'] );
			if ( ! $license ) {
				return fluent_abilities_error( 'not_found', 'License not found.' );
			}
			$expires = sanitize_text_field( $input['expiration_date'] );
			$license->expiration_date = ( 'lifetime' === strtolower( $expires ) ) ? null : $expires;
			$license->save();
			return array( 'success' => true, 'id' => (int) $license->id, 'expiration_date' => $license->expiration_date );
		},
	) );

	$reg->write( 'fluent-cart/update-license-status', array(
		'label'       => 'Update License Status',
		'description' => 'Set license status. Valid: disabled, active, expired. Mirrors LicenseController::updateStatus.',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'id', 'status' ),
			'properties' => array(
				'id'     => array( 'type' => 'integer' ),
				'status' => array( 'type' => 'string', 'enum' => array( 'disabled', 'active', 'expired' ) ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'     => array( 'type' => 'integer' ),
			'status' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$license = \FluentCartPro\App\Modules\Licensing\Models\License::find( (int) $input['id'] );
			if ( ! $license ) {
				return fluent_abilities_error( 'not_found', 'License not found.' );
			}
			$license->status = sanitize_text_field( $input['status'] );
			$license->save();
			return array( 'success' => true, 'id' => (int) $license->id, 'status' => (string) $license->status );
		},
	) );

	$reg->write( 'fluent-cart/update-license-limit', array(
		'label'       => 'Update License Activation Limit',
		'description' => 'Change the activation limit (number of allowed sites) for a license. Mirrors LicenseController::updateLimit.',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'id', 'limit' ),
			'properties' => array(
				'id'    => array( 'type' => 'integer' ),
				'limit' => array( 'type' => 'integer', 'description' => 'New activation limit (0 = unlimited)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'    => array( 'type' => 'integer' ),
			'limit' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$license = \FluentCartPro\App\Modules\Licensing\Models\License::find( (int) $input['id'] );
			if ( ! $license ) {
				return fluent_abilities_error( 'not_found', 'License not found.' );
			}
			$license->limit = max( 0, (int) $input['limit'] );
			$license->save();
			return array( 'success' => true, 'id' => (int) $license->id, 'limit' => (int) $license->limit );
		},
	) );

	$reg->delete( 'fluent-cart/delete-license', array(
		'label'       => 'Delete License',
		'description' => 'Delete a license. Fires fluent_cart_sl/license_deleted. Mirrors LicenseController::deleteLicense.',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'annotations' => array( 'idempotent' => false ),
		'callback'    => function( $input ) {
			$license = \FluentCartPro\App\Modules\Licensing\Models\License::find( (int) $input['id'] );
			if ( ! $license ) {
				return fluent_abilities_error( 'not_found', 'License not found.' );
			}
			$id = (int) $license->id;
			do_action( 'fluent_cart_sl/license_deleted', $license );
			$license->delete();
			return array( 'success' => true, 'id' => $id );
		},
	) );

	// =========================================================================
	// 4.15 LICENSE SITE ACTIVATION CONTROL (4)
	// =========================================================================

	$reg->write( 'fluent-cart/activate-license-site', array(
		'label'       => 'Activate License Site',
		'description' => 'Admin-side manual site activation against a license, by URL. Mirrors LicenseController::activateSite.',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'license_id', 'site_url' ),
			'properties' => array(
				'license_id' => array( 'type' => 'integer' ),
				'site_url'   => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'license_id'    => array( 'type' => 'integer' ),
			'activation_id' => array( 'type' => 'integer' ),
		) ),
		'annotations' => array( 'idempotent' => false ),
		'callback'    => function( $input ) {
			$license = \FluentCartPro\App\Modules\Licensing\Models\License::find( (int) $input['license_id'] );
			if ( ! $license ) {
				return fluent_abilities_error( 'not_found', 'License not found.' );
			}
			$activation = \FluentCartPro\App\Modules\Licensing\Models\LicenseActivation::create( array(
				'license_id'        => (int) $license->id,
				'status'            => 'active',
				'activation_method' => 'admin',
				'activation_hash'   => wp_generate_password( 32, false ),
			) );
			$license->activation_count = (int) ( $license->activation_count ?? 0 ) + 1;
			$license->save();
			do_action( 'fluent_cart_sl/site_activated', $license, $activation, esc_url_raw( $input['site_url'] ) );
			return array( 'success' => true, 'license_id' => (int) $license->id, 'activation_id' => (int) $activation->id );
		},
	) );

	$reg->write( 'fluent-cart/deactivate-license-site', array(
		'label'       => 'Deactivate License Site',
		'description' => 'Deactivate a specific license activation by ID. Mirrors LicenseController::deactivateSite.',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'activation_id' ),
			'properties' => array(
				'activation_id' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'activation_id' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$activation = \FluentCartPro\App\Modules\Licensing\Models\LicenseActivation::find( (int) $input['activation_id'] );
			if ( ! $activation ) {
				return fluent_abilities_error( 'not_found', 'Activation not found.' );
			}
			$activation->status = 'deactivated';
			$activation->save();
			$license = \FluentCartPro\App\Modules\Licensing\Models\License::find( (int) $activation->license_id );
			if ( $license ) {
				$license->activation_count = max( 0, (int) ( $license->activation_count ?? 0 ) - 1 );
				$license->save();
			}
			return array( 'success' => true, 'activation_id' => (int) $activation->id );
		},
	) );

	$reg->read( 'fluent-cart/list-license-activations', array(
		'label'       => 'List License Activations',
		'description' => 'List site activations for a license (fct_license_activations).',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'license_id' ),
			'properties' => array_merge( array(
				'license_id' => array( 'type' => 'integer' ),
				'status'     => array( 'type' => 'string' ),
			), fluent_abilities_pagination_schema() ),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'activations', array(
			'id'                  => array( 'type' => 'integer' ),
			'license_id'          => array( 'type' => 'integer' ),
			'site_id'             => array( 'type' => array( 'integer', 'null' ) ),
			'status'              => array( 'type' => array( 'string', 'null' ) ),
			'is_local'            => array( 'type' => array( 'integer', 'null' ) ),
			'activation_method'   => array( 'type' => array( 'string', 'null' ) ),
			'activation_hash'     => array( 'type' => array( 'string', 'null' ) ),
			'last_update_version' => array( 'type' => array( 'string', 'null' ) ),
			'last_update_date'    => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$pagination = fluent_abilities_pagination( $input );
			$query = \FluentCartPro\App\Modules\Licensing\Models\LicenseActivation::where( 'license_id', (int) $input['license_id'] );
			if ( ! empty( $input['status'] ) ) {
				$query->where( 'status', sanitize_text_field( $input['status'] ) );
			}
			$total = $query->count();
			$rows  = $query->orderBy( 'id', 'DESC' )
				->offset( $pagination['offset'] )->limit( $pagination['per_page'] )->get();
			$items = array();
			foreach ( $rows as $a ) {
				$items[] = array(
					'id'                  => (int) $a->id,
					'license_id'          => (int) $a->license_id,
					'site_id'             => isset( $a->site_id ) ? (int) $a->site_id : null,
					'status'              => $a->status ?? null,
					'is_local'            => isset( $a->is_local ) ? (int) $a->is_local : null,
					'activation_method'   => $a->activation_method ?? null,
					'activation_hash'     => $a->activation_hash ?? null,
					'last_update_version' => $a->last_update_version ?? null,
					'last_update_date'    => $a->last_update_date ?? null,
				);
			}
			return array(
				'activations' => $items,
				'total'       => $total,
				'page'        => $pagination['page'],
				'per_page'    => $pagination['per_page'],
			);
		},
	) );

	$reg->read( 'fluent-cart/get-product-license-settings', array(
		'label'       => 'Get Product License Settings',
		'description' => 'Get per-product license configuration: key length, default activation limit, default expiration. Mirrors GET /products/{id}/settings.',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'product_id' ),
			'properties' => array(
				'product_id' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'product_id'       => array( 'type' => 'integer' ),
			'key_length'       => array( 'type' => array( 'integer', 'null' ) ),
			'activation_limit' => array( 'type' => array( 'integer', 'null' ) ),
			'expiration_unit'  => array( 'type' => array( 'string', 'null' ) ),
			'expiration_value' => array( 'type' => array( 'integer', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$product_id = (int) $input['product_id'];
			$settings   = get_post_meta( $product_id, '_fluent_cart_license_settings', true );
			if ( ! is_array( $settings ) ) {
				$settings = array();
			}
			return array(
				'product_id'       => $product_id,
				'key_length'       => isset( $settings['key_length'] ) ? (int) $settings['key_length'] : null,
				'activation_limit' => isset( $settings['activation_limit'] ) ? (int) $settings['activation_limit'] : null,
				'expiration_unit'  => $settings['expiration_unit'] ?? null,
				'expiration_value' => isset( $settings['expiration_value'] ) ? (int) $settings['expiration_value'] : null,
			);
		},
	) );

	$count = 11;
	error_log( "Abilities for Fluent: Registered {$count} Cart License Extended abilities (Pro)" );

}, 100 );
