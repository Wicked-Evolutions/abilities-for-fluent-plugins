<?php
/**
 * FluentAffiliate Abilities — Portal (Admin View)
 *
 * Affiliate self-service portal data accessed as admin for a given affiliate.
 * These are admin-only abilities that accept an affiliate_id parameter,
 * NOT user-scoped portal endpoints.
 *
 * 6 abilities in the 'fluent-affiliate' category.
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {

	$reg = new Fluent_Abilities_Registrar( 'affiliate' );

	// =========================================================================
	// PORTAL (Admin View)
	// =========================================================================

	$reg->read( 'fluent-affiliate/get-portal-stats', array(
		'label'       => 'Get Affiliate Portal Stats',
		'description' => 'Get the portal dashboard stats for a specific affiliate (admin view). Shows the same data an affiliate sees in their portal.',
		'category'    => 'fluent-affiliate',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'affiliate_id' => array(
					'type'        => 'integer',
					'description' => 'The affiliate ID to view stats for.',
				),
			),
			'required' => array( 'affiliate_id' ),
		),
		'callback' => function( $input ) {
			$affiliate = \FluentAffiliate\App\Models\Affiliate::find( (int) $input['affiliate_id'] );
			if ( ! $affiliate ) {
				return fluent_abilities_error( 'not_found', 'Affiliate not found.' );
			}

			$share_url   = $affiliate->getShareUrl();
			$rate_details = $affiliate->getRateDetails();

			// Referral counts by status.
			$pending_count = (int) \FluentAffiliate\App\Models\Referral::where( 'affiliate_id', $affiliate->id )
				->where( 'status', 'pending' )->count();
			$unpaid_count = (int) \FluentAffiliate\App\Models\Referral::where( 'affiliate_id', $affiliate->id )
				->where( 'status', 'unpaid' )->count();

			return array(
				'affiliate_id'    => (int) $affiliate->id,
				'status'          => $affiliate->status ?? '',
				'share_url'       => $share_url ?? '',
				'rate_details'    => $rate_details['human_readable'] ?? '',
				'total_earnings'  => round( (float) ( $affiliate->total_earnings ?? 0 ), 2 ),
				'unpaid_earnings' => round( (float) ( $affiliate->unpaid_earnings ?? 0 ), 2 ),
				'total_referrals' => (int) ( $affiliate->referrals ?? 0 ),
				'total_visits'    => (int) ( $affiliate->visits ?? 0 ),
				'pending_referrals' => $pending_count,
				'unpaid_referrals'  => $unpaid_count,
				'coupons'         => $affiliate->getAttachedCoupons( 'view' ),
			);
		},
	) );

	$reg->read( 'fluent-affiliate/get-portal-referrals', array(
		'label'       => 'Get Affiliate Portal Referrals',
		'description' => 'Get referrals as displayed in the affiliate portal (admin view for a specific affiliate).',
		'category'    => 'fluent-affiliate',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge( array(
				'affiliate_id' => array(
					'type'        => 'integer',
					'description' => 'The affiliate ID',
				),
				'status' => array(
					'type'        => 'string',
					'description' => 'Filter by status: pending, unpaid, paid, rejected, cancelled',
				),
			), fluent_abilities_pagination_schema() ),
			'required' => array( 'affiliate_id' ),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'referrals', array(
			'id'          => array( 'type' => 'integer' ),
			'amount'      => array( 'type' => 'number' ),
			'status'      => array( 'type' => 'string' ),
			'type'        => array( 'type' => 'string' ),
			'description' => array( 'type' => 'string' ),
			'created_at'  => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$affiliate = \FluentAffiliate\App\Models\Affiliate::find( (int) $input['affiliate_id'] );
			if ( ! $affiliate ) {
				return fluent_abilities_error( 'not_found', 'Affiliate not found.' );
			}

			$pagination = fluent_abilities_pagination( $input );
			$query      = \FluentAffiliate\App\Models\Referral::where( 'affiliate_id', $affiliate->id );

			if ( ! empty( $input['status'] ) ) {
				$query->where( 'status', sanitize_text_field( $input['status'] ) );
			}

			$total     = $query->count();
			$referrals = $query->orderBy( 'id', 'DESC' )
				->offset( $pagination['offset'] )
				->limit( $pagination['per_page'] )
				->get();

			$items = array();
			foreach ( $referrals as $referral ) {
				$items[] = array(
					'id'          => (int) $referral->id,
					'amount'      => round( (float) ( $referral->amount ?? 0 ), 2 ),
					'status'      => $referral->status ?? '',
					'type'        => $referral->type ?? 'sale',
					'description' => $referral->description ?? '',
					'created_at'  => (string) ( $referral->created_at ?? '' ),
				);
			}

			return array(
				'referrals' => $items,
				'total'     => (int) $total,
				'page'      => $pagination['page'],
				'per_page'  => $pagination['per_page'],
			);
		},
	) );

	$reg->read( 'fluent-affiliate/get-portal-transactions', array(
		'label'       => 'Get Affiliate Portal Transactions',
		'description' => 'Get payout transaction history as displayed in the affiliate portal (admin view).',
		'category'    => 'fluent-affiliate',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge( array(
				'affiliate_id' => array(
					'type'        => 'integer',
					'description' => 'The affiliate ID',
				),
			), fluent_abilities_pagination_schema() ),
			'required' => array( 'affiliate_id' ),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'transactions', array(
			'id'            => array( 'type' => 'integer' ),
			'total_amount'  => array( 'type' => 'number' ),
			'payout_method' => array( 'type' => 'string' ),
			'status'        => array( 'type' => 'string' ),
			'currency'      => array( 'type' => 'string' ),
			'created_at'    => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$affiliate = \FluentAffiliate\App\Models\Affiliate::find( (int) $input['affiliate_id'] );
			if ( ! $affiliate ) {
				return fluent_abilities_error( 'not_found', 'Affiliate not found.' );
			}

			$pagination   = fluent_abilities_pagination( $input );
			$query        = \FluentAffiliate\App\Models\Transaction::where( 'affiliate_id', $affiliate->id );

			$total        = $query->count();
			$transactions = $query->orderBy( 'id', 'DESC' )
				->offset( $pagination['offset'] )
				->limit( $pagination['per_page'] )
				->get();

			$items = array();
			foreach ( $transactions as $tx ) {
				$items[] = array(
					'id'            => (int) $tx->id,
					'total_amount'  => round( (float) ( $tx->total_amount ?? 0 ), 2 ),
					'payout_method' => $tx->payout_method ?? 'manual',
					'status'        => $tx->status ?? '',
					'currency'      => $tx->currency ?? '',
					'created_at'    => (string) ( $tx->created_at ?? '' ),
				);
			}

			return array(
				'transactions' => $items,
				'total'        => (int) $total,
				'page'         => $pagination['page'],
				'per_page'     => $pagination['per_page'],
			);
		},
	) );

	$reg->read( 'fluent-affiliate/get-portal-visits', array(
		'label'       => 'Get Affiliate Portal Visits',
		'description' => 'Get visit/click data as displayed in the affiliate portal (admin view).',
		'category'    => 'fluent-affiliate',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge( array(
				'affiliate_id' => array(
					'type'        => 'integer',
					'description' => 'The affiliate ID',
				),
			), fluent_abilities_pagination_schema() ),
			'required' => array( 'affiliate_id' ),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'visits', array(
			'id'           => array( 'type' => 'integer' ),
			'url'          => array( 'type' => 'string' ),
			'referrer'     => array( 'type' => 'string' ),
			'utm_campaign' => array( 'type' => 'string' ),
			'utm_source'   => array( 'type' => 'string' ),
			'created_at'   => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$affiliate = \FluentAffiliate\App\Models\Affiliate::find( (int) $input['affiliate_id'] );
			if ( ! $affiliate ) {
				return fluent_abilities_error( 'not_found', 'Affiliate not found.' );
			}

			$pagination = fluent_abilities_pagination( $input );
			$query      = \FluentAffiliate\App\Models\Visit::where( 'affiliate_id', $affiliate->id );

			$total  = $query->count();
			$visits = $query->orderBy( 'id', 'DESC' )
				->offset( $pagination['offset'] )
				->limit( $pagination['per_page'] )
				->get();

			$items = array();
			foreach ( $visits as $visit ) {
				$items[] = array(
					'id'           => (int) $visit->id,
					'url'          => $visit->url ?? '',
					'referrer'     => $visit->referrer ?? '',
					'utm_campaign' => $visit->utm_campaign ?? '',
					'utm_source'   => $visit->utm_source ?? '',
					'created_at'   => (string) ( $visit->created_at ?? '' ),
				);
			}

			return array(
				'visits'   => $items,
				'total'    => (int) $total,
				'page'     => $pagination['page'],
				'per_page' => $pagination['per_page'],
			);
		},
	) );

	$reg->read( 'fluent-affiliate/get-portal-settings', array(
		'label'       => 'Get Affiliate Portal Settings',
		'description' => 'Get the portal profile settings for a specific affiliate (admin view): payment email, bank details.',
		'category'    => 'fluent-affiliate',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'affiliate_id' => array(
					'type'        => 'integer',
					'description' => 'The affiliate ID',
				),
			),
			'required' => array( 'affiliate_id' ),
		),
		'callback' => function( $input ) {
			$affiliate = \FluentAffiliate\App\Models\Affiliate::find( (int) $input['affiliate_id'] );
			if ( ! $affiliate ) {
				return fluent_abilities_error( 'not_found', 'Affiliate not found.' );
			}

			$settings = fluent_abilities_safe_array( maybe_unserialize( $affiliate->settings ) );
			$settings = is_array( $settings ) ? $settings : array();

			return array(
				'affiliate_id'          => (int) $affiliate->id,
				'payment_email'         => $affiliate->payment_email ?? '',
				'bank_details'          => $settings['bank_details'] ?? '',
				'disable_new_ref_email' => ! empty( $settings['disable_new_ref_email'] ),
			);
		},
	) );

	$reg->write( 'fluent-affiliate/update-portal-settings', array(
		'label'       => 'Update Affiliate Portal Settings',
		'description' => 'Update the portal profile settings for a specific affiliate (admin view): payment email, bank details.',
		'category'    => 'fluent-affiliate',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'affiliate_id' => array(
					'type'        => 'integer',
					'description' => 'The affiliate ID',
				),
				'payment_email' => array(
					'type'        => 'string',
					'description' => 'PayPal or payment email address',
				),
				'bank_details' => array(
					'type'        => 'string',
					'description' => 'Bank account details for bank transfer payouts',
				),
				'disable_new_ref_email' => array(
					'type'        => 'boolean',
					'description' => 'Disable new referral notification emails for this affiliate',
				),
			),
			'required' => array( 'affiliate_id' ),
		),
		'callback' => function( $input ) {
			$affiliate = \FluentAffiliate\App\Models\Affiliate::find( (int) $input['affiliate_id'] );
			if ( ! $affiliate ) {
				return fluent_abilities_error( 'not_found', 'Affiliate not found.' );
			}

			if ( isset( $input['payment_email'] ) ) {
				$affiliate->payment_email = sanitize_email( $input['payment_email'] );
			}

			$settings = fluent_abilities_safe_array( maybe_unserialize( $affiliate->settings ) );
			$settings = is_array( $settings ) ? $settings : array();

			if ( isset( $input['bank_details'] ) ) {
				$settings['bank_details'] = sanitize_textarea_field( $input['bank_details'] );
			}
			if ( isset( $input['disable_new_ref_email'] ) ) {
				$settings['disable_new_ref_email'] = ! empty( $input['disable_new_ref_email'] ) ? 'yes' : 'no';
			}

			// V3: assign the plain array. Vendor Affiliate::
			// setSettingsAttribute() wp_parse_args()+maybe_serialize()s an
			// array on set and getSettingsAttribute() unserializes on read
			// (installed FluentAffiliate app/Models/Affiliate.php:35-68).
			// Pre-serializing passed a STRING into the mutator → its
			// is_array() guard failed → the else branch discarded every
			// submitted value and stored defaults only (silent settings
			// data-loss); same V3 vendor-mutator-bypass root cause as the
			// #106 location_settings double-serialize crash class.
			$affiliate->settings = $settings;
			$affiliate->save();

			return array(
				'success'      => true,
				'affiliate_id' => (int) $affiliate->id,
				'message'      => 'Portal settings updated.',
			);
		},
	) );

} );
