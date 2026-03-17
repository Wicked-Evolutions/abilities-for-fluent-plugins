<?php
/**
 * Fluent Abilities Registrar — eliminates boilerplate from ability module files.
 *
 * Usage in a module file:
 *   $reg = new Fluent_Abilities_Registrar( 'crm' );
 *   $reg->read( 'fluent-crm/list-contacts', array(
 *       'label'         => 'List CRM Contacts',
 *       'description'   => '...',
 *       'input_schema'  => array( ... ),
 *       'output_schema' => array( ... ),
 *       'callback'      => function( $params ) { ... },
 *   ) );
 *
 * Write abilities default to the module's 'write' level.
 * Delete abilities default to the module's 'delete' level.
 * Override per-ability with: 'level' => 'admin'
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

class Fluent_Abilities_Registrar {

	private $module;

	/**
	 * @param string $module Module slug (e.g., 'crm', 'community', 'forms').
	 */
	public function __construct( $module ) {
		$this->module = $module;
	}

	/**
	 * Register a read-only ability (readonly=true, destructive=false, idempotent=true).
	 */
	public function read( $name, $config ) {
		$this->register( $name, $config, 'read' );
	}

	/**
	 * Register a write ability (readonly=false, destructive=false, idempotent=true).
	 */
	public function write( $name, $config ) {
		$this->register( $name, $config, 'write' );
	}

	/**
	 * Register a delete ability (readonly=false, destructive=true, idempotent=true).
	 */
	public function delete( $name, $config ) {
		$this->register( $name, $config, 'delete' );
	}

	/**
	 * Internal: build the full wp_register_ability() args from compact config.
	 *
	 * @param string $name     Ability name (e.g., 'fluent-crm/list-contacts').
	 * @param array  $config   Compact config array.
	 * @param string $op_type  Operation type: 'read', 'write', or 'delete'.
	 */
	private function register( $name, $config, $op_type ) {
		$module   = $this->module;
		$level    = $config['level'] ?? $op_type;
		$callback = fluent_abilities_pro_gate( $name, $config['callback'] );

		// Determine annotations from operation type.
		$annotations = array(
			'readonly'    => $op_type === 'read',
			'destructive' => $op_type === 'delete',
			'idempotent'  => true,
			'permission'  => $op_type, // 'read', 'write', or 'delete'
		);

		// Allow per-ability annotation overrides (e.g., idempotent=false for creates).
		if ( isset( $config['annotations'] ) ) {
			$annotations = array_merge( $annotations, $config['annotations'] );
		}

		// Build permission_callback.
		// Use 'capability' for raw WP caps (e.g., 'manage_options').
		// Use 'level' for module-level fluent_abilities_user_can() checks (default).
		if ( isset( $config['capability'] ) ) {
			$capability = $config['capability'];
			$permission_callback = function() use ( $capability ) {
				return current_user_can( $capability );
			};
		} else {
			$permission_callback = function() use ( $module, $level ) {
				return fluent_abilities_user_can( $module, $level );
			};
		}

		// WordPress Abilities API passes $input as stdClass. Our callbacks expect arrays.
		// Wrap every callback to cast $input, and handle zero-arg abilities gracefully.
		$has_input_schema = ! empty( $config['input_schema'] );
		$callback = static function( $input = null ) use ( $callback, $has_input_schema ) {
			if ( null !== $input ) {
				$input = (array) $input;
			} elseif ( $has_input_schema ) {
				$input = array();
			}
			return $callback( $input );
		};

		$args = array(
			'label'               => $config['label'],
			'description'         => $config['description'],
			'category'            => $config['category'] ?? ( strpos( $module, 'fluent' ) === 0 ? $module : 'fluent-' . $module ),
			'input_schema'        => $config['input_schema'] ?? array(),
			'execute_callback'    => $callback,
			'permission_callback' => $permission_callback,
			'meta' => array(
				'show_in_rest' => true,
				'tier'         => 'pro',
				'mcp'          => array( 'public' => true, 'type' => 'tool' ),
				'annotations'  => $annotations,
			),
		);

		// Only include output_schema if provided.
		if ( isset( $config['output_schema'] ) ) {
			$args['output_schema'] = $config['output_schema'];
		}

		wp_register_ability( $name, $args );
	}
}
