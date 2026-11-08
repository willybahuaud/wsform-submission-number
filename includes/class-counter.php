<?php
/**
 * Atomic counters.
 *
 * @package WSForm_Submission_Number
 */

namespace WSForm_Submission_Number;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin table, whose name cannot be a placeholder; a cached value would hand out duplicates.

/**
 * Hands out numbers without duplicates, even under simultaneous submissions.
 *
 * Every write is a single statement. MySQL locks the counter row for its
 * duration, and LAST_INSERT_ID(expr) gives the new value back to this
 * connection only, so no other request can read it in between.
 */
class Counter {

	/**
	 * Take the next number of a period.
	 *
	 * The first number of a period opens its row with the period start,
	 * which is how sequences reset without any scheduled task.
	 *
	 * @param string $sequence_id  Sequence ID.
	 * @param string $period       Period key.
	 * @param int    $period_start First number of a period that has no row yet.
	 *
	 * @return int The number, or 0 when the database refused the write.
	 */
	public static function next( $sequence_id, $period, $period_start ) {
		global $wpdb;

		$table = Schema::counters_table();

		$written = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table} (sequence_id, period, value) VALUES (%s, %s, LAST_INSERT_ID(%d))
				ON DUPLICATE KEY UPDATE value = LAST_INSERT_ID(value + 1)",
				$sequence_id,
				$period,
				max( 1, (int) $period_start )
			)
		);

		if ( false === $written ) {
			return 0;
		}

		return (int) $wpdb->get_var( 'SELECT LAST_INSERT_ID()' );
	}

	/**
	 * Last number given in a period.
	 *
	 * @param string $sequence_id Sequence ID.
	 * @param string $period      Period key.
	 *
	 * @return int|null Null when the period has not started.
	 */
	public static function last( $sequence_id, $period ) {
		global $wpdb;

		$table = Schema::counters_table();
		$value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT value FROM {$table} WHERE sequence_id = %s AND period = %s",
				$sequence_id,
				$period
			)
		);

		return null === $value ? null : (int) $value;
	}

	/**
	 * Set the next number of a period, never below what was already given.
	 *
	 * The check and the write are the same UPDATE, so a submission arriving
	 * in between cannot slip a duplicate past the guard.
	 *
	 * @param string $sequence_id Sequence ID.
	 * @param string $period      Period key.
	 * @param int    $next_number Number the next submission should get.
	 *
	 * @return bool False when a number at or above $next_number was already given.
	 */
	public static function set_next( $sequence_id, $period, $next_number ) {
		global $wpdb;

		$table      = Schema::counters_table();
		$last_value = max( 0, (int) $next_number - 1 );

		$created = $wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO {$table} (sequence_id, period, value) VALUES (%s, %s, %d)",
				$sequence_id,
				$period,
				$last_value
			)
		);

		if ( 1 === $created ) {
			return true;
		}

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET value = %d WHERE sequence_id = %s AND period = %s AND value < %d",
				$last_value,
				$sequence_id,
				$period,
				$last_value
			)
		);

		// MySQL reports no change when the value is already the target, which is fine.
		return self::last( $sequence_id, $period ) === $last_value;
	}

	/**
	 * Delete every counter of a sequence.
	 *
	 * @param string $sequence_id Sequence ID.
	 *
	 * @return void
	 */
	public static function delete_sequence( $sequence_id ) {
		global $wpdb;

		$wpdb->delete( Schema::counters_table(), array( 'sequence_id' => $sequence_id ), array( '%s' ) );
	}
}
