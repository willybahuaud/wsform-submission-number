<?php
/**
 * Saving a sequence from the admin.
 *
 * @package WSForm_Submission_Number
 */

namespace WSForm_Submission_Number\Admin;

use WSForm_Submission_Number\Assignments;
use WSForm_Submission_Number\Change_Log;
use WSForm_Submission_Number\Counter;
use WSForm_Submission_Number\Format;
use WSForm_Submission_Number\Numbering;
use WSForm_Submission_Number\Sequences;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Checks and applies the sequence form, apart from any screen output.
 */
class Sequence_Editor {

	/**
	 * Save a sequence and its next number.
	 *
	 * The next number can move forward (leaving a gap) but never back to a
	 * number already given in the current period: that would hand it out twice.
	 *
	 * @param array $input Unslashed form values: id, name, format, digits, reset, period_start, next_number.
	 *
	 * @return array Keys sequence (array|null), errors (codes), notices (codes).
	 */
	public static function save( $input ) {
		$existing = empty( $input['id'] ) ? null : Sequences::get( sanitize_key( $input['id'] ) );
		$sequence = Sequences::normalize( array_merge( (array) $existing, self::pick_settings( $input ) ) );
		$errors   = Format::find_problems( $sequence['format'], $sequence['reset'] );

		if ( '' === $sequence['name'] ) {
			$errors[] = 'missing_name';
		}

		if ( $existing && $existing['reset'] !== $sequence['reset'] && Assignments::sequence_used( $existing['id'] ) ) {
			$errors[] = 'reset_locked';
		}

		$status      = Numbering::status( $sequence );
		$next_number = isset( $input['next_number'] ) && '' !== $input['next_number'] ? (int) $input['next_number'] : $status['next'];

		if ( null !== $status['last'] && $next_number <= $status['last'] ) {
			$errors[] = 'next_too_low';
		}

		if ( $errors ) {
			return self::result( null, $errors, array() );
		}

		$sequence = Sequences::save( $sequence );

		return self::apply_next_number( $sequence, $existing ? $status['next'] : null, $next_number, $status );
	}

	/**
	 * Move the counter of the current period, and log it.
	 *
	 * @param array    $sequence    Saved sequence.
	 * @param int|null $old_next    Next number before, null for a new sequence.
	 * @param int      $next_number Requested next number.
	 * @param array    $status      Period status before the change.
	 *
	 * @return array See save().
	 */
	private static function apply_next_number( $sequence, $old_next, $next_number, $status ) {
		if ( null !== $old_next && $old_next === $next_number ) {
			return self::result( $sequence, array(), array() );
		}

		// A submission may have taken a number since the check: the counter has the last word.
		if ( ! Counter::set_next( $sequence['id'], $status['period'], $next_number ) ) {
			return self::result( $sequence, array( 'next_too_low' ), array() );
		}

		Change_Log::add( $sequence['id'], $sequence['name'], $status['period'], $old_next, $next_number );

		$notices = array();
		if ( null !== $status['last'] && $next_number > $status['last'] + 1 ) {
			$notices[] = 'gap';
		}

		return self::result( $sequence, array(), $notices );
	}

	/**
	 * Keep the settings the form may change.
	 *
	 * @param array $input Raw form values, unslashed.
	 *
	 * @return array
	 */
	private static function pick_settings( $input ) {
		return array_intersect_key( (array) $input, array_flip( array( 'id', 'name', 'format', 'digits', 'reset', 'period_start' ) ) );
	}

	/**
	 * Build a result.
	 *
	 * @param array|null $sequence Saved sequence.
	 * @param string[]   $errors   Error codes.
	 * @param string[]   $notices  Notice codes.
	 *
	 * @return array
	 */
	private static function result( $sequence, $errors, $notices ) {
		return array(
			'sequence' => $sequence,
			'errors'   => $errors,
			'notices'  => $notices,
		);
	}
}
