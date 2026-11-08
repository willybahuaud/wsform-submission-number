<?php
/**
 * Log of start number changes.
 *
 * @package WSForm_Submission_Number
 */

namespace WSForm_Submission_Number;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keeps who changed a next number, when, and from what to what.
 *
 * A short option is enough: these changes are rare, and only the recent
 * ones help when a number looks wrong.
 */
class Change_Log {

	const OPTION = 'wsform_submission_number_log';

	/**
	 * Entries kept.
	 */
	const MAX_ENTRIES = 200;

	/**
	 * Record a change.
	 *
	 * @param string   $sequence_id Sequence ID.
	 * @param string   $sequence_name Sequence name at the time.
	 * @param string   $period      Period key.
	 * @param int|null $old_next    Next number before, null for a new sequence.
	 * @param int      $new_next    Next number after.
	 *
	 * @return void
	 */
	public static function add( $sequence_id, $sequence_name, $period, $old_next, $new_next ) {
		$entries = self::entries();

		array_unshift(
			$entries,
			array(
				'time'          => time(),
				'user_id'       => get_current_user_id(),
				'sequence_id'   => $sequence_id,
				'sequence_name' => $sequence_name,
				'period'        => $period,
				'old_next'      => $old_next,
				'new_next'      => (int) $new_next,
			)
		);

		update_option( self::OPTION, array_slice( $entries, 0, self::MAX_ENTRIES ), false );
	}

	/**
	 * Entries, newest first.
	 *
	 * @return array[]
	 */
	public static function entries() {
		$entries = get_option( self::OPTION, array() );

		return is_array( $entries ) ? $entries : array();
	}
}
