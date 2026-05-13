<?php
/**
 * WordPress Function Stubs for Unit Tests — Abilities for Fluent Plugins
 *
 * Minimal stubs for pure helper functions. Integration tests use real WP test suite.
 */

// Core constants.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wp/' );
}
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}
if ( ! defined( 'FLUENT_ABILITIES_VERSION' ) ) {
	define( 'FLUENT_ABILITIES_VERSION', 'test' );
}

// WP_Error class.
if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		public $errors     = array();
		public $error_data = array();

		public function __construct( $code = '', $message = '', $data = array() ) {
			if ( $code ) {
				$this->errors[ $code ][] = $message;
				if ( $data ) {
					$this->error_data[ $code ] = $data;
				}
			}
		}

		public function get_error_code() {
			$codes = array_keys( $this->errors );
			return $codes[0] ?? '';
		}

		public function get_error_message( $code = '' ) {
			if ( ! $code ) {
				$code = $this->get_error_code();
			}
			return $this->errors[ $code ][0] ?? '';
		}
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}
if ( ! function_exists( 'wp_parse_args' ) ) {
	function wp_parse_args( $args, $defaults = array() ) {
		return array_merge( (array) $defaults, (array) $args );
	}
}
if ( ! function_exists( 'sanitize_email' ) ) {
	function sanitize_email( $email ) {
		return filter_var( $email, FILTER_SANITIZE_EMAIL );
	}
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return trim( strip_tags( $str ) );
	}
}
if ( ! function_exists( 'absint' ) ) {
	function absint( $maybeint ) {
		return abs( (int) $maybeint );
	}
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		$key = strtolower( (string) $key );
		return preg_replace( '/[^a-z0-9_\-]/', '', $key );
	}
}
if ( ! function_exists( 'sanitize_file_name' ) ) {
	function sanitize_file_name( $filename ) {
		return preg_replace( '/[^A-Za-z0-9._\-]/', '', (string) $filename );
	}
}
if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url ) {
		return filter_var( $url, FILTER_SANITIZE_URL );
	}
}
if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type = 'mysql' ) {
		return 'mysql' === $type ? gmdate( 'Y-m-d H:i:s' ) : time();
	}
}
if ( ! function_exists( 'size_format' ) ) {
	function size_format( $bytes, $decimals = 0 ) {
		return number_format( (float) $bytes, $decimals ) . ' B';
	}
}

// In-memory option store.
$_wp_options_store = array();

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $option, $default = false ) {
		global $_wp_options_store;
		return $_wp_options_store[ $option ] ?? $default;
	}
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( $option, $value, $autoload = null ) {
		global $_wp_options_store;
		$_wp_options_store[ $option ] = $value;
		return true;
	}
}
if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( $option ) {
		global $_wp_options_store;
		unset( $_wp_options_store[ $option ] );
		return true;
	}
}
if ( ! function_exists( 'get_site_option' ) ) {
	function get_site_option( $option, $default = false ) {
		return get_option( $option, $default );
	}
}
if ( ! function_exists( 'is_multisite' ) ) {
	function is_multisite() {
		return false;
	}
}

// In-memory registered abilities.
$_wp_registered_abilities = array();

if ( ! function_exists( 'wp_register_ability' ) ) {
	function wp_register_ability( $name, $args ) {
		global $_wp_registered_abilities;
		$_wp_registered_abilities[ $name ] = $args;
	}
}
if ( ! function_exists( 'wp_get_abilities' ) ) {
	function wp_get_abilities() {
		global $_wp_registered_abilities;
		return $_wp_registered_abilities;
	}
}

// Fluent permission helpers (fluent_abilities_user_can, fluent_abilities_module_enabled,
// fluent_abilities_get_enabled_modules) are loaded from the real includes/security.php
// in tests/bootstrap.php so unit tests exercise actual authorization logic.

// Tier gate stub — passthrough. Unit tests do not load includes/tier-gate.php
// or its license-manager dependency; the real gate returns a wrapped callable
// that calls the license check. Stubbing as identity returns the original
// callback unchanged, equivalent to "license active, gate passed".
if ( ! function_exists( 'fluent_abilities_pro_gate' ) ) {
	function fluent_abilities_pro_gate( $ability_name, $callback ) {
		return $callback;
	}
}

// Tests can override these via $GLOBALS['_test_current_user_id'] / _test_user_caps.
if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $capability, ...$args ) {
		if ( isset( $GLOBALS['_test_user_caps'] ) && is_array( $GLOBALS['_test_user_caps'] ) ) {
			return in_array( $capability, $GLOBALS['_test_user_caps'], true );
		}
		return true;
	}
}
if ( ! function_exists( 'get_current_user_id' ) ) {
	function get_current_user_id() {
		return $GLOBALS['_test_current_user_id'] ?? 1;
	}
}
if ( ! function_exists( 'defined' ) ) {
	// PHP builtin — don't override.
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) {}
}
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value, ...$args ) {
		return $value;
	}
}
if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '' ) {
		return 'https://example.com' . $path;
	}
}
