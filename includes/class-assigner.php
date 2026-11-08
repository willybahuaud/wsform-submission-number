<?php
/**
 * Gives numbers to submissions.
 *
 * @package WSForm_Submission_Number
 */

namespace WSForm_Submission_Number;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Writes the number into each number field of a saved submission.
 *
 * The moment is wsf_submit_update, fired by the "Save Submission" action
 * (priority 5) right after WS Form saved the row:
 * - validation and spam checks are done, and the status is final;
 * - the submission has an ID;
 * - the other actions (emails at 175, hooks at 190…) have not run yet, and
 *   read the submission kept in memory, so they see the number.
 *
 * The hook also fires for drafts, resumed drafts and the second pass of the
 * same action (priority 200), hence the checks and the reuse of a number
 * already recorded.
 */
class Assigner {

	/**
	 * Hook into WS Form.
	 *
	 * @return void
	 */
	public function boot() {
		add_action( 'wsf_submit_update', array( $this, 'on_submit_update' ) );
		add_action( 'wsf_submit_status', array( $this, 'on_status_change' ), 10, 2 );
	}

	/**
	 * Number a submission WS Form just saved.
	 *
	 * @param \WS_Form_Submit $submit Submission.
	 *
	 * @return void
	 */
	public function on_submit_update( $submit ) {
		$is_final = isset( $submit->post_mode, $submit->status ) && 'submit' === $submit->post_mode && 'publish' === $submit->status;

		self::process( $submit, $is_final, 'submit' );
	}

	/**
	 * Number a submission an admin marked as "not spam".
	 *
	 * Spam receives no number when sent, so it gets one once released.
	 *
	 * @param int    $submit_id Submission ID.
	 * @param string $status    New status.
	 *
	 * @return void
	 */
	public function on_status_change( $submit_id, $status ) {
		if ( 'publish' !== $status ) {
			return;
		}

		$submit = self::load_submission( $submit_id );
		if ( $submit ) {
			self::process( $submit, true, 'status' );
		}
	}

	/**
	 * Give a submission its numbers now, skipping the "assign now" filter.
	 *
	 * Backs wsform_submission_number_assign(), for numbering later from
	 * another hook, such as a confirmed payment.
	 *
	 * @param int $submit_id Submission ID.
	 *
	 * @return array<int,string> Formatted numbers, by field ID.
	 */
	public static function assign_later( $submit_id ) {
		$submit = self::load_submission( $submit_id );
		if ( ! $submit || 'publish' !== $submit->status ) {
			return array();
		}

		return self::process( $submit, true, 'later' );
	}

	/**
	 * Write a number, or nothing, into every number field of a submission.
	 *
	 * A field that already received a number keeps it. Otherwise it gets a
	 * new one if the submission is final and the filter agrees, and stays
	 * empty if not, which also wipes any value sent by the browser.
	 *
	 * @param \WS_Form_Submit $submit   Submission.
	 * @param bool            $is_final Whether the submission is saved, sent and not spam.
	 * @param string          $context  "submit", "status" or "later".
	 *
	 * @return array<int,string> Formatted numbers, by field ID.
	 */
	private static function process( $submit, $is_final, $context ) {
		$submit_id = isset( $submit->id ) ? (int) $submit->id : 0;
		$fields    = isset( $submit->form_object ) ? Field_Type::find_in_form( $submit->form_object ) : array();

		if ( $submit_id < 1 || ! $fields ) {
			return array();
		}

		$numbers = array();

		foreach ( $fields as $field_id => $field ) {
			$assignment = Assignments::find( $submit_id, $field_id );

			if ( ! $assignment && $is_final && self::should_assign( $submit, $field_id, $context ) ) {
				$assignment = self::assign( $submit, $field );
			}

			$numbers[ $field_id ] = $assignment ? $assignment['formatted'] : '';
			Submission_Value::write( $submit, $field, $numbers[ $field_id ] );
		}

		return array_filter( $numbers, 'strlen' );
	}

	/**
	 * Ask whether a submission should get its number now.
	 *
	 * @param \WS_Form_Submit $submit   Submission.
	 * @param int             $field_id Field ID.
	 * @param string          $context  "submit", "status" or "later".
	 *
	 * @return bool
	 */
	private static function should_assign( $submit, $field_id, $context ) {
		if ( 'later' === $context ) {
			return true;
		}

		/**
		 * Filters whether a submission gets its number now.
		 *
		 * Return false to number it later with wsform_submission_number_assign(),
		 * for example once a payment is confirmed. The field stays empty meanwhile.
		 *
		 * @param bool            $assign_now Whether to number the submission now. Default true.
		 * @param \WS_Form_Submit $submit     Submission, with its form in $submit->form_object.
		 * @param int             $field_id   Number field ID.
		 * @param string          $context    "submit" when sent, "status" when marked as not spam.
		 */
		return (bool) apply_filters( 'wsform_submission_number_assign_now', true, $submit, $field_id, $context );
	}

	/**
	 * Take a number from the field's sequence and record it.
	 *
	 * @param \WS_Form_Submit $submit Submission.
	 * @param object          $field  Field.
	 *
	 * @return array|null Assignment row, or null when the database failed.
	 */
	private static function assign( $submit, $field ) {
		$form_label = isset( $submit->form_object->label ) ? $submit->form_object->label : '#' . $submit->form_id;
		$sequence   = Sequences::resolve_for_field( Field_Type::chosen_sequence( $field ), $field->id, $form_label );
		$taken      = Numbering::take( $sequence, Numbering::now() );

		if ( ! $taken ) {
			return null;
		}

		$row = array(
			'submit_id'   => (int) $submit->id,
			'field_id'    => (int) $field->id,
			'form_id'     => (int) $submit->form_id,
			'sequence_id' => $sequence['id'],
			'period'      => $taken['period'],
			'number'      => $taken['number'],
			'formatted'   => $taken['formatted'],
		);

		// Another request recorded a number first: keep it, the one taken here becomes a gap.
		if ( ! Assignments::insert( $row ) ) {
			return Assignments::find( $submit->id, $field->id );
		}

		/**
		 * Fires once a submission field received its number.
		 *
		 * @param string          $formatted Number, such as DEV-2026-0042.
		 * @param array           $row       Assignment: submit_id, field_id, form_id, sequence_id, period, number, formatted.
		 * @param \WS_Form_Submit $submit    Submission.
		 */
		do_action( 'wsform_submission_number_assigned', $taken['formatted'], $row, $submit );

		return $row;
	}

	/**
	 * Read a submission and its published form.
	 *
	 * @param int $submit_id Submission ID.
	 *
	 * @return \WS_Form_Submit|null
	 */
	private static function load_submission( $submit_id ) {
		if ( ! class_exists( 'WS_Form_Submit' ) ) {
			return null;
		}

		try {
			$submit     = new \WS_Form_Submit();
			$submit->id = absint( $submit_id );
			$submit->db_read( true, false, true );
			$submit->db_form_object_read( true );
		} catch ( \Exception $exception ) {
			return null;
		}

		return $submit;
	}
}
