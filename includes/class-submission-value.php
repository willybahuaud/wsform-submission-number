<?php
/**
 * Field value of a submission.
 *
 * @package WSForm_Submission_Number
 */

namespace WSForm_Submission_Number;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Writes a number field value both in memory and in the database.
 *
 * WS Form's wsf_submit_set_value() only writes the database. The actions that run
 * next (emails, hooks) read $submit->meta, and the second pass of the
 * "Save Submission" action would write the browser value back from it.
 */
class Submission_Value {

	/**
	 * Set the value of a number field.
	 *
	 * @param \WS_Form_Submit $submit Submission.
	 * @param object          $field  Field.
	 * @param string          $value  Formatted number, or an empty string.
	 *
	 * @return void
	 */
	public static function write( $submit, $field, $value ) {
		$meta_key = self::meta_key( $field->id );

		if ( ! isset( $submit->meta ) || ! is_array( $submit->meta ) ) {
			$submit->meta = array();
		}

		$current = isset( $submit->meta[ $meta_key ]['value'] ) ? (string) $submit->meta[ $meta_key ]['value'] : null;

		$submit->meta[ $meta_key ] = array(
			'id'               => (int) $field->id,
			'value'            => $value,
			'type'             => Field_Type::TYPE,
			'section_id'       => isset( $field->section_id ) ? (int) $field->section_id : 0,
			'repeatable_index' => false,
		);

		if ( $current !== $value && ! empty( $submit->id ) && function_exists( 'wsf_submit_set_value' ) ) {
			wsf_submit_set_value( $submit, $meta_key, $value );
		}
	}

	/**
	 * Submission meta key of a field.
	 *
	 * @param int $field_id Field ID.
	 *
	 * @return string
	 */
	public static function meta_key( $field_id ) {
		$prefix = defined( 'WS_FORM_FIELD_PREFIX' ) ? WS_FORM_FIELD_PREFIX : 'field_';

		return $prefix . (int) $field_id;
	}
}
