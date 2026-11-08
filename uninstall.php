<?php
/**
 * Removes the plugin data when the plugin is deleted.
 *
 * Submissions keep their numbers: they are field values stored by WS Form,
 * and belong to the site's records.
 *
 * @package WSForm_Submission_Number
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Same names as WSForm_Submission_Number\Schema.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wsform_submission_number_counters" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin table.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wsform_submission_number_assignments" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin table.

delete_option( 'wsform_submission_number_db_version' );
delete_option( 'wsform_submission_number_sequences' );
delete_option( 'wsform_submission_number_log' );

// Same key as WSForm_Submission_Number\Updater\Github_Release::get_cache_key().
delete_site_transient( 'wsform_submission_number_release_' . md5( 'willybahuaud/wsform-submission-number' ) );
