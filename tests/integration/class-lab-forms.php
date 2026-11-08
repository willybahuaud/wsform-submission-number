<?php
/**
 * Test forms built in the lab site.
 *
 * @package WSForm_Submission_Number\Tests
 */

use WSForm_Submission_Number\Field_Type;

/**
 * Builds published WS Form forms with a number field, from the "Contact Us" template.
 *
 * The template brings fields, a "Save Submission" action, an email action
 * and a message, which is what the scenarios need.
 */
class WSForm_Submission_Number_Lab_Forms {

	/**
	 * Create and publish a form with a number field.
	 *
	 * @param string $label       Form name.
	 * @param string $sequence_id Shared sequence ID, or "" for the form's own sequence.
	 *
	 * @return array Keys form_id, number_field_id, fields (field IDs by type).
	 */
	public static function create( $label, $sequence_id = '' ) {
		$form    = new WS_Form_Form();
		$form_id = $form->db_create_from_template( 'contact-us' );

		global $wpdb;
		$wpdb->update( $wpdb->prefix . 'wsf_form', array( 'label' => $label ), array( 'id' => $form_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Test fixture.
		$form->id = $form_id;

		$object  = $form->db_read( true, true );
		$section = $object->groups[0]->sections[0];

		$field             = new WS_Form_Field();
		$field->form_id    = $form_id;
		$field->section_id = $section->id;
		$field->type       = Field_Type::TYPE;
		$field->db_create();

		$meta            = new WS_Form_Meta();
		$meta->object    = 'field';
		$meta->parent_id = $field->id;
		$meta->db_update_from_array( array( Field_Type::SEQUENCE_META_KEY => $sequence_id ) );

		$form->db_publish();

		return array(
			'form_id'         => (int) $form_id,
			'number_field_id' => (int) $field->id,
			'fields'          => self::field_ids_by_type( $form_id ),
		);
	}

	/**
	 * Add an action with empty settings to a form, then publish it.
	 *
	 * Built from the "Save Submission" row, so the row keeps the shape WS Form expects.
	 *
	 * @param int    $form_id   Form ID.
	 * @param string $action_id Action type.
	 *
	 * @return void
	 */
	public static function add_action( $form_id, $action_id ) {
		$form     = new WS_Form_Form();
		$form->id = $form_id;
		$object   = $form->db_read( true, true );
		$actions  = $object->meta->action;
		$row      = json_decode( wp_json_encode( $actions->groups[0]->rows[0] ) );
		$config   = is_string( $row->data[1] ) ? json_decode( $row->data[1], true ) : json_decode( wp_json_encode( $row->data[1] ), true );

		$config['id']   = $action_id;
		$config['meta'] = new stdClass();
		$row->id        = 999;
		$row->data[0]   = $action_id;
		$row->data[1]   = is_string( $row->data[1] ) ? wp_json_encode( $config ) : $config;

		$actions->groups[0]->rows[] = $row;

		self::save_actions( $form, $actions );
	}

	/**
	 * Delete a form for good.
	 *
	 * @param int $form_id Form ID.
	 *
	 * @return void
	 */
	public static function delete( $form_id ) {
		$form     = new WS_Form_Form();
		$form->id = $form_id;
		$form->db_delete( true );
	}

	/**
	 * Field IDs of a form, by field type.
	 *
	 * @param int $form_id Form ID.
	 *
	 * @return array<string,int[]>
	 */
	public static function field_ids_by_type( $form_id ) {
		$form     = new WS_Form_Form();
		$form->id = $form_id;
		$object   = $form->db_read( true, true );
		$by_type  = array();

		foreach ( WS_Form_Common::get_fields_from_form_group( $object->groups, false, false ) as $field_id => $field ) {
			$by_type[ $field->type ][] = (int) $field_id;
		}

		return $by_type;
	}

	/**
	 * Store a form's actions, then publish it.
	 *
	 * @param WS_Form_Form $form    Form, with its ID.
	 * @param object       $actions Action data grid.
	 *
	 * @return void
	 */
	private static function save_actions( $form, $actions ) {
		$meta            = new WS_Form_Meta();
		$meta->object    = 'form';
		$meta->parent_id = $form->id;
		$meta->db_update_from_array( array( 'action' => $actions ) );

		$form->db_publish();
	}
}
