<?php
/**
 * The #submission_number variable.
 *
 * @package WSForm_Submission_Number
 */

namespace WSForm_Submission_Number;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds #submission_number, a shortcut to the number without looking up the field ID.
 *
 * Named outside WS Form's own "#submit_" family to avoid any clash.
 */
class Variable {

	const NAME = 'submission_number';

	/**
	 * Hook into WS Form variables.
	 *
	 * @return void
	 */
	public function boot() {
		add_filter( 'wsf_config_parse_variables', array( $this, 'register' ) );
	}

	/**
	 * Declare the variable, so it shows in the variable picker too.
	 *
	 * @param array $parse_variables Variable groups.
	 *
	 * @return array
	 */
	public function register( $parse_variables ) {
		$parse_variables['wsform_submission_number'] = array(
			'label'     => __( 'Submission Numbers', 'wsform-submission-number' ),
			'variables' => array(
				self::NAME => array(
					'label'       => __( 'Submission Number', 'wsform-submission-number' ),
					'function'    => array( $this, 'get_value' ),
					'description' => __( 'Returns the number of the first Submission Number field of the form, once the submission is saved.', 'wsform-submission-number' ),
				),
			),
		);

		return $parse_variables;
	}

	/**
	 * Value of the variable.
	 *
	 * Reads the submission in memory first, which holds the number while the
	 * actions of the same request run, then the recorded numbers.
	 *
	 * @param object      $form    Form.
	 * @param object|bool $submit  Submission, false before submitting.
	 *
	 * @return string
	 */
	public function get_value( $form, $submit ) {
		if ( ! is_object( $submit ) || empty( $submit->id ) ) {
			return '';
		}

		$fields = Field_Type::find_in_form( $form );
		$meta   = isset( $submit->meta ) ? (array) $submit->meta : array();

		foreach ( array_keys( $fields ) as $field_id ) {
			$meta_key = Submission_Value::meta_key( $field_id );
			$value    = isset( $meta[ $meta_key ] ) ? (array) $meta[ $meta_key ] : array();

			if ( ! empty( $value['value'] ) ) {
				return (string) $value['value'];
			}
		}

		$assignments = Assignments::for_submission( $submit->id );
		$first       = reset( $assignments );

		return $first ? $first['formatted'] : '';
	}
}
