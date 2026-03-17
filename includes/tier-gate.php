<?php
/**
 * Tier Gate — Wraps Pro ability callbacks with license verification.
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

/**
 * Wrap an execute_callback with a Pro license check.
 *
 * If the license is not active, returns a Pro Required error.
 * If the license IS active, calls the original callback.
 *
 * @param string   $ability_name The ability slug (for error messages).
 * @param callable $callback     The original execute_callback.
 * @return callable Wrapped callback.
 */
function fluent_abilities_pro_gate( $ability_name, $callback ) {
	return function( $params ) use ( $ability_name, $callback ) {
		if ( ! Fluent_Abilities_License_Manager::is_pro_active() ) {
			return Fluent_Abilities_License_Manager::pro_required_error( $ability_name );
		}
		return $callback( $params );
	};
}
