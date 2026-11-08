<?php
/**
 * Public functions, for themes and other plugins.
 *
 * @package WSForm_Submission_Number
 */

use WSForm_Submission_Number\Assigner;
use WSForm_Submission_Number\Assignments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Give a submission its numbers now.
 *
 * Meant for numbering later than the submission, after returning false from
 * the wsform_submission_number_assign_now filter. Fields that already have a
 * number keep it, so calling it twice is harmless.
 *
 * @param int $submit_id WS Form submission ID.
 *
 * @return array<int,string> Formatted numbers, by field ID. Empty when the
 *                           submission is missing, not published, or has no number field.
 */
function wsform_submission_number_assign( $submit_id ) {
	return Assigner::assign_later( (int) $submit_id );
}

/**
 * Number of a submission.
 *
 * @param int $submit_id WS Form submission ID.
 * @param int $field_id  Number field ID, or 0 for the first one.
 *
 * @return string Formatted number, empty when none was given.
 */
function wsform_submission_number_get( $submit_id, $field_id = 0 ) {
	$assignments = Assignments::for_submission( (int) $submit_id );

	if ( $field_id ) {
		return isset( $assignments[ $field_id ] ) ? $assignments[ $field_id ]['formatted'] : '';
	}

	$first = reset( $assignments );

	return $first ? $first['formatted'] : '';
}
