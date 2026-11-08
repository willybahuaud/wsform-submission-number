<?php
/**
 * Numbers given to submissions.
 *
 * @package WSForm_Submission_Number
 */

namespace WSForm_Submission_Number;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Plugin table, read fresh on purpose.

/**
 * Remembers which number each submission received.
 *
 * This table is the reference: the submission meta is only a copy, which
 * WS Form, an admin edit or a resumed draft could overwrite.
 */
class Assignments {

	/**
	 * Number a submission field received.
	 *
	 * @param int $submit_id Submission ID.
	 * @param int $field_id  Field ID.
	 *
	 * @return array|null Row, or null when no number was given yet.
	 */
	public static function find( $submit_id, $field_id ) {
		global $wpdb;

		$table = Schema::assignments_table();
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE submit_id = %d AND field_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name.
				$submit_id,
				$field_id
			),
			ARRAY_A
		);

		return $row ? $row : null;
	}

	/**
	 * Every number of a submission, by field ID.
	 *
	 * @param int $submit_id Submission ID.
	 *
	 * @return array<int,array>
	 */
	public static function for_submission( $submit_id ) {
		global $wpdb;

		$table = Schema::assignments_table();
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE submit_id = %d ORDER BY field_id", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name.
				$submit_id
			),
			ARRAY_A
		);

		$by_field = array();
		foreach ( (array) $rows as $row ) {
			$by_field[ (int) $row['field_id'] ] = $row;
		}

		return $by_field;
	}

	/**
	 * Record a number.
	 *
	 * The primary key on (submit_id, field_id) makes a second record for the
	 * same field impossible, even if two requests race.
	 *
	 * @param array $row Keys submit_id, field_id, form_id, sequence_id, period, number, formatted.
	 *
	 * @return bool False when the submission field already had a number.
	 */
	public static function insert( $row ) {
		global $wpdb;

		$table = Schema::assignments_table();

		$inserted = $wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO {$table} (submit_id, field_id, form_id, sequence_id, period, number, formatted, assigned_at) VALUES (%d, %d, %d, %s, %s, %d, %s, %s)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name.
				$row['submit_id'],
				$row['field_id'],
				$row['form_id'],
				$row['sequence_id'],
				$row['period'],
				$row['number'],
				$row['formatted'],
				current_time( 'mysql', true )
			)
		);

		return 1 === $inserted;
	}

	/**
	 * Whether a sequence already gave numbers, in any period.
	 *
	 * @param string $sequence_id Sequence ID.
	 *
	 * @return bool
	 */
	public static function sequence_used( $sequence_id ) {
		global $wpdb;

		$table = Schema::assignments_table();

		return null !== $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 1 FROM {$table} WHERE sequence_id = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name.
				$sequence_id
			)
		);
	}
}
