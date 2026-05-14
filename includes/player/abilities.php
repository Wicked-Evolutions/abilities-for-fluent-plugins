<?php
/**
 * FluentPlayer Abilities — module loader.
 *
 * Greenfield module added for v2.0.0 (Fluent Suite Registrar Bundle Sprint).
 *
 * Registers 103 abilities in the `fluent-player` category — 22 free-tier surfaces
 * (Media, Settings, YouTube, Email Collections, free Presets) plus 81 Pro-tier
 * surfaces (Playlists, Subtitles, Analytics, Bunny CDN Stream + Storage, Mux,
 * Media Tags, License). Pro sub-files are guarded with class_exists() on a Pro
 * sentinel class so they cleanly no-op when FluentPlayer Pro is not installed.
 *
 * Pending scaffold-owned edits (described in PR body, integrated by orchestrator at merge):
 *   - `includes/ability-categories.php` — add `fluent-player` category entry
 *   - `abilities-for-fluent-plugins.php` — add `'player' => 'FLUENT_PLAYER_VERSION'` to $modules
 *   - `includes/security.php` — add `'player' => array(read,write,delete)` to fluent_abilities_get_caps()
 *
 * Until those land, this file defensively self-registers the category and caps
 * so the module is functional on probe sites without central wiring. The self-
 * registration is idempotent: it no-ops if the canonical scaffold edits are
 * present.
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

// ─── Defensive category registration ───────────────────────────────────────
// Idempotent: if includes/ability-categories.php already registers fluent-player
// at the canonical hook, this second registration call is overwritten by the
// later/identical args.
add_action(
	'wp_abilities_api_categories_init',
	function () {
		if ( function_exists( 'wp_register_ability_category' ) ) {
			wp_register_ability_category(
				'fluent-player',
				array(
					'label'       => 'FluentPlayer',
					'description' => 'Media items, playlists, presets, settings, analytics, subtitles, email captures, and Bunny CDN / Mux integration for FluentPlayer + FluentPlayer Pro.',
				)
			);
		}
	},
	5
);

// ─── Defensive cap registration ────────────────────────────────────────────
// The shared fluent_abilities_get_caps() in includes/security.php does not yet
// include the 'player' module. Until the scaffold-owned edit lands, grant the
// player caps to the administrator role on init. Idempotent: WP_Role::add_cap()
// is a no-op if the cap is already present.
add_action(
	'init',
	function () {
		if ( ! function_exists( 'get_role' ) ) {
			return;
		}
		$admin_role = get_role( 'administrator' );
		if ( ! $admin_role ) {
			return;
		}
		foreach ( array( 'fluent_player_read', 'fluent_player_write', 'fluent_player_delete' ) as $cap ) {
			$admin_role->add_cap( $cap );
		}
	},
	5
);

// ─── Shared controller-invocation helpers ──────────────────────────────────
// FluentPlayer (free + Pro) controller methods follow a Laravel-style HTTP
// dispatch contract: they take `\FluentPlayer\Framework\Http\Request\Request`
// as their first arg (constructor signature `(Application $app, $get, $post)`),
// with downstream services auto-injected via the framework's IoC container.
// Passing $input as an array directly to controller methods throws TypeError.
//
// `fluent_abilities_player_make_request()` builds the Request object.
// `fluent_abilities_player_invoke_controller()` is the canonical entry point —
// constructs the Request, binds it to the container so DI sees it, instantiates
// the controller, and dispatches the method via `$app->call()` (which auto-
// resolves Request + service-typed parameters and accepts scalars by name).
//
// Both return a WP_Error('missing_class', …) when FluentPlayer / Application
// isn't loaded (unit-test env), so callbacks short-circuit gracefully.

if ( ! function_exists( 'fluent_abilities_player_make_request' ) ) {
	function fluent_abilities_player_make_request( array $input = array() ) {
		if ( ! class_exists( '\FluentPlayer\App\App' ) || ! class_exists( '\FluentPlayer\Framework\Http\Request\Request' ) ) {
			return null;
		}
		$app = \FluentPlayer\App\App::getInstance();
		if ( ! $app ) {
			return null;
		}
		return new \FluentPlayer\Framework\Http\Request\Request( $app, $input, $input );
	}
}

// ─── Output redaction helper (Reviewer pre-flight #2) ─────────────────────
// Replaces secret + PII field values in callback output with "[REDACTED]"
// before the response leaves the ability boundary. Walks arrays recursively;
// replaces any non-empty value where the key matches the secret/PII list.
//
// Empty strings + null + false are preserved as-is — operators on
// unconfigured/unregistered surfaces see the natural empty state, while
// configured surfaces never expose real secrets / PII.
//
// Key list is intentionally conservative: operationally-required identifiers
// (user_id, list_id, tag_id, form_id) and non-sensitive metadata (timestamps,
// status enums, video_time, integer counts) are NOT in scope and pass through.
if ( ! function_exists( 'fluent_abilities_player_redact' ) ) {
	function fluent_abilities_player_redact( $data ) {
		static $secret_keys = array(
			// Secrets
			'license_key', 'api_key', 'api_token', 'private_key', 'signing_key',
			'secret', 'token', 'password', 'bearer', 'auth_token', 'connectUrl',
			'webhook_secret', 'mux_webhook_secret', 'mux_token_id', 'mux_token_secret',
			'access_token', 'refresh_token', 'client_secret', 'consumer_secret',
			// Email PII
			'email', 'customer_email', 'user_email',
			// Name PII
			'display_name', 'customer_name', 'full_name',
			// Other PII
			'phone', 'ip_address',
			// Financial cross-ref
			'payment_id',
		);
		if ( ! is_array( $data ) ) {
			return $data;
		}
		$out = array();
		foreach ( $data as $k => $v ) {
			if ( is_string( $k ) && in_array( $k, $secret_keys, true ) ) {
				if ( '' === $v || null === $v || false === $v || array() === $v ) {
					$out[ $k ] = $v;
				} else {
					$out[ $k ] = '[REDACTED]';
				}
			} elseif ( is_array( $v ) ) {
				$out[ $k ] = fluent_abilities_player_redact( $v );
			} else {
				$out[ $k ] = $v;
			}
		}
		return $out;
	}
}

// ─── Permissive collection schema for third-party-API-passthrough abilities ─
// FluentPlayer/Pro controllers wrapping Bunny/Mux/etc. return wildly variant
// shapes depending on connection state — array of objects when connected, array
// of error-message strings when not. The shared `fluent_abilities_schema_collection_output()`
// helper enforces `items: {type: object}` which fails validation on the
// not-connected scalar path. This helper relaxes items.type, accepting any
// shape under the items_key.
if ( ! function_exists( 'fluent_abilities_player_loose_collection_schema' ) ) {
	function fluent_abilities_player_loose_collection_schema( $items_key = 'data' ) {
		return array(
			'type'       => 'object',
			'properties' => array(
				'total'    => array( 'type' => 'integer' ),
				$items_key => array( 'type' => 'array' ),
			),
		);
	}
}

if ( ! function_exists( 'fluent_abilities_player_invoke_controller' ) ) {
	function fluent_abilities_player_invoke_controller( $controller_class, $method, array $input = array(), array $extra_params = array() ) {
		if ( ! class_exists( $controller_class ) ) {
			return fluent_abilities_error( 'missing_class', $controller_class . ' not found.' );
		}
		if ( ! class_exists( '\FluentPlayer\App\App' ) || ! class_exists( '\FluentPlayer\Framework\Http\Request\Request' ) ) {
			return fluent_abilities_error( 'missing_class', 'FluentPlayer Request framework not available.' );
		}
		$app = \FluentPlayer\App\App::getInstance();
		if ( ! $app ) {
			return fluent_abilities_error( 'missing_class', 'FluentPlayer Application not initialized.' );
		}
		try {
			$request = new \FluentPlayer\Framework\Http\Request\Request( $app, $input, $input );
			$app->instance( 'request', $request );
			$app->instance( \FluentPlayer\Framework\Http\Request\Request::class, $request );
			$controller = new $controller_class();
			$result     = $app->call( array( $controller, $method ), $extra_params );
			// Normalize WP_REST_Response → underlying data array.
			if ( $result instanceof \WP_REST_Response ) {
				$data = $result->get_data();
				return is_array( $data ) ? $data : array( 'data' => $data );
			}
			if ( is_array( $result ) ) {
				return fluent_abilities_safe_array( $result );
			}
			if ( is_object( $result ) && method_exists( $result, 'toArray' ) ) {
				return fluent_abilities_safe_array( $result->toArray() );
			}
			return $result;
		} catch ( \Throwable $e ) {
			return fluent_abilities_error( 'execution_failed', $e->getMessage() );
		}
	}
}

// ─── Sub-file loader ───────────────────────────────────────────────────────
// Each sub-file defines a named registration function and hooks it onto
// wp_abilities_api_init. Named functions (rather than inline closures) keep
// the registration logic directly callable from unit tests where add_action
// is stubbed as a no-op.
require_once __DIR__ . '/abilities-media.php';
require_once __DIR__ . '/abilities-presets.php';
require_once __DIR__ . '/abilities-email.php';
require_once __DIR__ . '/abilities-playlists.php';
require_once __DIR__ . '/abilities-analytics.php';
require_once __DIR__ . '/abilities-bunny.php';
require_once __DIR__ . '/abilities-mux.php';
require_once __DIR__ . '/abilities-license.php';
