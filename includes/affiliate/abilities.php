<?php
/**
 * FluentAffiliate Abilities — Core
 *
 * Affiliates, referrals, visits, customers, affiliate groups, and utility.
 *
 * 21 abilities in the 'fluent-affiliate' category.
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {

	$reg = new Fluent_Abilities_Registrar( 'affiliate' );

	// =========================================================================
	// AFFILIATES
	// =========================================================================

	$reg->read( 'fluent-affiliate/list-affiliates', array(
		'label'       => 'List Affiliates',
		'description' => 'List affiliates with filtering by status (active/pending/inactive), search by email or name, and pagination.',
		'category'    => 'fluent-affiliate',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge( array(
				'search' => array(
					'type'        => 'string',
					'description' => 'Search by user email, display name, or affiliate ID',
				),
				'status' => array(
					'type'        => 'string',
					'description' => 'Filter by status: active, pending, inactive',
				),
				'order_by' => array(
					'type'        => 'string',
					'description' => 'Sort column (default: id)',
					'default'     => 'id',
				),
				'order_type' => array(
					'type'        => 'string',
					'description' => 'Sort direction: ASC or DESC (default: DESC)',
					'default'     => 'DESC',
				),
			), fluent_abilities_pagination_schema() ),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'affiliates', array(
			'id'              => array( 'type' => 'integer' ),
			'user_id'         => array( 'type' => 'integer' ),
			'user_email'      => array( 'type' => 'string' ),
			'display_name'    => array( 'type' => 'string' ),
			'status'          => array( 'type' => 'string' ),
			'rate'            => array( 'type' => 'number' ),
			'rate_type'       => array( 'type' => 'string' ),
			'total_earnings'  => array( 'type' => 'number' ),
			'unpaid_earnings' => array( 'type' => 'number' ),
			'referrals'       => array( 'type' => 'integer' ),
			'visits'          => array( 'type' => 'integer' ),
			'payment_email'   => array( 'type' => 'string' ),
			'created_at'      => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$pagination = fluent_abilities_pagination( $input );

			$query = \FluentAffiliate\App\Models\Affiliate::query();

			if ( ! empty( $input['search'] ) ) {
				$search = sanitize_text_field( $input['search'] );
				$query->where( function( $q ) use ( $search ) {
					$q->where( 'id', $search )
					  ->orWhereHas( 'user', function( $uq ) use ( $search ) {
						$uq->where( 'user_email', 'LIKE', "%{$search}%" )
						   ->orWhere( 'display_name', 'LIKE', "%{$search}%" );
					  } );
				} );
			}

			if ( ! empty( $input['status'] ) ) {
				$query->where( 'status', sanitize_text_field( $input['status'] ) );
			}

			$order_by   = sanitize_text_field( $input['order_by'] ?? 'id' );
			$order_type = strtoupper( sanitize_text_field( $input['order_type'] ?? 'DESC' ) );
			if ( ! in_array( $order_type, array( 'ASC', 'DESC' ), true ) ) {
				$order_type = 'DESC';
			}

			$total      = $query->count();
			$affiliates = $query->orderBy( $order_by, $order_type )
				->offset( $pagination['offset'] )
				->limit( $pagination['per_page'] )
				->get();

			$items = array();
			foreach ( $affiliates as $affiliate ) {
				$user = $affiliate->user;
				$items[] = array(
					'id'              => (int) $affiliate->id,
					'user_id'         => (int) ( $affiliate->user_id ?? 0 ),
					'user_email'      => $user->user_email ?? '',
					'display_name'    => $user->display_name ?? '',
					'status'          => $affiliate->status ?? '',
					'rate'            => (float) ( $affiliate->rate ?? 0 ),
					'rate_type'       => $affiliate->rate_type ?? 'default',
					'total_earnings'  => round( (float) ( $affiliate->total_earnings ?? 0 ), 2 ),
					'unpaid_earnings' => round( (float) ( $affiliate->unpaid_earnings ?? 0 ), 2 ),
					'referrals'       => (int) ( $affiliate->referrals ?? 0 ),
					'visits'          => (int) ( $affiliate->visits ?? 0 ),
					'payment_email'   => $affiliate->payment_email ?? '',
					'created_at'      => (string) ( $affiliate->created_at ?? '' ),
				);
			}

			return array(
				'affiliates' => $items,
				'total'      => (int) $total,
				'page'       => $pagination['page'],
				'per_page'   => $pagination['per_page'],
			);
		},
	) );

	$reg->read( 'fluent-affiliate/get-affiliate', array(
		'label'       => 'Get Affiliate',
		'description' => 'Get a single affiliate with user info, group, rate details, and summary stats.',
		'category'    => 'fluent-affiliate',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'id' => array(
					'type'        => 'integer',
					'description' => 'Affiliate ID',
				),
			),
			'required' => array( 'id' ),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'id'              => array( 'type' => 'integer' ),
			'user_id'         => array( 'type' => 'integer' ),
			'user_email'      => array( 'type' => 'string' ),
			'display_name'    => array( 'type' => 'string' ),
			'status'          => array( 'type' => 'string' ),
			'rate'            => array( 'type' => 'number' ),
			'rate_type'       => array( 'type' => 'string' ),
			'rate_details'    => array( 'type' => 'string' ),
			'group_id'        => array( 'type' => 'integer' ),
			'total_earnings'  => array( 'type' => 'number' ),
			'unpaid_earnings' => array( 'type' => 'number' ),
			'referrals'       => array( 'type' => 'integer' ),
			'visits'          => array( 'type' => 'integer' ),
			'lead_counts'     => array( 'type' => 'integer' ),
			'custom_param'    => array( 'type' => 'string' ),
			'payment_email'   => array( 'type' => 'string' ),
			'note'            => array( 'type' => 'string' ),
			'created_at'      => array( 'type' => 'string' ),
			'updated_at'      => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$affiliate = \FluentAffiliate\App\Models\Affiliate::find( (int) $input['id'] );
			if ( ! $affiliate ) {
				return fluent_abilities_error( 'not_found', 'Affiliate not found.' );
			}

			$user         = $affiliate->user;
			$rate_details = $affiliate->getRateDetails();

			return array(
				'id'              => (int) $affiliate->id,
				'user_id'         => (int) ( $affiliate->user_id ?? 0 ),
				'user_email'      => $user->user_email ?? '',
				'display_name'    => $user->display_name ?? '',
				'status'          => $affiliate->status ?? '',
				'rate'            => (float) ( $affiliate->rate ?? 0 ),
				'rate_type'       => $affiliate->rate_type ?? 'default',
				'rate_details'    => $rate_details['human_readable'] ?? '',
				'group_id'        => (int) ( $affiliate->group_id ?? 0 ),
				'total_earnings'  => round( (float) ( $affiliate->total_earnings ?? 0 ), 2 ),
				'unpaid_earnings' => round( (float) ( $affiliate->unpaid_earnings ?? 0 ), 2 ),
				'referrals'       => (int) ( $affiliate->referrals ?? 0 ),
				'visits'          => (int) ( $affiliate->visits ?? 0 ),
				'lead_counts'     => (int) ( $affiliate->lead_counts ?? 0 ),
				'custom_param'    => $affiliate->custom_param ?? '',
				'payment_email'   => $affiliate->payment_email ?? '',
				'note'            => $affiliate->note ?? '',
				'created_at'      => (string) ( $affiliate->created_at ?? '' ),
				'updated_at'      => (string) ( $affiliate->updated_at ?? '' ),
			);
		},
	) );

	$reg->read( 'fluent-affiliate/get-affiliate-stats', array(
		'label'       => 'Get Affiliate Stats',
		'description' => 'Get overview statistics for a single affiliate: earnings, referrals, visits, conversion rate.',
		'category'    => 'fluent-affiliate',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'id' => array(
					'type'        => 'integer',
					'description' => 'Affiliate ID',
				),
			),
			'required' => array( 'id' ),
		),
		'callback' => function( $input ) {
			$affiliate = \FluentAffiliate\App\Models\Affiliate::find( (int) $input['id'] );
			if ( ! $affiliate ) {
				return fluent_abilities_error( 'not_found', 'Affiliate not found.' );
			}

			$total_visits    = (int) ( $affiliate->visits ?? 0 );
			$total_referrals = (int) ( $affiliate->referrals ?? 0 );
			$conversion_rate = $total_visits > 0 ? round( ( $total_referrals / $total_visits ) * 100, 2 ) : 0;

			return array(
				'affiliate_id'    => (int) $affiliate->id,
				'status'          => $affiliate->status ?? '',
				'total_earnings'  => round( (float) ( $affiliate->total_earnings ?? 0 ), 2 ),
				'unpaid_earnings' => round( (float) ( $affiliate->unpaid_earnings ?? 0 ), 2 ),
				'total_referrals' => $total_referrals,
				'total_visits'    => $total_visits,
				'lead_counts'     => (int) ( $affiliate->lead_counts ?? 0 ),
				'conversion_rate' => $conversion_rate,
			);
		},
	) );

	$reg->read( 'fluent-affiliate/get-affiliate-statistics', array(
		'label'       => 'Get Affiliate Chart Statistics',
		'description' => 'Get time-series chart data for a single affiliate performance over a date range.',
		'category'    => 'fluent-affiliate',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'id' => array(
					'type'        => 'integer',
					'description' => 'Affiliate ID',
				),
				'date_from' => array(
					'type'        => 'string',
					'description' => 'Start date (YYYY-MM-DD)',
				),
				'date_to' => array(
					'type'        => 'string',
					'description' => 'End date (YYYY-MM-DD)',
				),
			),
			'required' => array( 'id' ),
		),
		'callback' => function( $input ) {
			$affiliate = \FluentAffiliate\App\Models\Affiliate::find( (int) $input['id'] );
			if ( ! $affiliate ) {
				return fluent_abilities_error( 'not_found', 'Affiliate not found.' );
			}

			$date_from = sanitize_text_field( $input['date_from'] ?? gmdate( 'Y-m-d', strtotime( '-30 days' ) ) );
			$date_to   = sanitize_text_field( $input['date_to'] ?? gmdate( 'Y-m-d' ) );

			$referrals_query = \FluentAffiliate\App\Models\Referral::where( 'affiliate_id', $affiliate->id )
				->where( 'created_at', '>=', $date_from . ' 00:00:00' )
				->where( 'created_at', '<=', $date_to . ' 23:59:59' );

			$visits_query = \FluentAffiliate\App\Models\Visit::where( 'affiliate_id', $affiliate->id )
				->where( 'created_at', '>=', $date_from . ' 00:00:00' )
				->where( 'created_at', '<=', $date_to . ' 23:59:59' );

			return array(
				'affiliate_id'    => (int) $affiliate->id,
				'date_from'       => $date_from,
				'date_to'         => $date_to,
				'total_referrals' => (int) $referrals_query->count(),
				'total_visits'    => (int) $visits_query->count(),
				'total_earnings'  => round( (float) ( $referrals_query->sum( 'amount' ) ?? 0 ), 2 ),
			);
		},
	) );

	$reg->write( 'fluent-affiliate/create-affiliate', array(
		'label'       => 'Create Affiliate',
		'description' => 'Create a new affiliate linked to a WordPress user. The user must exist.',
		'category'    => 'fluent-affiliate',
		'annotations' => array( 'idempotent' => false ),
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'user_id' => array(
					'type'        => 'integer',
					'description' => 'WordPress user ID to link the affiliate to',
				),
				'rate' => array(
					'type'        => 'number',
					'description' => 'Commission rate (e.g. 25 for 25%)',
				),
				'rate_type' => array(
					'type'        => 'string',
					'description' => 'Rate type: percentage, flat, group, or default',
					'default'     => 'default',
				),
				'status' => array(
					'type'        => 'string',
					'description' => 'Affiliate status: active, pending, or inactive (default: pending)',
					'default'     => 'pending',
				),
				'group_id' => array(
					'type'        => 'integer',
					'description' => 'Affiliate group ID for group-based rates',
				),
				'payment_email' => array(
					'type'        => 'string',
					'description' => 'PayPal or payment email address',
				),
				'custom_param' => array(
					'type'        => 'string',
					'description' => 'Custom referral parameter (slug in affiliate URLs)',
				),
				'note' => array(
					'type'        => 'string',
					'description' => 'Admin note',
				),
			),
			'required' => array( 'user_id' ),
		),
		'callback' => function( $input ) {
			$user_id = (int) $input['user_id'];
			$user    = get_userdata( $user_id );
			if ( ! $user ) {
				return fluent_abilities_error( 'not_found', 'WordPress user not found.' );
			}

			// Check if affiliate already exists for this user.
			$existing = \FluentAffiliate\App\Models\Affiliate::where( 'user_id', $user_id )->first();
			if ( $existing ) {
				return fluent_abilities_error( 'already_exists', 'An affiliate already exists for this user (ID: ' . $existing->id . ').' );
			}

			$data = array(
				'user_id'   => $user_id,
				'status'    => sanitize_text_field( $input['status'] ?? 'pending' ),
				'rate_type' => sanitize_text_field( $input['rate_type'] ?? 'default' ),
			);

			if ( isset( $input['rate'] ) ) {
				$data['rate'] = (float) $input['rate'];
			}
			if ( isset( $input['group_id'] ) ) {
				$data['group_id'] = (int) $input['group_id'];
			}
			if ( isset( $input['payment_email'] ) ) {
				$data['payment_email'] = sanitize_email( $input['payment_email'] );
			}
			if ( isset( $input['custom_param'] ) ) {
				$data['custom_param'] = sanitize_text_field( $input['custom_param'] );
			}
			if ( isset( $input['note'] ) ) {
				$data['note'] = sanitize_textarea_field( $input['note'] );
			}

			$affiliate = \FluentAffiliate\App\Models\Affiliate::create( $data );

			return array(
				'success'      => true,
				'affiliate_id' => (int) $affiliate->id,
				'status'       => $affiliate->status ?? '',
				'message'      => 'Affiliate created for user ' . $user->display_name . '.',
			);
		},
	) );

	$reg->write( 'fluent-affiliate/update-affiliate', array(
		'label'       => 'Update Affiliate',
		'description' => 'Update affiliate details: rate, rate_type, group, payment email, custom param, or note.',
		'category'    => 'fluent-affiliate',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'id' => array(
					'type'        => 'integer',
					'description' => 'Affiliate ID',
				),
				'rate' => array(
					'type'        => 'number',
					'description' => 'Commission rate',
				),
				'rate_type' => array(
					'type'        => 'string',
					'description' => 'Rate type: percentage, flat, group, default',
				),
				'group_id' => array(
					'type'        => 'integer',
					'description' => 'Affiliate group ID',
				),
				'payment_email' => array(
					'type'        => 'string',
					'description' => 'PayPal or payment email',
				),
				'custom_param' => array(
					'type'        => 'string',
					'description' => 'Custom referral parameter',
				),
				'note' => array(
					'type'        => 'string',
					'description' => 'Admin note',
				),
			),
			'required' => array( 'id' ),
		),
		'callback' => function( $input ) {
			$affiliate = \FluentAffiliate\App\Models\Affiliate::find( (int) $input['id'] );
			if ( ! $affiliate ) {
				return fluent_abilities_error( 'not_found', 'Affiliate not found.' );
			}

			$update = array();
			if ( isset( $input['rate'] ) ) {
				$update['rate'] = (float) $input['rate'];
			}
			if ( isset( $input['rate_type'] ) ) {
				$update['rate_type'] = sanitize_text_field( $input['rate_type'] );
			}
			if ( isset( $input['group_id'] ) ) {
				$update['group_id'] = (int) $input['group_id'];
			}
			if ( isset( $input['payment_email'] ) ) {
				$update['payment_email'] = sanitize_email( $input['payment_email'] );
			}
			if ( isset( $input['custom_param'] ) ) {
				$update['custom_param'] = sanitize_text_field( $input['custom_param'] );
			}
			if ( isset( $input['note'] ) ) {
				$update['note'] = sanitize_textarea_field( $input['note'] );
			}

			if ( empty( $update ) ) {
				return fluent_abilities_error( 'ability_invalid_input', 'No fields to update. Provide at least one field.' );
			}

			$affiliate->fill( $update )->save();

			return array(
				'success'      => true,
				'affiliate_id' => (int) $affiliate->id,
				'message'      => 'Affiliate updated.',
			);
		},
	) );

	$reg->write( 'fluent-affiliate/update-affiliate-status', array(
		'label'       => 'Update Affiliate Status',
		'description' => 'Change an affiliate\'s status to active, pending, or inactive. Fires status change hooks.',
		'category'    => 'fluent-affiliate',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'id' => array(
					'type'        => 'integer',
					'description' => 'Affiliate ID',
				),
				'status' => array(
					'type'        => 'string',
					'description' => 'New status: active, pending, or inactive',
				),
			),
			'required' => array( 'id', 'status' ),
		),
		'callback' => function( $input ) {
			$affiliate = \FluentAffiliate\App\Models\Affiliate::find( (int) $input['id'] );
			if ( ! $affiliate ) {
				return fluent_abilities_error( 'not_found', 'Affiliate not found.' );
			}

			$new_status = sanitize_text_field( $input['status'] );
			$allowed    = array( 'active', 'pending', 'inactive' );
			if ( ! in_array( $new_status, $allowed, true ) ) {
				return fluent_abilities_error( 'ability_invalid_input', 'Status must be one of: ' . implode( ', ', $allowed ) );
			}

			$previous_status    = $affiliate->status;
			$affiliate->status  = $new_status;
			$affiliate->save();

			do_action( 'fluent_affiliate/affiliate_status_to_' . $new_status, $affiliate, $previous_status );
			do_action( 'fluent_affiliate/affiliate_updated', $affiliate, 'by_admin', array( 'status' => $new_status ) );

			return array(
				'success'         => true,
				'affiliate_id'    => (int) $affiliate->id,
				'previous_status' => $previous_status ?? '',
				'new_status'      => $new_status,
			);
		},
	) );

	$reg->write( 'fluent-affiliate/recount-affiliate-earnings', array(
		'label'       => 'Recount Affiliate Earnings',
		'description' => 'Recalculate total_earnings and unpaid_earnings from referral records. Use when earnings appear out of sync.',
		'category'    => 'fluent-affiliate',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'id' => array(
					'type'        => 'integer',
					'description' => 'Affiliate ID',
				),
			),
			'required' => array( 'id' ),
		),
		'callback' => function( $input ) {
			$affiliate = \FluentAffiliate\App\Models\Affiliate::find( (int) $input['id'] );
			if ( ! $affiliate ) {
				return fluent_abilities_error( 'not_found', 'Affiliate not found.' );
			}

			$before_total  = round( (float) ( $affiliate->total_earnings ?? 0 ), 2 );
			$before_unpaid = round( (float) ( $affiliate->unpaid_earnings ?? 0 ), 2 );

			$affiliate->recountEarnings();
			$affiliate->refresh();

			return array(
				'success'               => true,
				'affiliate_id'          => (int) $affiliate->id,
				'previous_total'        => $before_total,
				'previous_unpaid'       => $before_unpaid,
				'recounted_total'       => round( (float) ( $affiliate->total_earnings ?? 0 ), 2 ),
				'recounted_unpaid'      => round( (float) ( $affiliate->unpaid_earnings ?? 0 ), 2 ),
			);
		},
	) );

	$reg->delete( 'fluent-affiliate/delete-affiliate', array(
		'label'       => 'Delete Affiliate',
		'description' => 'Permanently deletes an affiliate and ALL associated visits and referrals. This cannot be undone. Set confirm=true to execute.',
		'category'    => 'fluent-affiliate',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'id' => array(
					'type'        => 'integer',
					'description' => 'Affiliate ID',
				),
				'confirm' => array(
					'type'        => 'boolean',
					'description' => 'Must be true to execute. If false, returns a preview of what would be deleted.',
				),
			),
			'required' => array( 'id', 'confirm' ),
		),
		'callback' => function( $input ) {
			$affiliate = \FluentAffiliate\App\Models\Affiliate::find( (int) $input['id'] );
			if ( ! $affiliate ) {
				return fluent_abilities_error( 'not_found', 'Affiliate not found.' );
			}

			$referral_count = (int) \FluentAffiliate\App\Models\Referral::where( 'affiliate_id', $affiliate->id )->count();
			$visit_count    = (int) \FluentAffiliate\App\Models\Visit::where( 'affiliate_id', $affiliate->id )->count();

			if ( empty( $input['confirm'] ) || $input['confirm'] !== true ) {
				return array(
					'status'         => 'preview',
					'message'        => 'Affiliate NOT deleted. Review the data below and call again with confirm=true to execute.',
					'affiliate_id'   => (int) $affiliate->id,
					'user_email'     => $affiliate->user->user_email ?? '',
					'referrals_to_delete' => $referral_count,
					'visits_to_delete'    => $visit_count,
					'total_earnings'      => round( (float) ( $affiliate->total_earnings ?? 0 ), 2 ),
				);
			}

			do_action( 'fluent_affiliate/before_delete_affiliate', $affiliate );

			$affiliate_id = (int) $affiliate->id;

			\FluentAffiliate\App\Models\Visit::where( 'affiliate_id', $affiliate_id )->delete();
			\FluentAffiliate\App\Models\Referral::where( 'affiliate_id', $affiliate_id )->delete();
			$affiliate->delete();

			do_action( 'fluent_affiliate/after_delete_affiliate', $affiliate_id );

			return array(
				'success'          => true,
				'deleted_id'       => $affiliate_id,
				'referrals_deleted' => $referral_count,
				'visits_deleted'    => $visit_count,
			);
		},
	) );

	// =========================================================================
	// REFERRALS
	// =========================================================================

	$reg->read( 'fluent-affiliate/list-referrals', array(
		'label'       => 'List Referrals',
		'description' => 'List referrals with filtering by affiliate, status, type, and provider. Paginated.',
		'category'    => 'fluent-affiliate',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge( array(
				'affiliate_id' => array(
					'type'        => 'integer',
					'description' => 'Filter by affiliate ID',
				),
				'status' => array(
					'type'        => 'string',
					'description' => 'Filter by status: pending, unpaid, paid, rejected, cancelled',
				),
				'type' => array(
					'type'        => 'string',
					'description' => 'Filter by type: sale, opt_in, recurring_sale',
				),
				'provider' => array(
					'type'        => 'string',
					'description' => 'Filter by provider: woo, edd, fluentcart, manual, etc.',
				),
			), fluent_abilities_pagination_schema() ),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'referrals', array(
			'id'           => array( 'type' => 'integer' ),
			'affiliate_id' => array( 'type' => 'integer' ),
			'status'       => array( 'type' => 'string' ),
			'amount'       => array( 'type' => 'number' ),
			'order_total'  => array( 'type' => 'number' ),
			'currency'     => array( 'type' => 'string' ),
			'type'         => array( 'type' => 'string' ),
			'provider'     => array( 'type' => 'string' ),
			'description'  => array( 'type' => 'string' ),
			'created_at'   => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$pagination = fluent_abilities_pagination( $input );
			$query = \FluentAffiliate\App\Models\Referral::query();

			if ( ! empty( $input['affiliate_id'] ) ) {
				$query->where( 'affiliate_id', (int) $input['affiliate_id'] );
			}
			if ( ! empty( $input['status'] ) ) {
				$query->where( 'status', sanitize_text_field( $input['status'] ) );
			}
			if ( ! empty( $input['type'] ) ) {
				$query->where( 'type', sanitize_text_field( $input['type'] ) );
			}
			if ( ! empty( $input['provider'] ) ) {
				$query->where( 'provider', sanitize_text_field( $input['provider'] ) );
			}

			$total     = $query->count();
			$referrals = $query->orderBy( 'id', 'DESC' )
				->offset( $pagination['offset'] )
				->limit( $pagination['per_page'] )
				->get();

			$items = array();
			foreach ( $referrals as $referral ) {
				$items[] = array(
					'id'           => (int) $referral->id,
					'affiliate_id' => (int) ( $referral->affiliate_id ?? 0 ),
					'status'       => $referral->status ?? '',
					'amount'       => round( (float) ( $referral->amount ?? 0 ), 2 ),
					'order_total'  => round( (float) ( $referral->order_total ?? 0 ), 2 ),
					'currency'     => $referral->currency ?? '',
					'type'         => $referral->type ?? 'sale',
					'provider'     => $referral->provider ?? '',
					'description'  => $referral->description ?? '',
					'created_at'   => (string) ( $referral->created_at ?? '' ),
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

	$reg->read( 'fluent-affiliate/get-referral', array(
		'label'       => 'Get Referral',
		'description' => 'Get a single referral with visit, payout, and customer details.',
		'category'    => 'fluent-affiliate',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'id' => array(
					'type'        => 'integer',
					'description' => 'Referral ID',
				),
			),
			'required' => array( 'id' ),
		),
		'callback' => function( $input ) {
			$referral = \FluentAffiliate\App\Models\Referral::find( (int) $input['id'] );
			if ( ! $referral ) {
				return fluent_abilities_error( 'not_found', 'Referral not found.' );
			}

			return array(
				'id'                    => (int) $referral->id,
				'affiliate_id'          => (int) ( $referral->affiliate_id ?? 0 ),
				'customer_id'           => (int) ( $referral->customer_id ?? 0 ),
				'visit_id'              => (int) ( $referral->visit_id ?? 0 ),
				'payout_id'             => (int) ( $referral->payout_id ?? 0 ),
				'payout_transaction_id' => (int) ( $referral->payout_transaction_id ?? 0 ),
				'status'                => $referral->status ?? '',
				'amount'                => round( (float) ( $referral->amount ?? 0 ), 2 ),
				'order_total'           => round( (float) ( $referral->order_total ?? 0 ), 2 ),
				'currency'              => $referral->currency ?? '',
				'type'                  => $referral->type ?? 'sale',
				'provider'              => $referral->provider ?? '',
				'provider_id'           => (int) ( $referral->provider_id ?? 0 ),
				'description'           => $referral->description ?? '',
				'utm_campaign'          => $referral->utm_campaign ?? '',
				'created_at'            => (string) ( $referral->created_at ?? '' ),
				'updated_at'            => (string) ( $referral->updated_at ?? '' ),
			);
		},
	) );

	$reg->read( 'fluent-affiliate/list-affiliate-referrals', array(
		'label'       => 'List Affiliate Referrals',
		'description' => 'List referrals for a specific affiliate. Shortcut for list-referrals with affiliate_id filter.',
		'category'    => 'fluent-affiliate',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge( array(
				'affiliate_id' => array(
					'type'        => 'integer',
					'description' => 'Affiliate ID',
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
			'status'      => array( 'type' => 'string' ),
			'amount'      => array( 'type' => 'number' ),
			'order_total' => array( 'type' => 'number' ),
			'type'        => array( 'type' => 'string' ),
			'provider'    => array( 'type' => 'string' ),
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
					'status'      => $referral->status ?? '',
					'amount'      => round( (float) ( $referral->amount ?? 0 ), 2 ),
					'order_total' => round( (float) ( $referral->order_total ?? 0 ), 2 ),
					'type'        => $referral->type ?? 'sale',
					'provider'    => $referral->provider ?? '',
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

	$reg->write( 'fluent-affiliate/create-referral', array(
		'label'       => 'Create Manual Referral',
		'description' => 'Create a manual referral for an affiliate. Used for offline sales or custom commissions. Amount is in the site currency (DOUBLE, not cents).',
		'category'    => 'fluent-affiliate',
		'annotations' => array( 'idempotent' => false ),
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'affiliate_id' => array(
					'type'        => 'integer',
					'description' => 'Affiliate ID',
				),
				'amount' => array(
					'type'        => 'number',
					'description' => 'Commission amount (e.g. 25.50). Stored as DOUBLE.',
				),
				'description' => array(
					'type'        => 'string',
					'description' => 'Referral description',
				),
				'status' => array(
					'type'        => 'string',
					'description' => 'Initial status: pending or unpaid (default: pending)',
					'default'     => 'pending',
				),
				'type' => array(
					'type'        => 'string',
					'description' => 'Referral type: sale, opt_in, or recurring_sale (default: sale)',
					'default'     => 'sale',
				),
				'order_total' => array(
					'type'        => 'number',
					'description' => 'Original order total (optional)',
				),
			),
			'required' => array( 'affiliate_id', 'amount' ),
		),
		'callback' => function( $input ) {
			$affiliate = \FluentAffiliate\App\Models\Affiliate::find( (int) $input['affiliate_id'] );
			if ( ! $affiliate ) {
				return fluent_abilities_error( 'not_found', 'Affiliate not found.' );
			}

			if ( $affiliate->status !== 'active' ) {
				return fluent_abilities_error( 'ability_invalid_input', 'Cannot create referral for non-active affiliate (status: ' . ( $affiliate->status ?? 'unknown' ) . ').' );
			}

			$currency = \FluentAffiliate\App\Helper\Utility::getCurrency();

			$data = array(
				'affiliate_id' => (int) $affiliate->id,
				'amount'       => (float) $input['amount'],
				'description'  => sanitize_text_field( $input['description'] ?? '' ),
				'status'       => sanitize_text_field( $input['status'] ?? 'pending' ),
				'type'         => sanitize_text_field( $input['type'] ?? 'sale' ),
				'provider'     => 'manual',
				'currency'     => $currency,
			);

			if ( isset( $input['order_total'] ) ) {
				$data['order_total'] = (float) $input['order_total'];
			}

			$referral = \FluentAffiliate\App\Models\Referral::create( $data );

			if ( $referral->status !== 'unpaid' ) {
				do_action( 'fluent_affiliate/referral_created', $referral );
			} else {
				do_action( 'fluent_affiliate/referral_marked_unpaid', $referral );
			}

			return array(
				'success'     => true,
				'referral_id' => (int) $referral->id,
				'amount'      => round( (float) $referral->amount, 2 ),
				'status'      => $referral->status ?? '',
				'message'     => 'Manual referral created.',
			);
		},
	) );

	$reg->write( 'fluent-affiliate/update-referral', array(
		'label'       => 'Update Referral',
		'description' => 'Update a referral\'s amount, status, or description. Cannot modify paid referrals.',
		'category'    => 'fluent-affiliate',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'id' => array(
					'type'        => 'integer',
					'description' => 'Referral ID',
				),
				'amount' => array(
					'type'        => 'number',
					'description' => 'New commission amount',
				),
				'status' => array(
					'type'        => 'string',
					'description' => 'New status: pending, unpaid, rejected, cancelled',
				),
				'description' => array(
					'type'        => 'string',
					'description' => 'Updated description',
				),
			),
			'required' => array( 'id' ),
		),
		'callback' => function( $input ) {
			$referral = \FluentAffiliate\App\Models\Referral::find( (int) $input['id'] );
			if ( ! $referral ) {
				return fluent_abilities_error( 'not_found', 'Referral not found.' );
			}

			if ( $referral->status === 'paid' ) {
				return fluent_abilities_error( 'ability_invalid_input', 'Cannot modify a paid referral. It has already been included in a payout.' );
			}

			$update = array();
			if ( isset( $input['amount'] ) ) {
				$update['amount'] = (float) $input['amount'];
			}
			if ( isset( $input['status'] ) ) {
				$allowed = array( 'pending', 'unpaid', 'rejected', 'cancelled' );
				$new_status = sanitize_text_field( $input['status'] );
				if ( ! in_array( $new_status, $allowed, true ) ) {
					return fluent_abilities_error( 'ability_invalid_input', 'Status must be one of: ' . implode( ', ', $allowed ) . '. Use payout processing to mark as paid.' );
				}
				$update['status'] = $new_status;
			}
			if ( isset( $input['description'] ) ) {
				$update['description'] = sanitize_text_field( $input['description'] );
			}

			if ( empty( $update ) ) {
				return fluent_abilities_error( 'ability_invalid_input', 'No fields to update.' );
			}

			$referral->fill( $update )->save();

			if ( isset( $update['status'] ) && $update['status'] === 'unpaid' ) {
				do_action( 'fluent_affiliate/referral_marked_unpaid', $referral );
			} elseif ( isset( $update['status'] ) && $update['status'] === 'rejected' ) {
				do_action( 'fluent_affiliate/referral_marked_rejected', $referral );
			}

			if ( isset( $update['amount'] ) ) {
				do_action( 'fluent_affiliate/referral_commission_updated', $referral );
			}

			return array(
				'success'     => true,
				'referral_id' => (int) $referral->id,
				'status'      => $referral->status ?? '',
				'amount'      => round( (float) ( $referral->amount ?? 0 ), 2 ),
			);
		},
	) );

	$reg->delete( 'fluent-affiliate/delete-referral', array(
		'label'       => 'Delete Referral',
		'description' => 'Permanently delete a referral. Cannot delete paid referrals.',
		'category'    => 'fluent-affiliate',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'id' => array(
					'type'        => 'integer',
					'description' => 'Referral ID',
				),
			),
			'required' => array( 'id' ),
		),
		'callback' => function( $input ) {
			$referral = \FluentAffiliate\App\Models\Referral::find( (int) $input['id'] );
			if ( ! $referral ) {
				return fluent_abilities_error( 'not_found', 'Referral not found.' );
			}

			if ( $referral->status === 'paid' ) {
				return fluent_abilities_error( 'ability_invalid_input', 'Cannot delete a paid referral. It is part of a completed payout.' );
			}

			$referral_id = (int) $referral->id;
			$affiliate   = \FluentAffiliate\App\Models\Affiliate::find( $referral->affiliate_id );

			do_action( 'fluent_affiliate/referral/before_delete', $referral );
			$referral->delete();
			do_action( 'fluent_affiliate/referral/deleted', $referral_id, $affiliate );

			return array(
				'success'    => true,
				'deleted_id' => $referral_id,
			);
		},
	) );

	// =========================================================================
	// VISITS
	// =========================================================================

	$reg->read( 'fluent-affiliate/list-visits', array(
		'label'       => 'List Visits',
		'description' => 'List affiliate visit/click tracking data with UTM parameters. Paginated.',
		'category'    => 'fluent-affiliate',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge( array(
				'affiliate_id' => array(
					'type'        => 'integer',
					'description' => 'Filter by affiliate ID',
				),
			), fluent_abilities_pagination_schema() ),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'visits', array(
			'id'           => array( 'type' => 'integer' ),
			'affiliate_id' => array( 'type' => 'integer' ),
			'referral_id'  => array( 'type' => 'integer' ),
			'url'          => array( 'type' => 'string' ),
			'referrer'     => array( 'type' => 'string' ),
			'utm_campaign' => array( 'type' => 'string' ),
			'utm_medium'   => array( 'type' => 'string' ),
			'utm_source'   => array( 'type' => 'string' ),
			'created_at'   => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$pagination = fluent_abilities_pagination( $input );
			$query      = \FluentAffiliate\App\Models\Visit::query();

			if ( ! empty( $input['affiliate_id'] ) ) {
				$query->where( 'affiliate_id', (int) $input['affiliate_id'] );
			}

			$total  = $query->count();
			$visits = $query->orderBy( 'id', 'DESC' )
				->offset( $pagination['offset'] )
				->limit( $pagination['per_page'] )
				->get();

			$items = array();
			foreach ( $visits as $visit ) {
				$items[] = array(
					'id'           => (int) $visit->id,
					'affiliate_id' => (int) ( $visit->affiliate_id ?? 0 ),
					'referral_id'  => (int) ( $visit->referral_id ?? 0 ),
					'url'          => $visit->url ?? '',
					'referrer'     => $visit->referrer ?? '',
					'utm_campaign' => $visit->utm_campaign ?? '',
					'utm_medium'   => $visit->utm_medium ?? '',
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

	$reg->read( 'fluent-affiliate/list-affiliate-visits', array(
		'label'       => 'List Affiliate Visits',
		'description' => 'List visits for a specific affiliate. Shortcut for list-visits with affiliate_id filter.',
		'category'    => 'fluent-affiliate',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge( array(
				'affiliate_id' => array(
					'type'        => 'integer',
					'description' => 'Affiliate ID',
				),
			), fluent_abilities_pagination_schema() ),
			'required' => array( 'affiliate_id' ),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'visits', array(
			'id'           => array( 'type' => 'integer' ),
			'referral_id'  => array( 'type' => 'integer' ),
			'url'          => array( 'type' => 'string' ),
			'referrer'     => array( 'type' => 'string' ),
			'utm_campaign' => array( 'type' => 'string' ),
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
					'referral_id'  => (int) ( $visit->referral_id ?? 0 ),
					'url'          => $visit->url ?? '',
					'referrer'     => $visit->referrer ?? '',
					'utm_campaign' => $visit->utm_campaign ?? '',
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

	// =========================================================================
	// CUSTOMERS
	// =========================================================================

	$reg->read( 'fluent-affiliate/list-customers', array(
		'label'       => 'List Referred Customers',
		'description' => 'List customers referred by affiliates. Paginated.',
		'category'    => 'fluent-affiliate',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge( array(
				'affiliate_id' => array(
					'type'        => 'integer',
					'description' => 'Filter by referring affiliate ID',
				),
				'search' => array(
					'type'        => 'string',
					'description' => 'Search by customer email or name',
				),
			), fluent_abilities_pagination_schema() ),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'customers', array(
			'id'              => array( 'type' => 'integer' ),
			'email'           => array( 'type' => 'string' ),
			'first_name'      => array( 'type' => 'string' ),
			'last_name'       => array( 'type' => 'string' ),
			'by_affiliate_id' => array( 'type' => 'integer' ),
			'user_id'         => array( 'type' => 'integer' ),
			'created_at'      => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$pagination = fluent_abilities_pagination( $input );
			$query      = \FluentAffiliate\App\Models\Customer::query();

			if ( ! empty( $input['affiliate_id'] ) ) {
				$query->where( 'by_affiliate_id', (int) $input['affiliate_id'] );
			}

			if ( ! empty( $input['search'] ) ) {
				$search = sanitize_text_field( $input['search'] );
				$query->where( function( $q ) use ( $search ) {
					$q->where( 'email', 'LIKE', "%{$search}%" )
					  ->orWhere( 'first_name', 'LIKE', "%{$search}%" )
					  ->orWhere( 'last_name', 'LIKE', "%{$search}%" );
				} );
			}

			$total     = $query->count();
			$customers = $query->orderBy( 'id', 'DESC' )
				->offset( $pagination['offset'] )
				->limit( $pagination['per_page'] )
				->get();

			$items = array();
			foreach ( $customers as $customer ) {
				$items[] = array(
					'id'              => (int) $customer->id,
					'email'           => $customer->email ?? '',
					'first_name'      => $customer->first_name ?? '',
					'last_name'       => $customer->last_name ?? '',
					'by_affiliate_id' => (int) ( $customer->by_affiliate_id ?? 0 ),
					'user_id'         => (int) ( $customer->user_id ?? 0 ),
					'created_at'      => (string) ( $customer->created_at ?? '' ),
				);
			}

			return array(
				'customers' => $items,
				'total'     => (int) $total,
				'page'      => $pagination['page'],
				'per_page'  => $pagination['per_page'],
			);
		},
	) );

	// =========================================================================
	// AFFILIATE GROUPS
	// =========================================================================

	$reg->read( 'fluent-affiliate/list-affiliate-groups', array(
		'label'       => 'List Affiliate Groups',
		'description' => 'List affiliate groups (commission rate tiers). Groups are stored in the fa_meta table.',
		'category'    => 'fluent-affiliate',
		'output_schema' => fluent_abilities_schema_collection_output( 'groups', array(
			'id'    => array( 'type' => 'integer' ),
			'title' => array( 'type' => 'string' ),
			'rate'  => array( 'type' => 'number' ),
			'rate_type' => array( 'type' => 'string' ),
		) ),
		'callback' => function() {
			$groups = \FluentAffiliate\App\Models\Meta::where( 'object_type', 'affiliate_group' )
				->orderBy( 'id', 'ASC' )
				->get();

			$items = array();
			foreach ( $groups as $group ) {
				$value = maybe_unserialize( $group->value );
				$items[] = array(
					'id'        => (int) $group->id,
					'title'     => is_array( $value ) ? ( $value['title'] ?? '' ) : '',
					'rate'      => is_array( $value ) ? (float) ( $value['rate'] ?? 0 ) : 0,
					'rate_type' => is_array( $value ) ? ( $value['rate_type'] ?? 'percentage' ) : 'percentage',
				);
			}

			return array(
				'groups' => $items,
				'total'  => count( $items ),
			);
		},
	) );

	// =========================================================================
	// UTILITY
	// =========================================================================

	$reg->read( 'fluent-affiliate/get-option', array(
		'label'       => 'Get FluentAffiliate Option',
		'description' => 'Get a configuration option from FluentAffiliate. Options are stored in the fa_meta table, not wp_options.',
		'category'    => 'fluent-affiliate',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'key' => array(
					'type'        => 'string',
					'description' => 'Option key to retrieve',
				),
			),
			'required' => array( 'key' ),
		),
		'callback' => function( $input ) {
			$key   = sanitize_text_field( $input['key'] );
			$value = fluentAffiliate_get_option( $key, null );

			return array(
				'key'   => $key,
				'value' => $value,
			);
		},
	) );

	$reg->read( 'fluent-affiliate/get-currencies', array(
		'label'       => 'Get Available Currencies',
		'description' => 'Get the list of available currencies configured in FluentAffiliate.',
		'category'    => 'fluent-affiliate',
		'callback'    => function() {
			$currencies      = \FluentAffiliate\App\Helper\Helper::getCurrencies();
			$current         = \FluentAffiliate\App\Helper\Utility::getCurrency();
			$current_symbol  = \FluentAffiliate\App\Helper\Utility::getCurrencySymbol( $current );

			return array(
				'current_currency' => $current ?? '',
				'current_symbol'   => $current_symbol ?? '',
				'total'            => count( $currencies ),
				'currencies'       => $currencies,
			);
		},
	) );

} );
