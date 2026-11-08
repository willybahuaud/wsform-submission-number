<?php
/**
 * Which forms use which sequence.
 *
 * @package WSForm_Submission_Number
 */

namespace WSForm_Submission_Number\Admin;

use WSForm_Submission_Number\Field_Type;
use WSForm_Submission_Number\Sequences;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lists the number fields of every form, for the sequences screen.
 *
 * WS Form has no API to find fields by type across forms, so this reads its
 * tables, in the admin only. It looks at the forms as edited, which may
 * differ from the published version until the form is published again.
 */
class Usage {

	/**
	 * Number fields of all forms that are not in the trash.
	 *
	 * @return array[] Rows with field_id, form_id, form_label, chosen (sequence ID or "").
	 */
	public static function fields() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- WS Form tables, admin screen only.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT field.id AS field_id, form.id AS form_id, form.label AS form_label, meta.meta_value AS chosen
				FROM {$wpdb->prefix}wsf_field AS field
				INNER JOIN {$wpdb->prefix}wsf_section AS section ON section.id = field.section_id
				INNER JOIN {$wpdb->prefix}wsf_group AS grp ON grp.id = section.group_id
				INNER JOIN {$wpdb->prefix}wsf_form AS form ON form.id = grp.form_id
				LEFT JOIN {$wpdb->prefix}wsf_field_meta AS meta ON meta.parent_id = field.id AND meta.meta_key = %s
				WHERE field.type = %s AND form.status <> 'trash'
				ORDER BY form.label, field.id",
				Field_Type::SEQUENCE_META_KEY,
				Field_Type::TYPE
			),
			ARRAY_A
		);

		foreach ( (array) $rows as $index => $row ) {
			$rows[ $index ]['chosen'] = (string) $row['chosen'];
		}

		return (array) $rows;
	}

	/**
	 * Forms using each sequence, with fields pointing to a missing shared sequence apart.
	 *
	 * @param array[] $fields Rows from fields().
	 *
	 * @return array Keys by_sequence (sequence ID => form labels by form ID) and missing (rows).
	 */
	public static function map( $fields ) {
		$by_sequence = array();
		$missing     = array();

		foreach ( $fields as $row ) {
			$sequence_id = $row['chosen'];

			if ( Sequences::OWN !== $sequence_id && ! Sequences::get( $sequence_id ) ) {
				$missing[]   = $row;
				$sequence_id = Sequences::OWN;
			}

			if ( Sequences::OWN === $sequence_id ) {
				$sequence_id = Sequences::own_id( $row['field_id'] );
			}

			$by_sequence[ $sequence_id ][ (int) $row['form_id'] ] = $row['form_label'];
		}

		return array(
			'by_sequence' => $by_sequence,
			'missing'     => $missing,
		);
	}

	/**
	 * Forms with a number field but no enabled "Save Submission" action.
	 *
	 * Without that action WS Form saves nothing, so no number can be given.
	 *
	 * @param array[] $fields Rows from fields().
	 *
	 * @return array<int,string> Form labels, by form ID.
	 */
	public static function forms_without_saving( $fields ) {
		$form_labels = array_column( $fields, 'form_label', 'form_id' );
		$without     = array();

		foreach ( $form_labels as $form_id => $form_label ) {
			if ( ! self::form_saves_submissions( (int) $form_id ) ) {
				$without[ (int) $form_id ] = $form_label;
			}
		}

		return $without;
	}

	/**
	 * Whether a form has an enabled "Save Submission" action.
	 *
	 * @param int $form_id Form ID.
	 *
	 * @return bool
	 */
	private static function form_saves_submissions( $form_id ) {
		try {
			$form     = new \WS_Form_Form();
			$form->id = $form_id;
			$object   = $form->db_read( true, false, false, false, true );
		} catch ( \Exception $exception ) {
			return true;
		}

		return false !== \WS_Form_Action::get_form_action_config( $object, 'database' );
	}
}
