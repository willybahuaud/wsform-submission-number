<?php
/**
 * The "Submission number" field type.
 *
 * @package WSForm_Submission_Number
 */

namespace WSForm_Submission_Number;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Declares the field type and its settings to WS Form.
 *
 * A field rather than a plain variable: WS Form then shows the number in the
 * submission list, the CSV export, the search, #field(ID), emails and post
 * mapping without any extra code.
 */
class Field_Type {

	const TYPE = 'wsform_submission_number';

	/**
	 * Field setting holding the chosen sequence ID.
	 */
	const SEQUENCE_META_KEY = 'wsform_submission_number_sequence';

	/**
	 * Hook into WS Form configuration.
	 *
	 * @return void
	 */
	public function boot() {
		add_filter( 'wsf_config_field_types', array( $this, 'register_field_type' ) );
		add_filter( 'wsf_config_meta_keys', array( $this, 'register_meta_keys' ) );
	}

	/**
	 * Add the field type to the "Advanced" group of the field list.
	 *
	 * @param array $field_types Field types known to WS Form, by group.
	 *
	 * @return array
	 */
	public function register_field_type( $field_types ) {
		$group = isset( $field_types['advanced'] ) ? 'advanced' : 'wsform_submission_number';

		if ( ! isset( $field_types[ $group ] ) ) {
			$field_types[ $group ] = array(
				'label' => __( 'Submission Numbers', 'wsform-submission-number' ),
				'types' => array(),
			);
		}

		$field_types[ $group ]['types'][ self::TYPE ] = $this->get_config();

		return $field_types;
	}

	/**
	 * Field type definition.
	 *
	 * The input stays empty in the browser: the server writes the value,
	 * and ignores whatever the browser sends.
	 *
	 * @return array
	 */
	private function get_config() {
		return array(
			'label'                 => __( 'Submission Number', 'wsform-submission-number' ),
			'label_default'         => __( 'Number', 'wsform-submission-number' ),
			'description'           => __( 'A readable, continuous number given by the server to each saved submission.', 'wsform-submission-number' ),
			'icon'                  => 'hidden',
			'keyword'               => __( 'number sequence counter increment id reference numero', 'wsform-submission-number' ),
			'mask_field'            => '<input type="hidden" id="#id" name="#name" value=""#attributes />',
			'mask_field_attributes' => array( 'custom_attributes' ),
			'submit_save'           => true,
			// WS Form protects the value from admin edits in its own script only, see Edit_Guard.
			'submit_edit'           => false,
			'calc_in'               => false,
			'calc_out'              => false,
			'text_in'               => false,
			'text_out'              => true,
			'value_out'             => true,
			'mappable'              => true,
			'has_required'          => false,
			'progress'              => false,
			'template_svg_exclude'  => true,
			'event_validate_bypass' => true,
			'mask_wrappers_drop'    => true,
			'fieldsets'             => array(
				'basic'    => array(
					'label'     => __( 'Basic', 'wsform-submission-number' ),
					'meta_keys' => array( 'label', 'label_render', self::SEQUENCE_META_KEY ),
					'fieldsets' => array(
						array(
							'label'     => __( 'Exclusions', 'wsform-submission-number' ),
							'meta_keys' => array( 'exclude_email' ),
						),
					),
				),
				'advanced' => array(
					'label'     => __( 'Advanced', 'wsform-submission-number' ),
					'fieldsets' => array(
						array(
							'label'     => __( 'Custom Attributes', 'wsform-submission-number' ),
							'meta_keys' => array( 'custom_attributes' ),
						),
					),
				),
			),
		);
	}

	/**
	 * Declare the sequence setting.
	 *
	 * @param array $meta_keys Settings known to WS Form.
	 *
	 * @return array
	 */
	public function register_meta_keys( $meta_keys ) {
		$meta_keys[ self::SEQUENCE_META_KEY ] = array(
			'label'   => __( 'Sequence', 'wsform-submission-number' ),
			'type'    => 'select',
			'default' => Sequences::OWN,
			'options' => $this->get_sequence_options(),
			'help'    => sprintf(
				/* translators: %s: link to the sequences screen. */
				__( 'Share a sequence to number several forms in a row. Formats and start numbers are set in %s.', 'wsform-submission-number' ),
				'<a href="' . esc_url( admin_url( 'admin.php?page=' . Admin\Sequences_Page::SLUG ) ) . '" target="_blank">' . esc_html__( 'WS Form > Numbering', 'wsform-submission-number' ) . '</a>'
			),
		);

		return $meta_keys;
	}

	/**
	 * Sequence choices: the form's own one, then every shared sequence.
	 *
	 * @return array
	 */
	private function get_sequence_options() {
		$options = array(
			array(
				'value' => Sequences::OWN,
				'text'  => __( 'Own sequence (this form only)', 'wsform-submission-number' ),
			),
		);

		foreach ( Sequences::shared() as $sequence_id => $sequence ) {
			$options[] = array(
				'value' => $sequence_id,
				'text'  => $sequence['name'],
			);
		}

		return $options;
	}

	/**
	 * Number fields of a form, outside repeatable sections.
	 *
	 * A repeatable section would need one number per row, which makes no sense
	 * for a submission number, so those fields are left out.
	 *
	 * @param object $form_object WS Form form object.
	 *
	 * @return object[] Fields, by field ID.
	 */
	public static function find_in_form( $form_object ) {
		if ( ! is_object( $form_object ) || empty( $form_object->groups ) || ! class_exists( 'WS_Form_Common' ) ) {
			return array();
		}

		// get_fields_from_form() would keep this form in WS Form's static cache, which other code then reads.
		$fields = \WS_Form_Common::get_fields_from_form_group( $form_object->groups, false, false );
		$found  = array();

		foreach ( $fields as $field_id => $field ) {
			if ( self::TYPE === $field->type && empty( $field->section_repeatable ) ) {
				$found[ (int) $field_id ] = $field;
			}
		}

		return $found;
	}

	/**
	 * Sequence chosen in a field's settings.
	 *
	 * @param object $field WS Form field object.
	 *
	 * @return string Sequence ID, or Sequences::OWN.
	 */
	public static function chosen_sequence( $field ) {
		return (string) \WS_Form_Common::get_object_meta_value( $field, self::SEQUENCE_META_KEY, Sequences::OWN );
	}
}
