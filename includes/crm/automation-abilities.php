<?php
/**
 * Abilities for Fluent — CRM Automation CRUD Abilities
 *
 * Abilities for creating, reading, and managing FluentCRM automation funnels
 * and their steps (funnel sequences). Extends the base CRM abilities with
 * full automation lifecycle management.
 *
 * @since 1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_abilities_api_init', function () {

	if ( ! defined( 'FLUENTCRM_PLUGIN_VERSION' ) ) {
		return;
	}

	$reg = new Fluent_Abilities_Registrar( 'crm' );

	// =========================================================================
	// 1. GET AUTOMATION DETAIL
	// =========================================================================

	$reg->read( 'fluent-crm/get-automation-detail', array(
		'label'       => 'Get Automation Detail',
		'description' => 'Read complete automation structure: funnel metadata, trigger config, and all steps with their settings. Use this to inspect how an automation is built.',
		'category'    => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'funnel_id' ),
			'properties' => array(
				'funnel_id' => array( 'type' => 'integer', 'description' => 'Automation funnel ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'funnel'       => array( 'type' => 'object' ),
			'steps'        => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
			'total_steps'  => array( 'type' => 'integer' ),
			'raw_db_count' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$funnel = \FluentCrm\App\Models\Funnel::find( absint( $input['funnel_id'] ) );
			if ( ! $funnel ) {
				return fluent_abilities_error( 'not_found', 'Automation funnel not found' );
			}

			// Decode funnel settings and conditions.
			$settings   = $funnel->settings;
			$conditions = $funnel->conditions;
			if ( is_string( $settings ) ) {
				$settings = maybe_unserialize( $settings );
			}
			if ( is_string( $conditions ) ) {
				$conditions = maybe_unserialize( $conditions );
			}

			// Get steps via Eloquent model.
			$sequences = \FluentCrm\App\Models\FunnelSequence::where( 'funnel_id', $funnel->id )
				->orderBy( 'sequence', 'ASC' )
				->get();

			$steps = array();
			foreach ( $sequences as $seq ) {
				$step_settings   = $seq->settings;
				$step_conditions = $seq->conditions;
				if ( is_string( $step_settings ) ) {
					$step_settings = maybe_unserialize( $step_settings );
				}
				if ( is_string( $step_conditions ) ) {
					$step_conditions = maybe_unserialize( $step_conditions );
				}

				$steps[] = array(
					'id'             => (int) $seq->id,
					'funnel_id'      => (int) $seq->funnel_id,
					'parent_id'      => (int) $seq->parent_id,
					'action_name'    => $seq->action_name,
					'condition_type' => $seq->condition_type,
					'type'           => $seq->type,
					'title'          => $seq->title,
					'description'    => $seq->description,
					'status'         => $seq->status,
					'settings'       => fluent_abilities_safe_array( $step_settings ),
					'conditions'     => fluent_abilities_safe_array( $step_conditions ),
					'delay'          => (int) $seq->delay,
					'c_delay'        => (int) $seq->c_delay,
					'sequence'       => (int) $seq->sequence,
					'created_by'     => (int) $seq->created_by,
				);
			}

			// Raw DB fallback for diagnostics — check if table exists and has rows.
			global $wpdb;
			$table = $wpdb->prefix . 'fc_funnel_sequences';
			$raw_count = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE funnel_id = %d",
				$funnel->id
			) );

			return array(
				'funnel' => array(
					'id'           => (int) $funnel->id,
					'title'        => $funnel->title,
					'trigger_name' => $funnel->trigger_name,
					'status'       => $funnel->status,
					'type'         => $funnel->type,
					'conditions'   => fluent_abilities_safe_array( $conditions ),
					'settings'     => fluent_abilities_safe_array( $settings ),
					'created_by'   => (int) $funnel->created_by,
					'created_at'   => (string) $funnel->created_at,
					'updated_at'   => (string) $funnel->updated_at,
				),
				'steps'            => $steps,
				'total_steps'      => count( $steps ),
				'raw_db_count'     => $raw_count,
			);
		},
	) );

	// =========================================================================
	// 2. CREATE AUTOMATION
	// =========================================================================

	$reg->write( 'fluent-crm/create-automation', array(
		'label'       => 'Create Automation',
		'description' => 'Create a new automation funnel (shell only, no steps). Add steps separately with add-automation-step. Common trigger_name values: fluentcrm_contact_added_to_tags (tag applied), fluent_surecart_purchase_created_wrap (SureCart purchase), fluentform_submission_inserted (form submit).',
		'category'    => 'fluent-crm',
		'annotations' => array( 'idempotent' => false ),
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'title', 'trigger_name' ),
			'properties' => array(
				'title'        => array( 'type' => 'string', 'description' => 'Automation name' ),
				'trigger_name' => array( 'type' => 'string', 'description' => 'Trigger identifier, e.g. fluentcrm_contact_added_to_tags' ),
				'status'       => array( 'type' => 'string', 'description' => 'Status: draft (default) or published' ),
				'conditions'   => array( 'type' => 'object', 'description' => 'Trigger conditions JSON, e.g. {"tags": [56], "select_type": "any"}' ),
				'settings'     => array( 'type' => 'object', 'description' => 'Automation settings JSON' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'funnel_id'    => array( 'type' => 'integer' ),
			'title'        => array( 'type' => 'string' ),
			'status'       => array( 'type' => 'string' ),
			'trigger_name' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$data = array(
				'title'        => sanitize_text_field( $input['title'] ),
				'trigger_name' => sanitize_text_field( $input['trigger_name'] ),
				'status'       => isset( $input['status'] ) && in_array( $input['status'], array( 'draft', 'published' ), true ) ? $input['status'] : 'draft',
				'created_by'   => get_current_user_id(),
			);

			if ( isset( $input['conditions'] ) ) {
				$data['conditions'] = $input['conditions'];
			}
			if ( isset( $input['settings'] ) ) {
				$data['settings'] = $input['settings'];
			}

			$funnel = \FluentCrm\App\Models\Funnel::create( $data );

			return array(
				'success'      => true,
				'funnel_id'    => (int) $funnel->id,
				'title'        => $funnel->title,
				'status'       => $funnel->status,
				'trigger_name' => $funnel->trigger_name,
			);
		},
	) );

	// =========================================================================
	// 3. ADD AUTOMATION STEP
	// =========================================================================

	$reg->write( 'fluent-crm/add-automation-step', array(
		'label'       => 'Add Automation Step',
		'description' => 'Add a step to an automation. Common action_name values: fluentcrm_wait_times (wait/delay), send_custom_email (send email), add_contact_to_tag (apply tag), detach_contact_from_tag (remove tag), fcrm_has_contact_tag (condition: has tag?), end_this_funnel (end). For wait: settings={wait_type:"unit_wait", wait_time_amount:N, wait_time_unit:"days"|"hours"|"minutes"}. For tag actions: settings={tags:[id1,id2]}. For conditions: set type="conditional".',
		'category'    => 'fluent-crm',
		'annotations' => array( 'idempotent' => false ),
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'funnel_id', 'action_name' ),
			'properties' => array(
				'funnel_id'      => array( 'type' => 'integer', 'description' => 'Automation funnel ID to add step to' ),
				'action_name'    => array( 'type' => 'string', 'description' => 'Step type: fluentcrm_wait_times, send_custom_email, add_contact_to_tag, fcrm_has_contact_tag, end_this_funnel, etc.' ),
				'title'          => array( 'type' => 'string', 'description' => 'Step label (defaults to action_name)' ),
				'type'           => array( 'type' => 'string', 'description' => 'Step type: sequence (default) or conditional' ),
				'parent_id'      => array( 'type' => 'integer', 'description' => 'Parent step ID for child steps under conditions (0 = top-level)' ),
				'condition_type' => array( 'type' => 'string', 'description' => 'Condition type identifier (for conditional steps)' ),
				'settings'       => array( 'type' => 'object', 'description' => 'Action-specific settings JSON' ),
				'conditions'     => array( 'type' => 'object', 'description' => 'Condition configuration JSON' ),
				'delay'          => array( 'type' => 'integer', 'description' => 'Delay in seconds before this step executes' ),
				'c_delay'        => array( 'type' => 'integer', 'description' => 'Conditional delay in seconds' ),
				'position'       => array( 'type' => 'integer', 'description' => 'Step order position (auto-increments if omitted)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'step_id'           => array( 'type' => 'integer' ),
			'funnel_id'         => array( 'type' => 'integer' ),
			'action_name'       => array( 'type' => 'string' ),
			'title'             => array( 'type' => 'string' ),
			'sequence_position' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$funnel = \FluentCrm\App\Models\Funnel::find( absint( $input['funnel_id'] ) );
			if ( ! $funnel ) {
				return fluent_abilities_error( 'not_found', 'Automation funnel not found' );
			}

			// Determine position.
			$max_seq  = \FluentCrm\App\Models\FunnelSequence::where( 'funnel_id', $funnel->id )->max( 'sequence' );
			$position = isset( $input['position'] ) ? absint( $input['position'] ) : ( (int) $max_seq + 1 );

			$step_data = array(
				'funnel_id'      => $funnel->id,
				'action_name'    => sanitize_text_field( $input['action_name'] ),
				'title'          => sanitize_text_field( $input['title'] ?? $input['action_name'] ),
				'type'           => sanitize_text_field( $input['type'] ?? 'sequence' ),
				'parent_id'      => absint( $input['parent_id'] ?? 0 ),
				'condition_type' => isset( $input['condition_type'] ) ? sanitize_text_field( $input['condition_type'] ) : null,
				'sequence'       => $position,
				'status'         => 'published',
				'delay'          => absint( $input['delay'] ?? 0 ),
				'c_delay'        => absint( $input['c_delay'] ?? 0 ),
				'created_by'     => get_current_user_id(),
			);

			// Settings and conditions — store as-is (model handles serialization).
			if ( isset( $input['settings'] ) ) {
				$step_data['settings'] = $input['settings'];
			}
			if ( isset( $input['conditions'] ) ) {
				$step_data['conditions'] = $input['conditions'];
			}

			$step = \FluentCrm\App\Models\FunnelSequence::create( $step_data );

			// Reset sequence indexes.
			if ( class_exists( '\FluentCrm\App\Services\Funnel\FunnelHandler' ) ) {
				( new \FluentCrm\App\Services\Funnel\FunnelHandler() )->resetFunnelIndexes();
			}

			return array(
				'success'           => true,
				'step_id'           => (int) $step->id,
				'funnel_id'         => (int) $funnel->id,
				'action_name'       => $step->action_name,
				'title'             => $step->title,
				'sequence_position' => (int) $step->sequence,
			);
		},
	) );

	// =========================================================================
	// 4. UPDATE AUTOMATION
	// =========================================================================

	$reg->write( 'fluent-crm/update-automation', array(
		'label'       => 'Update Automation',
		'description' => 'Update automation funnel properties: title, trigger, conditions, or settings. Only provided fields are updated.',
		'category'    => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'funnel_id' ),
			'properties' => array(
				'funnel_id'    => array( 'type' => 'integer', 'description' => 'Automation funnel ID to update' ),
				'title'        => array( 'type' => 'string', 'description' => 'New title' ),
				'trigger_name' => array( 'type' => 'string', 'description' => 'New trigger identifier' ),
				'conditions'   => array( 'type' => 'object', 'description' => 'New trigger conditions JSON' ),
				'settings'     => array( 'type' => 'object', 'description' => 'New automation settings JSON' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'funnel_id'    => array( 'type' => 'integer' ),
			'title'        => array( 'type' => 'string' ),
			'status'       => array( 'type' => 'string' ),
			'trigger_name' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$funnel = \FluentCrm\App\Models\Funnel::find( absint( $input['funnel_id'] ) );
			if ( ! $funnel ) {
				return fluent_abilities_error( 'not_found', 'Automation funnel not found' );
			}

			if ( isset( $input['title'] ) ) {
				$funnel->title = sanitize_text_field( $input['title'] );
			}
			if ( isset( $input['trigger_name'] ) ) {
				$funnel->trigger_name = sanitize_text_field( $input['trigger_name'] );
			}
			if ( isset( $input['conditions'] ) ) {
				$funnel->conditions = $input['conditions'];
			}
			if ( isset( $input['settings'] ) ) {
				$funnel->settings = $input['settings'];
			}

			$funnel->save();

			return array(
				'success'      => true,
				'funnel_id'    => (int) $funnel->id,
				'title'        => $funnel->title,
				'status'       => $funnel->status,
				'trigger_name' => $funnel->trigger_name,
			);
		},
	) );

	// =========================================================================
	// 5. LIST AUTOMATION STEPS
	// =========================================================================

	$reg->read( 'fluent-crm/list-automation-steps', array(
		'label'       => 'List Automation Steps',
		'description' => 'List all steps of an automation in execution order, with decoded settings and conditions.',
		'category'    => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'funnel_id' ),
			'properties' => array(
				'funnel_id' => array( 'type' => 'integer', 'description' => 'Automation funnel ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'funnel_id'    => array( 'type' => 'integer' ),
			'funnel_title' => array( 'type' => 'string' ),
			'steps'        => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
			'total_steps'  => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$funnel = \FluentCrm\App\Models\Funnel::find( absint( $input['funnel_id'] ) );
			if ( ! $funnel ) {
				return fluent_abilities_error( 'not_found', 'Automation funnel not found' );
			}

			$sequences = \FluentCrm\App\Models\FunnelSequence::where( 'funnel_id', $funnel->id )
				->orderBy( 'sequence', 'ASC' )
				->get();

			$steps = array();
			foreach ( $sequences as $seq ) {
				$step_settings   = $seq->settings;
				$step_conditions = $seq->conditions;
				if ( is_string( $step_settings ) ) {
					$step_settings = maybe_unserialize( $step_settings );
				}
				if ( is_string( $step_conditions ) ) {
					$step_conditions = maybe_unserialize( $step_conditions );
				}

				$steps[] = array(
					'id'             => (int) $seq->id,
					'action_name'    => $seq->action_name,
					'title'          => $seq->title,
					'type'           => $seq->type,
					'parent_id'      => (int) $seq->parent_id,
					'condition_type' => $seq->condition_type,
					'sequence'       => (int) $seq->sequence,
					'status'         => $seq->status,
					'settings'       => fluent_abilities_safe_array( $step_settings ),
					'conditions'     => fluent_abilities_safe_array( $step_conditions ),
					'delay'          => (int) $seq->delay,
					'c_delay'        => (int) $seq->c_delay,
				);
			}

			return array(
				'funnel_id'   => (int) $funnel->id,
				'funnel_title' => $funnel->title,
				'steps'       => $steps,
				'total_steps' => count( $steps ),
			);
		},
	) );

	// =========================================================================
	// 6. REMOVE AUTOMATION STEP
	// =========================================================================

	$reg->write( 'fluent-crm/remove-automation-step', array(
		'label'       => 'Remove Automation Step',
		'description' => 'Remove a step from an automation. The step must belong to the specified funnel.',
		'category'    => 'fluent-crm',
		'annotations' => array( 'idempotent' => false ),
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'funnel_id', 'step_id' ),
			'properties' => array(
				'funnel_id' => array( 'type' => 'integer', 'description' => 'Automation funnel ID' ),
				'step_id'   => array( 'type' => 'integer', 'description' => 'Step ID to remove' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'removed_step_id' => array( 'type' => 'integer' ),
			'funnel_id'       => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$funnel = \FluentCrm\App\Models\Funnel::find( absint( $input['funnel_id'] ) );
			if ( ! $funnel ) {
				return fluent_abilities_error( 'not_found', 'Automation funnel not found' );
			}

			$step = \FluentCrm\App\Models\FunnelSequence::where( 'id', absint( $input['step_id'] ) )
				->where( 'funnel_id', $funnel->id )
				->first();

			if ( ! $step ) {
				return fluent_abilities_error( 'not_found', 'Step not found in this automation' );
			}

			$step_id = (int) $step->id;
			$step->delete();

			// Reset sequence indexes.
			if ( class_exists( '\FluentCrm\App\Services\Funnel\FunnelHandler' ) ) {
				( new \FluentCrm\App\Services\Funnel\FunnelHandler() )->resetFunnelIndexes();
			}

			return array(
				'success'        => true,
				'removed_step_id' => $step_id,
				'funnel_id'      => (int) $funnel->id,
			);
		},
	) );

	// ===== AUTOMATION — DELETE =====

	$reg->delete( 'fluent-crm/delete-automation', array(
		'label'       => 'Delete Automation',
		'description' => 'Delete a FluentCRM automation funnel. Active/running automations require force=true.',
		'category'    => 'fluent-crm',
		'capability'  => 'manage_options',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'funnel_id' ),
			'properties' => array(
				'funnel_id' => array( 'type' => 'integer', 'description' => 'Automation funnel ID' ),
				'force'     => array( 'type' => 'boolean', 'default' => false, 'description' => 'Force delete even if automation is active/running' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'message'   => array( 'type' => 'string' ),
			'funnel_id' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$funnel = \FluentCrm\App\Models\Funnel::find( absint( $input['funnel_id'] ) );
			if ( ! $funnel ) {
				return fluent_abilities_error( 'not_found', 'Automation funnel not found' );
			}

			$force = ! empty( $input['force'] );
			if ( ! $force && in_array( $funnel->status, array( 'published', 'active' ), true ) ) {
				return fluent_abilities_error( 'ability_invalid_input', 'Cannot delete an active automation without force=true. Set force=true to confirm.' );
			}

			$funnel_id = (int) $funnel->id;

			// Delete all steps first.
			\FluentCrm\App\Models\FunnelSequence::where( 'funnel_id', $funnel_id )->delete();
			$funnel->delete();

			return array(
				'success'   => true,
				'message'   => 'Automation funnel deleted',
				'funnel_id' => $funnel_id,
			);
		},
	) );

}, 100 );
