<?php
/**
 * Register Fluent Ability Categories
 *
 * One category per Fluent product + one cross-module category.
 * Categories must be registered BEFORE abilities that reference them.
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_categories_init', 'fluent_abilities_register_categories' );

/**
 * Register all Fluent ability categories.
 */
function fluent_abilities_register_categories() {

	$categories = array(
		'fluent-crm' => array(
			'label'       => 'FluentCRM',
			'description' => 'Contact management, email campaigns, automations, sequences, tags, lists, companies, smart links, and CRM analytics.',
		),
		'fluent-community' => array(
			'label'       => 'Fluent Community',
			'description' => 'Community feeds, spaces, members, courses, lessons, notifications, scheduled posts, media, reactions, and moderation.',
		),
		'fluent-forms' => array(
			'label'       => 'Fluent Forms',
			'description' => 'Form management, submissions, fields, analytics, and reports.',
		),
		'fluent-support' => array(
			'label'       => 'Fluent Support',
			'description' => 'Support tickets, replies, agents, customers, and service reports.',
		),
		'fluent-boards' => array(
			'label'       => 'Fluent Boards',
			'description' => 'Project boards, tasks, stages, labels, and time tracking.',
		),
		'fluent-booking' => array(
			'label'       => 'FluentBooking',
			'description' => 'Calendars, bookings, availability, and scheduling.',
		),
		'fluent-smtp' => array(
			'label'       => 'FluentSMTP',
			'description' => 'Email delivery settings, logs, and provider configuration.',
		),
		'fluent-auth' => array(
			'label'       => 'FluentAuth',
			'description' => 'Authentication security settings, login logs, and security statistics.',
		),
		'fluent-snippets' => array(
			'label'       => 'Fluent Snippets',
			'description' => 'Code snippet management — create, read, update, delete, and toggle activation.',
		),
		'fluent-messaging' => array(
			'label'       => 'Fluent Messaging',
			'description' => 'Direct messaging conversations, messages, and threads.',
		),
		'fluent-cart' => array(
			'label'       => 'FluentCart',
			'description' => 'Products, orders, customers, subscriptions, coupons, and cart analytics.',
		),
		'fluent-affiliate' => array(
			'label'       => 'FluentAffiliate',
			'description' => 'Affiliate management, referrals, commissions, payouts, visits, customers, and affiliate portal.',
		),
		'fluent' => array(
			'label'       => 'Fluent (Cross-Module)',
			'description' => 'Cross-product abilities: unified user view, dashboard, engagement scoring, and multi-product operations.',
		),
	);

	foreach ( $categories as $slug => $args ) {
		wp_register_ability_category( $slug, $args );
	}
}
