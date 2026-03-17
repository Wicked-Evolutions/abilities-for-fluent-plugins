<?php
/**
 * FluentAffiliate Abilities — Reports & Dashboard
 *
 * Dashboard statistics, chart data, and commerce reports.
 *
 * 4 abilities in the 'fluent-affiliate' category.
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {

	$reg = new Fluent_Abilities_Registrar( 'affiliate' );

	// =========================================================================
	// REPORTS & DASHBOARD
	// =========================================================================

	$reg->read( 'fluent-affiliate/get-dashboard-stats', array(
		'label'       => 'Get Dashboard Stats',
		'description' => 'Get top-level affiliate program statistics: total affiliates, referrals, earnings, unpaid amounts, and conversion rates.',
		'category'    => 'fluent-affiliate',
		'callback'    => function() {
			$total_affiliates = (int) \FluentAffiliate\App\Models\Affiliate::count();
			$active_affiliates = (int) \FluentAffiliate\App\Models\Affiliate::where( 'status', 'active' )->count();
			$pending_affiliates = (int) \FluentAffiliate\App\Models\Affiliate::where( 'status', 'pending' )->count();

			$total_referrals = (int) \FluentAffiliate\App\Models\Referral::count();
			$pending_referrals = (int) \FluentAffiliate\App\Models\Referral::where( 'status', 'pending' )->count();
			$unpaid_referrals = (int) \FluentAffiliate\App\Models\Referral::where( 'status', 'unpaid' )->count();
			$paid_referrals = (int) \FluentAffiliate\App\Models\Referral::where( 'status', 'paid' )->count();

			$total_visits = (int) \FluentAffiliate\App\Models\Visit::count();

			$total_earnings = round( (float) ( \FluentAffiliate\App\Models\Affiliate::sum( 'total_earnings' ) ?? 0 ), 2 );
			$total_unpaid   = round( (float) ( \FluentAffiliate\App\Models\Affiliate::sum( 'unpaid_earnings' ) ?? 0 ), 2 );

			$total_paid_out = round( (float) ( \FluentAffiliate\App\Models\Payout::where( 'status', 'paid' )->sum( 'total_amount' ) ?? 0 ), 2 );

			$conversion_rate = $total_visits > 0 ? round( ( $total_referrals / $total_visits ) * 100, 2 ) : 0;

			$currency = \FluentAffiliate\App\Helper\Utility::getCurrency();

			return array(
				'affiliates' => array(
					'total'   => $total_affiliates,
					'active'  => $active_affiliates,
					'pending' => $pending_affiliates,
				),
				'referrals' => array(
					'total'   => $total_referrals,
					'pending' => $pending_referrals,
					'unpaid'  => $unpaid_referrals,
					'paid'    => $paid_referrals,
				),
				'visits'          => $total_visits,
				'conversion_rate' => $conversion_rate,
				'earnings'        => array(
					'total'    => $total_earnings,
					'unpaid'   => $total_unpaid,
					'paid_out' => $total_paid_out,
				),
				'currency' => $currency ?? '',
			);
		},
	) );

	$reg->read( 'fluent-affiliate/get-dashboard-chart', array(
		'label'       => 'Get Dashboard Chart Data',
		'description' => 'Get time-series data for the affiliate program dashboard. Shows referrals, visits, and earnings per day over a date range.',
		'category'    => 'fluent-affiliate',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'date_from' => array(
					'type'        => 'string',
					'description' => 'Start date (YYYY-MM-DD). Default: 30 days ago.',
				),
				'date_to' => array(
					'type'        => 'string',
					'description' => 'End date (YYYY-MM-DD). Default: today.',
				),
			),
		),
		'callback' => function( $input ) {
			global $wpdb;

			$date_from = sanitize_text_field( $input['date_from'] ?? gmdate( 'Y-m-d', strtotime( '-30 days' ) ) );
			$date_to   = sanitize_text_field( $input['date_to'] ?? gmdate( 'Y-m-d' ) );

			$prefix = $wpdb->prefix;

			// Referrals per day.
			$referral_data = $wpdb->get_results( $wpdb->prepare(
				"SELECT DATE(created_at) as date, COUNT(*) as count, SUM(amount) as total_amount
				 FROM {$prefix}fa_referrals
				 WHERE created_at >= %s AND created_at <= %s
				 GROUP BY DATE(created_at)
				 ORDER BY date ASC",
				$date_from . ' 00:00:00',
				$date_to . ' 23:59:59'
			) );

			// Visits per day.
			$visit_data = $wpdb->get_results( $wpdb->prepare(
				"SELECT DATE(created_at) as date, COUNT(*) as count
				 FROM {$prefix}fa_visits
				 WHERE created_at >= %s AND created_at <= %s
				 GROUP BY DATE(created_at)
				 ORDER BY date ASC",
				$date_from . ' 00:00:00',
				$date_to . ' 23:59:59'
			) );

			$referrals_by_day = array();
			foreach ( $referral_data as $row ) {
				$referrals_by_day[] = array(
					'date'    => $row->date,
					'count'   => (int) $row->count,
					'amount'  => round( (float) ( $row->total_amount ?? 0 ), 2 ),
				);
			}

			$visits_by_day = array();
			foreach ( $visit_data as $row ) {
				$visits_by_day[] = array(
					'date'  => $row->date,
					'count' => (int) $row->count,
				);
			}

			return array(
				'date_from'  => $date_from,
				'date_to'    => $date_to,
				'referrals'  => $referrals_by_day,
				'visits'     => $visits_by_day,
			);
		},
	) );

	$reg->read( 'fluent-affiliate/list-report-providers', array(
		'label'       => 'List Report Providers',
		'description' => 'List available advanced report providers (e.g., WooCommerce, FluentCart).',
		'category'    => 'fluent-affiliate',
		'callback'    => function() {
			// Get unique providers from referrals.
			$providers = \FluentAffiliate\App\Models\Referral::select( 'provider' )
				->whereNotNull( 'provider' )
				->where( 'provider', '!=', '' )
				->groupBy( 'provider' )
				->get()
				->pluck( 'provider' )
				->toArray();

			$items = array();
			foreach ( $providers as $provider ) {
				$count = (int) \FluentAffiliate\App\Models\Referral::where( 'provider', $provider )->count();
				$items[] = array(
					'provider'       => $provider,
					'referral_count' => $count,
				);
			}

			return array(
				'providers' => $items,
				'total'     => count( $items ),
			);
		},
	) );

	$reg->read( 'fluent-affiliate/get-commerce-report', array(
		'label'       => 'Get Commerce Report',
		'description' => 'Get commerce analytics for a specific provider: total sales, commissions, top affiliates.',
		'category'    => 'fluent-affiliate',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'provider' => array(
					'type'        => 'string',
					'description' => 'Provider slug: woo, edd, fluentcart, manual, etc.',
				),
				'date_from' => array(
					'type'        => 'string',
					'description' => 'Start date (YYYY-MM-DD). Default: 30 days ago.',
				),
				'date_to' => array(
					'type'        => 'string',
					'description' => 'End date (YYYY-MM-DD). Default: today.',
				),
			),
			'required' => array( 'provider' ),
		),
		'callback' => function( $input ) {
			$provider  = sanitize_text_field( $input['provider'] );
			$date_from = sanitize_text_field( $input['date_from'] ?? gmdate( 'Y-m-d', strtotime( '-30 days' ) ) );
			$date_to   = sanitize_text_field( $input['date_to'] ?? gmdate( 'Y-m-d' ) );

			$query = \FluentAffiliate\App\Models\Referral::where( 'provider', $provider )
				->where( 'created_at', '>=', $date_from . ' 00:00:00' )
				->where( 'created_at', '<=', $date_to . ' 23:59:59' );

			$total_referrals  = (int) $query->count();
			$total_commission = round( (float) ( $query->sum( 'amount' ) ?? 0 ), 2 );
			$total_order_value = round( (float) ( $query->sum( 'order_total' ) ?? 0 ), 2 );

			// Status breakdown.
			$by_status = array();
			foreach ( array( 'pending', 'unpaid', 'paid', 'rejected', 'cancelled' ) as $status ) {
				$count = (int) \FluentAffiliate\App\Models\Referral::where( 'provider', $provider )
					->where( 'status', $status )
					->where( 'created_at', '>=', $date_from . ' 00:00:00' )
					->where( 'created_at', '<=', $date_to . ' 23:59:59' )
					->count();
				if ( $count > 0 ) {
					$by_status[ $status ] = $count;
				}
			}

			// Top 10 affiliates by commission for this provider.
			global $wpdb;
			$prefix = $wpdb->prefix;

			$top_affiliates_raw = $wpdb->get_results( $wpdb->prepare(
				"SELECT affiliate_id, COUNT(*) as referral_count, SUM(amount) as total_commission
				 FROM {$prefix}fa_referrals
				 WHERE provider = %s AND created_at >= %s AND created_at <= %s
				 GROUP BY affiliate_id
				 ORDER BY total_commission DESC
				 LIMIT 10",
				$provider,
				$date_from . ' 00:00:00',
				$date_to . ' 23:59:59'
			) );

			$top_affiliates = array();
			foreach ( $top_affiliates_raw as $row ) {
				$affiliate = \FluentAffiliate\App\Models\Affiliate::find( $row->affiliate_id );
				$top_affiliates[] = array(
					'affiliate_id'    => (int) $row->affiliate_id,
					'user_email'      => $affiliate && $affiliate->user ? $affiliate->user->user_email : '',
					'referral_count'  => (int) $row->referral_count,
					'total_commission' => round( (float) ( $row->total_commission ?? 0 ), 2 ),
				);
			}

			return array(
				'provider'          => $provider,
				'date_from'         => $date_from,
				'date_to'           => $date_to,
				'total_referrals'   => $total_referrals,
				'total_commission'  => $total_commission,
				'total_order_value' => $total_order_value,
				'by_status'         => $by_status,
				'top_affiliates'    => $top_affiliates,
				'currency'          => \FluentAffiliate\App\Helper\Utility::getCurrency() ?? '',
			);
		},
	) );

} );
