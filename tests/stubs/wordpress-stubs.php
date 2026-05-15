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

// WP_REST_Response class (minimal stub — surface required by the player
// invoke_controller normalization path AND by CRM extended-misc-* proxy
// helpers that call ->is_error() / ->as_error() on a rest_do_request() return).
if ( ! class_exists( 'WP_REST_Response' ) ) {
	class WP_REST_Response {
		protected $data;
		protected $status;
		protected $is_error;
		public function __construct( $data = null, $status = 200, $is_error = false ) {
			$this->data     = $data;
			$this->status   = $status;
			$this->is_error = (bool) $is_error;
		}
		public function get_data() {
			return $this->data;
		}
		public function is_error() {
			return $this->is_error;
		}
		public function as_error() {
			return $this->data instanceof WP_Error ? $this->data : new WP_Error( 'rest_error', 'REST error' );
		}
	}
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
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		$key = strtolower( (string) $key );
		return preg_replace( '/[^a-z0-9_\-]/', '', $key );
	}
}
if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( $data ) {
		return $data;
	}
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, $options = 0, $depth = 512 ) {
		return json_encode( $data, $options, $depth );
	}
}
if ( ! function_exists( 'maybe_unserialize' ) ) {
	function maybe_unserialize( $data ) {
		if ( is_string( $data ) && '' !== $data ) {
			$un = @unserialize( $data );
			if ( false !== $un || 'b:0;' === $data ) {
				return $un;
			}
		}
		return $data;
	}
}
if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type, $gmt = 0 ) {
		return ( 'mysql' === $type ) ? gmdate( 'Y-m-d H:i:s' ) : time();
	}
}
if ( ! function_exists( 'wp_get_current_user' ) ) {
	function wp_get_current_user() {
		$u = new stdClass();
		$u->ID = $GLOBALS['_test_current_user_id'] ?? 1;
		$u->display_name = 'Test User';
		$u->user_email   = 'test@example.com';
		$u->allcaps      = array();
		if ( isset( $GLOBALS['_test_user_caps'] ) && is_array( $GLOBALS['_test_user_caps'] ) ) {
			foreach ( $GLOBALS['_test_user_caps'] as $cap ) {
				$u->allcaps[ $cap ] = true;
			}
		}
		return $u;
	}
}
if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url ) {
		return $url;
	}
}
if ( ! function_exists( 'get_user_by' ) ) {
	function get_user_by( $field, $value ) {
		return new class( (int) $value ) {
			public $ID;
			public $display_name = 'Test User';
			public $user_email   = 'test@example.com';
			public function __construct( $id ) {
				$this->ID = $id;
			}
			public function add_cap( $cap ) {}
			public function remove_cap( $cap ) {}
		};
	}
}
if ( ! function_exists( 'update_user_meta' ) ) {
	function update_user_meta( $user_id, $key, $value ) {
		$GLOBALS['_test_user_meta'][ $user_id ][ $key ] = $value;
		return true;
	}
}
if ( ! function_exists( 'get_user_meta' ) ) {
	function get_user_meta( $user_id, $key = '', $single = false ) {
		$value = $GLOBALS['_test_user_meta'][ $user_id ][ $key ] ?? '';
		return $single ? $value : ( '' === $value ? array() : array( $value ) );
	}
}
if ( ! function_exists( 'delete_user_meta' ) ) {
	function delete_user_meta( $user_id, $key ) {
		unset( $GLOBALS['_test_user_meta'][ $user_id ][ $key ] );
		return true;
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
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, $options = 0, $depth = 512 ) {
		return json_encode( $data, $options, $depth );
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

// In-memory REST capture. Unit tests can read $GLOBALS['_test_rest_log'] after
// invoking an ability callback to assert what method/route/params reached the
// vendor REST layer. Tests can also pre-set $GLOBALS['_test_rest_response'] to
// the response data the captured request should return.
$_test_rest_log      = array();
$_test_rest_response = array();

if ( ! class_exists( 'WP_REST_Request' ) ) {
	class WP_REST_Request {
		private $method;
		private $route;
		private $params = array();

		public function __construct( $method = 'GET', $route = '' ) {
			$this->method = $method;
			$this->route  = $route;
		}

		public function set_param( $key, $value ) {
			$this->params[ $key ] = $value;
		}

		public function get_method() {
			return $this->method;
		}

		public function get_route() {
			return $this->route;
		}

		public function get_params() {
			return $this->params;
		}
	}
}

if ( ! function_exists( 'rest_do_request' ) ) {
	function rest_do_request( $request ) {
		global $_test_rest_log, $_test_rest_response;
		$entry = array(
			'method' => $request->get_method(),
			'route'  => $request->get_route(),
			'params' => $request->get_params(),
		);
		$_test_rest_log[] = $entry;
		$data = $_test_rest_response ?? array();
		return new WP_REST_Response( $data, 200, false );
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

$_wp_test_action_callbacks = array();

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
		global $_wp_test_action_callbacks;
		if ( ! isset( $_wp_test_action_callbacks[ $hook ] ) ) {
			$_wp_test_action_callbacks[ $hook ] = array();
		}
		$_wp_test_action_callbacks[ $hook ][] = $callback;
	}
}
if ( ! function_exists( 'do_action' ) ) {
	function do_action( $hook, ...$args ) {
		global $_wp_test_action_callbacks;
		foreach ( $_wp_test_action_callbacks[ $hook ] ?? array() as $cb ) {
			if ( is_callable( $cb ) ) {
				call_user_func_array( $cb, $args );
			}
		}
	}
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
