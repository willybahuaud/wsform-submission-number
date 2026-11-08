<?php
/**
 * Own sequences of published forms.
 *
 * @package WSForm_Submission_Number
 */

namespace WSForm_Submission_Number;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates a form's own sequence as soon as the form is published.
 *
 * The sequence would otherwise appear with the first submission. Creating it
 * on publish lets the site owner set its format and start number before.
 */
class Form_Sync {

	/**
	 * Hook into WS Form.
	 *
	 * @return void
	 */
	public function boot() {
		add_action( 'wsf_form_publish', array( $this, 'create_own_sequences' ) );
	}

	/**
	 * Create the missing own sequences of a published form.
	 *
	 * @param object $form_object Published form.
	 *
	 * @return void
	 */
	public function create_own_sequences( $form_object ) {
		$form_label = isset( $form_object->label ) ? $form_object->label : '';

		foreach ( Field_Type::find_in_form( $form_object ) as $field_id => $field ) {
			Sequences::resolve_for_field( Field_Type::chosen_sequence( $field ), $field_id, $form_label );
		}
	}
}
