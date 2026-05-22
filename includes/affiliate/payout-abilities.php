<?php
/**
 * FluentAffiliate Abilities — Payouts
 *
 * Payout CRUD, transactions, processing, and validation.
 *
 * 10 abilities in the 'fluent-affiliate' category.
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {

	$reg = new Fluent_Abilities_Registrar( 'affiliate' );

	// =========================================================================
	// PAYOUTS
	// =========================================================================

	$reg->read( 'fluent-affiliate/list-payouts', array(
		'label'       => 'List Payouts',
		'description' => 'List payout batches with status and amount summaries. Paginated.',
		'category'    => 'fluent-affiliate',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge( array(
				'status' => array(
					'type'        => 'string',
					'description' => 'Filter by status: draft, processing, paid',
				),
			), fluent_abilities_pagination_schema() ),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'payouts', array(
			'id'             => array( 'type' => 'integer' ),
			'title'          => array( 'type' => 'string' ),
			'total_amount'   => array( 'type' => 'number' ),
			'payout_method'  => array( 'type' => 'string' ),
			'status'         => array( 'type' => 'string' ),
			'currency'       => array( 'type' => 'string' ),
			'created_by'     => array( 'type' => 'integer' ),
			'created_at'     => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$pagination = fluent_abilities_pagination( $input );
			$query      = \FluentAffiliate\App\Models\Payout::query();

			if ( ! empty( $input['status'] ) ) {
				$query->where( 'status', sanitize_text_field( $input['status'] ) );
			}

			$total   = $query->count();
			$payouts = $query->orderBy( 'id', 'DESC' )
				->offset( $pagination['offset'] )
				->limit( $pagination['per_page'] )
				->get();

			$items = array();
			foreach ( $payouts as $payout ) {
				$items[] = array(
					'id'            => (int) $payout->id,
					'title'         => $payout->title ?? '',
					'total_amount'  => round( (float) ( $payout->total_amount ?? 0 ), 2 ),
					'payout_method' => $payout->payout_method ?? 'manual',
					'status'        => $payout->status ?? '',
					'currency'      => $payout->currency ?? '',
					'created_by'    => (int) ( $payout->created_by ?? 0 ),
					'created_at'    => (string) ( $payout->created_at ?? '' ),
				);
			}

			return array(
				'payouts'  => $items,
				'total'    => (int) $total,
				'page'     => $pagination['page'],
				'per_page' => $pagination['per_page'],
			);
		},
	) );

	$reg->read( 'fluent-affiliate/get-payout', array(
		'label'       => 'Get Payout',
		'description' => 'Get a single payout batch with details.',
		'category'    => 'fluent-affiliate',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'id' => array(
					'type'        => 'integer',
					'description' => 'Payout ID',
				),
			),
			'required' => array( 'id' ),
		),
		'callback' => function( $input ) {
			$payout = \FluentAffiliate\App\Models\Payout::find( (int) $input['id'] );
			if ( ! $payout ) {
				return fluent_abilities_error( 'not_found', 'Payout not found.' );
			}

			$transaction_count = (int) \FluentAffiliate\App\Models\Transaction::where( 'payout_id', $payout->id )->count();
			$referral_count    = (int) \FluentAffiliate\App\Models\Referral::where( 'payout_id', $payout->id )->count();

			return array(
				'id'                => (int) $payout->id,
				'title'             => $payout->title ?? '',
				'description'       => $payout->description ?? '',
				'total_amount'      => round( (float) ( $payout->total_amount ?? 0 ), 2 ),
				'payout_method'     => $payout->payout_method ?? 'manual',
				'status'            => $payout->status ?? '',
				'currency'          => $payout->currency ?? '',
				'created_by'        => (int) ( $payout->created_by ?? 0 ),
				'transaction_count' => $transaction_count,
				'referral_count'    => $referral_count,
				'created_at'        => (string) ( $payout->created_at ?? '' ),
				'updated_at'        => (string) ( $payout->updated_at ?? '' ),
			);
		},
	) );

	$reg->read( 'fluent-affiliate/get-payout-referrals', array(
		'label'       => 'Get Payout Referrals',
		'description' => 'List referrals included in a specific payout batch.',
		'category'    => 'fluent-affiliate',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge( array(
				'payout_id' => array(
					'type'        => 'integer',
					'description' => 'Payout ID',
				),
			), fluent_abilities_pagination_schema() ),
			'required' => array( 'payout_id' ),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'referrals', array(
			'id'           => array( 'type' => 'integer' ),
			'affiliate_id' => array( 'type' => 'integer' ),
			'amount'       => array( 'type' => 'number' ),
			'status'       => array( 'type' => 'string' ),
			'type'         => array( 'type' => 'string' ),
			'provider'     => array( 'type' => 'string' ),
			'created_at'   => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$payout = \FluentAffiliate\App\Models\Payout::find( (int) $input['payout_id'] );
			if ( ! $payout ) {
				return fluent_abilities_error( 'not_found', 'Payout not found.' );
			}

			$pagination = fluent_abilities_pagination( $input );
			$query      = \FluentAffiliate\App\Models\Referral::where( 'payout_id', $payout->id );

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
					'amount'       => round( (float) ( $referral->amount ?? 0 ), 2 ),
					'status'       => $referral->status ?? '',
					'type'         => $referral->type ?? 'sale',
					'provider'     => $referral->provider ?? '',
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

	$reg->read( 'fluent-affiliate/list-payout-transactions', array(
		'label'       => 'List Payout Transactions',
		'description' => 'List per-affiliate transactions within a payout batch.',
		'category'    => 'fluent-affiliate',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge( array(
				'payout_id' => array(
					'type'        => 'integer',
					'description' => 'Payout ID',
				),
			), fluent_abilities_pagination_schema() ),
			'required' => array( 'payout_id' ),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'transactions', array(
			'id'             => array( 'type' => 'integer' ),
			'affiliate_id'   => array( 'type' => 'integer' ),
			'total_amount'   => array( 'type' => 'number' ),
			'payout_method'  => array( 'type' => 'string' ),
			'status'         => array( 'type' => 'string' ),
			'currency'       => array( 'type' => 'string' ),
			'created_at'     => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$payout = \FluentAffiliate\App\Models\Payout::find( (int) $input['payout_id'] );
			if ( ! $payout ) {
				return fluent_abilities_error( 'not_found', 'Payout not found.' );
			}

			$pagination   = fluent_abilities_pagination( $input );
			$query        = \FluentAffiliate\App\Models\Transaction::where( 'payout_id', $payout->id );

			$total        = $query->count();
			$transactions = $query->orderBy( 'id', 'DESC' )
				->offset( $pagination['offset'] )
				->limit( $pagination['per_page'] )
				->get();

			$items = array();
			foreach ( $transactions as $tx ) {
				$items[] = array(
					'id'            => (int) $tx->id,
					'affiliate_id'  => (int) ( $tx->affiliate_id ?? 0 ),
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

	$reg->read( 'fluent-affiliate/list-affiliate-transactions', array(
		'label'       => 'List Affiliate Transactions',
		'description' => 'List payout transactions for a specific affiliate across all payouts.',
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
		'output_schema' => fluent_abilities_schema_list_output( 'transactions', array(
			'id'            => array( 'type' => 'integer' ),
			'payout_id'     => array( 'type' => 'integer' ),
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
					'payout_id'     => (int) ( $tx->payout_id ?? 0 ),
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

	$reg->read( 'fluent-affiliate/validate-payout-config', array(
		'label'       => 'Validate Payout Configuration',
		'description' => 'Preview what a payout would look like without processing it. Shows eligible affiliates, amounts, and referral counts.',
		'category'    => 'fluent-affiliate',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'payout_method' => array(
					'type'        => 'string',
					'description' => 'Payment method: manual, paypal, bank_transfer (default: manual)',
					'default'     => 'manual',
				),
				'minimum_amount' => array(
					'type'        => 'number',
					'description' => 'Minimum payout threshold (default: 0)',
					'default'     => 0,
				),
			),
		),
		'callback' => function( $input ) {
			$minimum = (float) ( $input['minimum_amount'] ?? 0 );

			// Find affiliates with unpaid referrals.
			$affiliates = \FluentAffiliate\App\Models\Affiliate::where( 'status', 'active' )
				->where( 'unpaid_earnings', '>', $minimum )
				->get();

			$eligible = array();
			$total_amount = 0;

			foreach ( $affiliates as $affiliate ) {
				$unpaid_count = (int) \FluentAffiliate\App\Models\Referral::where( 'affiliate_id', $affiliate->id )
					->where( 'status', 'unpaid' )
					->count();

				$unpaid = round( (float) ( $affiliate->unpaid_earnings ?? 0 ), 2 );
				$total_amount += $unpaid;

				$eligible[] = array(
					'affiliate_id'   => (int) $affiliate->id,
					'user_email'     => $affiliate->user->user_email ?? '',
					'payment_email'  => $affiliate->payment_email ?? '',
					'unpaid_amount'  => $unpaid,
					'referral_count' => $unpaid_count,
				);
			}

			return array(
				'status'           => 'preview',
				'eligible_count'   => count( $eligible ),
				'total_amount'     => round( $total_amount, 2 ),
				'currency'         => \FluentAffiliate\App\Helper\Utility::getCurrency() ?? '',
				'payout_method'    => sanitize_text_field( $input['payout_method'] ?? 'manual' ),
				'minimum_amount'   => $minimum,
				'eligible'         => $eligible,
			);
		},
	) );

	$reg->write( 'fluent-affiliate/update-payout', array(
		'label'       => 'Update Payout',
		'description' => 'Update payout batch details: title, description, or status.',
		'category'    => 'fluent-affiliate',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'id' => array(
					'type'        => 'integer',
					'description' => 'Payout ID',
				),
				'title' => array(
					'type'        => 'string',
					'description' => 'Payout title',
				),
				'description' => array(
					'type'        => 'string',
					'description' => 'Payout description',
				),
				'status' => array(
					'type'        => 'string',
					'description' => 'Payout status: draft, processing, paid',
				),
			),
			'required' => array( 'id' ),
		),
		'callback' => function( $input ) {
			$payout = \FluentAffiliate\App\Models\Payout::find( (int) $input['id'] );
			if ( ! $payout ) {
				return fluent_abilities_error( 'not_found', 'Payout not found.' );
			}

			$update = array();
			if ( isset( $input['title'] ) ) {
				$update['title'] = sanitize_text_field( $input['title'] );
			}
			if ( isset( $input['description'] ) ) {
				$update['description'] = sanitize_textarea_field( $input['description'] );
			}
			if ( isset( $input['status'] ) ) {
				$allowed = array( 'draft', 'processing', 'paid' );
				$status  = sanitize_text_field( $input['status'] );
				if ( ! in_array( $status, $allowed, true ) ) {
					return fluent_abilities_error( 'ability_invalid_input', 'Status must be one of: ' . implode( ', ', $allowed ) );
				}
				$update['status'] = $status;
			}

			if ( empty( $update ) ) {
				return fluent_abilities_error( 'ability_invalid_input', 'No fields to update.' );
			}

			$payout->fill( $update )->save();

			return array(
				'success'   => true,
				'payout_id' => (int) $payout->id,
				'message'   => 'Payout updated.',
			);
		},
	) );

	$reg->write( 'fluent-affiliate/update-payout-transaction', array(
		'label'       => 'Update Payout Transaction',
		'description' => 'Update a single payout transaction status or method.',
		'category'    => 'fluent-affiliate',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'payout_id' => array(
					'type'        => 'integer',
					'description' => 'Payout ID',
				),
				'transaction_id' => array(
					'type'        => 'integer',
					'description' => 'Transaction ID',
				),
				'status' => array(
					'type'        => 'string',
					'description' => 'New status: paid, processing',
				),
				'payout_method' => array(
					'type'        => 'string',
					'description' => 'Payment method: manual, paypal, bank_transfer',
				),
			),
			'required' => array( 'payout_id', 'transaction_id' ),
		),
		'callback' => function( $input ) {
			$payout = \FluentAffiliate\App\Models\Payout::find( (int) $input['payout_id'] );
			if ( ! $payout ) {
				return fluent_abilities_error( 'not_found', 'Payout not found.' );
			}

			$transaction = \FluentAffiliate\App\Models\Transaction::where( 'id', (int) $input['transaction_id'] )
				->where( 'payout_id', $payout->id )
				->first();

			if ( ! $transaction ) {
				return fluent_abilities_error( 'not_found', 'Transaction not found in this payout.' );
			}

			$update = array();
			if ( isset( $input['status'] ) ) {
				$allowed = array( 'paid', 'processing' );
				$status  = sanitize_text_field( $input['status'] );
				if ( ! in_array( $status, $allowed, true ) ) {
					return fluent_abilities_error( 'ability_invalid_input', 'Status must be one of: ' . implode( ', ', $allowed ) );
				}
				$update['status'] = $status;
			}
			if ( isset( $input['payout_method'] ) ) {
				$update['payout_method'] = sanitize_text_field( $input['payout_method'] );
			}

			if ( empty( $update ) ) {
				return fluent_abilities_error( 'ability_invalid_input', 'No fields to update.' );
			}

			$previous_status = $transaction->status;
			$transaction->fill( $update )->save();

			if ( isset( $update['status'] ) && $update['status'] === 'paid' && $previous_status !== 'paid' ) {
				do_action( 'fluent_affiliate/payout/transaction/transaction_updated_to_paid', $transaction, $payout );
			}

			return array(
				'success'        => true,
				'transaction_id' => (int) $transaction->id,
				'message'        => 'Transaction updated.',
			);
		},
	) );

	$reg->delete( 'fluent-affiliate/delete-payout-transaction', array(
		'label'       => 'Delete Payout Transaction',
		'description' => 'Delete a single payout transaction.',
		'category'    => 'fluent-affiliate',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'payout_id' => array(
					'type'        => 'integer',
					'description' => 'Payout ID',
				),
				'transaction_id' => array(
					'type'        => 'integer',
					'description' => 'Transaction ID',
				),
			),
			'required' => array( 'payout_id', 'transaction_id' ),
		),
		'callback' => function( $input ) {
			$payout = \FluentAffiliate\App\Models\Payout::find( (int) $input['payout_id'] );
			if ( ! $payout ) {
				return fluent_abilities_error( 'not_found', 'Payout not found.' );
			}

			$transaction = \FluentAffiliate\App\Models\Transaction::where( 'id', (int) $input['transaction_id'] )
				->where( 'payout_id', $payout->id )
				->first();

			if ( ! $transaction ) {
				return fluent_abilities_error( 'not_found', 'Transaction not found in this payout.' );
			}

			$tx_id = (int) $transaction->id;
			$transaction->delete();

			return array(
				'success'    => true,
				'deleted_id' => $tx_id,
			);
		},
	) );

} );
