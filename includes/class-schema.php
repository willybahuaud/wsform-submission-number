<?php
/**
 * Plugin tables.
 *
 * @package WSForm_Submission_Number
 */

namespace WSForm_Submission_Number;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates and names the two plugin tables.
 *
 * Counters live in their own table rather than in options: options go
 * through the object cache, and a get_option() then update_option() pair
 * hands the same number to two simultaneous submissions.
 */
class Schema {

	/**
	 * Bumped when a table definition changes.
	 */
	const DB_VERSION = '1';

	/**
	 * Option holding the installed schema version.
	 */
	const VERSION_OPTION = 'wsform_submission_number_db_version';

	/**
	 * Create or upgrade the tables when the schema version changed.
	 *
	 * Runs on every admin load rather than on activation only, so a plugin
	 * updated by copying files still gets its tables.
	 *
	 * @return void
	 */
	public static function maybe_install() {
		if ( self::DB_VERSION === get_option( self::VERSION_OPTION ) ) {
			return;
		}

		self::install();
	}

	/**
	 * Create or upgrade the tables.
	 *
	 * @return void
	 */
	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$counters        = self::counters_table();
		$assignments     = self::assignments_table();

		// dbDelta wants two spaces after PRIMARY KEY and one field per line.
		dbDelta(
			"CREATE TABLE {$counters} (
				sequence_id varchar(64) NOT NULL,
				period varchar(7) NOT NULL,
				value bigint(20) unsigned NOT NULL DEFAULT 0,
				PRIMARY KEY  (sequence_id,period)
			) {$charset_collate};
			CREATE TABLE {$assignments} (
				submit_id bigint(20) unsigned NOT NULL,
				field_id bigint(20) unsigned NOT NULL,
				form_id bigint(20) unsigned NOT NULL,
				sequence_id varchar(64) NOT NULL,
				period varchar(7) NOT NULL,
				number bigint(20) unsigned NOT NULL,
				formatted varchar(191) NOT NULL,
				assigned_at datetime NOT NULL,
				PRIMARY KEY  (submit_id,field_id),
				KEY sequence_period (sequence_id,period)
			) {$charset_collate};"
		);

		update_option( self::VERSION_OPTION, self::DB_VERSION, false );
	}

	/**
	 * Counter table: one row per sequence and period, holding the last number given.
	 *
	 * @return string
	 */
	public static function counters_table() {
		global $wpdb;

		return $wpdb->prefix . 'wsform_submission_number_counters';
	}

	/**
	 * Assignment table: the number each submission received, which never changes.
	 *
	 * @return string
	 */
	public static function assignments_table() {
		global $wpdb;

		return $wpdb->prefix . 'wsform_submission_number_assignments';
	}
}
