<?php
/**
 * Integration scenarios, run inside the lab site.
 *
 * Run with: ddev wp eval-file wp-content/plugins/wsform-submission-number/tests/integration/run.php --user=admin
 *
 * Every form, sequence and submission created here is removed at the end.
 *
 * @package WSForm_Submission_Number\Tests
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Test runner, never shipped.

use WSForm_Submission_Number\Admin\Sequence_Editor;
use WSForm_Submission_Number\Assignments;
use WSForm_Submission_Number\Change_Log;
use WSForm_Submission_Number\Format;
use WSForm_Submission_Number\Numbering;
use WSForm_Submission_Number\Schema;
use WSForm_Submission_Number\Sequences;
use WSForm_Submission_Number_Lab_Forms as Lab_Forms;
use WSForm_Submission_Number_Lab_Test as Lab;

require __DIR__ . '/class-lab-forms.php';
require __DIR__ . '/class-lab-test.php';
require __DIR__ . '/updater-scenarios.php';

/**
 * What the run created, removed by wsfsn_test_clean_up().
 *
 * @var array
 */
$GLOBALS['wsfsn_test_created'] = array(
	'forms'     => array(),
	'sequences' => array(),
);

/**
 * Create a form and remember it for the clean-up.
 *
 * @param string $label       Form name.
 * @param string $sequence_id Shared sequence ID, or "" for its own.
 *
 * @return array Form, see Lab_Forms::create().
 */
function wsfsn_test_form( $label, $sequence_id = '' ) {
	$form = Lab_Forms::create( 'Lab ' . $label, $sequence_id );

	$GLOBALS['wsfsn_test_created']['forms'][]     = $form['form_id'];
	$GLOBALS['wsfsn_test_created']['sequences'][] = Sequences::own_id( $form['number_field_id'] );

	return $form;
}

/**
 * Number recorded for a submission.
 *
 * @param array $result Result of Lab::submit().
 *
 * @return string Empty when none.
 */
function wsfsn_test_number( $result ) {
	return wsform_submission_number_get( $result['submit_id'] );
}

/**
 * Settings of a sequence, ready for Sequence_Editor::save().
 *
 * @param string $sequence_id Sequence ID.
 * @param int    $next_number Next number to ask for.
 *
 * @return array
 */
function wsfsn_test_editor_input( $sequence_id, $next_number ) {
	return array_merge( Sequences::get( $sequence_id ), array( 'next_number' => $next_number ) );
}

/**
 * Two forms with their own sequence, one starting at 1543; values sent by the browser are ignored.
 *
 * @return void
 */
function wsfsn_test_own_sequences() {
	$form_a = wsfsn_test_form( 'A' );
	$form_b = wsfsn_test_form( 'B' );
	$own_a  = Sequences::own_id( $form_a['number_field_id'] );

	Lab::check( null !== Sequences::get( $own_a ), 'Publishing a form creates its own sequence' );

	$saved = Sequence_Editor::save( wsfsn_test_editor_input( $own_a, 1543 ) );
	Lab::same( array(), $saved['errors'], 'A new sequence can start at 1543' );

	$forced  = array( 'field_' . $form_a['number_field_id'] => 'HACK-999' );
	$first_a = Lab::submit( $form_a, $forced );
	$next_a  = Lab::submit( $form_a );
	$first_b = Lab::submit( $form_b );

	Lab::same( '1543', wsfsn_test_number( $first_a ), 'Form A starts at its start number' );
	Lab::same( '1544', wsfsn_test_number( $next_a ), 'Form A goes on' );
	Lab::same( '0001', wsfsn_test_number( $first_b ), 'Form B has its own sequence' );
	Lab::same( '1543', Lab::saved_value( $first_a['submit_id'], $form_a['number_field_id'] ), 'A value sent by the browser is replaced' );
}

/**
 * Two forms sharing a sequence get numbers in a row, and the next number only goes up.
 *
 * @return void
 */
function wsfsn_test_shared_sequence_and_guard() {
	$created = Sequence_Editor::save(
		array(
			'name'        => 'Lab quotes',
			'format'      => 'DEV-{YYYY}-{N}',
			'digits'      => 4,
			'reset'       => Format::RESET_YEARLY,
			'next_number' => 1,
		)
	);
	$shared  = $created['sequence']['id'];

	$GLOBALS['wsfsn_test_created']['sequences'][] = $shared;

	$form_c = wsfsn_test_form( 'C', $shared );
	$form_d = wsfsn_test_form( 'D', $shared );
	$year   = wp_date( 'Y' );

	$numbers = array(
		wsfsn_test_number( Lab::submit( $form_c ) ),
		wsfsn_test_number( Lab::submit( $form_d ) ),
		wsfsn_test_number( Lab::submit( $form_c ) ),
	);
	Lab::same( array( "DEV-$year-0001", "DEV-$year-0002", "DEV-$year-0003" ), $numbers, 'Forms sharing a sequence get numbers in a row' );

	Lab::same( array( 'next_too_low' ), Sequence_Editor::save( wsfsn_test_editor_input( $shared, 2 ) )['errors'], 'The next number cannot go below the last one' );
	Lab::same( array( 'next_too_low' ), Sequence_Editor::save( wsfsn_test_editor_input( $shared, 3 ) )['errors'], 'The next number cannot equal the last one' );

	$jump = Sequence_Editor::save( wsfsn_test_editor_input( $shared, 10 ) );
	Lab::same( array( 'gap' ), $jump['notices'], 'Jumping forward warns about the gap' );

	$log = Change_Log::entries()[0];
	Lab::same( array( $shared, 4, 10, get_current_user_id() ), array( $log['sequence_id'], $log['old_next'], $log['new_next'], $log['user_id'] ), 'The change is logged' );

	Lab::same( "DEV-$year-0010", wsfsn_test_number( Lab::submit( $form_d ) ), 'The next submission takes the new number' );

	$reset_change = wsfsn_test_editor_input( $shared, 11 );

	$reset_change['reset'] = Format::RESET_NEVER;
	Lab::same( array( 'reset_locked' ), Sequence_Editor::save( $reset_change )['errors'], 'A used sequence keeps its reset rule' );
}

/**
 * Drafts, invalid and spam submissions take no number; a resumed draft does.
 *
 * @return void
 */
function wsfsn_test_nothing_consumed() {
	$form     = wsfsn_test_form( 'Skips' );
	$field_id = $form['number_field_id'];

	$draft = Lab::submit( $form, array( 'wsf_post_mode' => 'save' ) );
	Lab::check( $draft['submit_id'] > 0, 'The draft is saved' );
	Lab::same( '', wsfsn_test_number( $draft ), 'A draft gets no number' );
	Lab::same( '', (string) Lab::saved_value( $draft['submit_id'], $field_id ), 'A draft keeps an empty field' );

	$invalid = Lab::submit( $form, array( 'field_' . $form['fields']['email'][0] => '' ) );
	Lab::check( ! empty( $invalid['json']['error_validation'] ), 'The invalid submission is refused' );

	$spam = Lab::submit( $form, array( 'field_' . $field_id => 'SPAM-1' ), 'spam' );
	Lab::same( '', wsfsn_test_number( $spam ), 'Spam gets no number' );
	Lab::same( '', (string) Lab::saved_value( $spam['submit_id'], $field_id ), 'Spam keeps an empty field, not the browser value' );

	Lab::same( '0001', wsfsn_test_number( Lab::submit( $form ) ), 'Skipped submissions consumed no number' );

	$resumed = Lab::submit( $form, array( 'wsf_hash' => $draft['json']['hash'] ) );
	Lab::same( $draft['submit_id'], $resumed['submit_id'], 'The resumed draft is the same submission' );
	Lab::same( '0002', wsfsn_test_number( $resumed ), 'The resumed draft gets the next number once sent' );

	$again = Lab::submit( $form, array( 'wsf_hash' => $draft['json']['hash'] ) );
	Lab::same( '0002', wsfsn_test_number( $again ), 'Sending the same submission again keeps its number' );

	$released = new WS_Form_Submit();

	$released->id      = $spam['submit_id'];
	$released->form_id = $form['form_id'];
	$released->db_set_status( 'not_spam' );
	Lab::same( '0003', wsform_submission_number_get( $spam['submit_id'] ), 'Spam marked as not spam gets a number' );
}

/**
 * The email sent by the submission shows its number.
 *
 * @return void
 */
function wsfsn_test_email() {
	$form   = wsfsn_test_form( 'Email' );
	$marker = 'lab-' . wp_generate_password( 8, false );
	$sent   = Lab::submit( $form, array( 'field_' . $form['fields']['textarea'][0] => $marker ) );
	$text   = Lab::find_email_text( $marker );

	Lab::check( '' !== $text, 'The email reached Mailpit' );
	Lab::check( false !== strpos( $text, wsfsn_test_number( $sent ) ), 'The email shows the number' );
}

/**
 * An admin edit cannot change a number.
 *
 * @return void
 */
function wsfsn_test_admin_edit() {
	$form     = wsfsn_test_form( 'Edit' );
	$sent     = Lab::submit( $form );
	$field_id = $form['number_field_id'];
	$textarea = $form['fields']['textarea'][0];
	$password = WP_Application_Passwords::create_new_application_password( get_current_user_id(), array( 'name' => 'wsfsn-lab' ) );
	$user     = wp_get_current_user();

	$edited = array(
		'id'   => $sent['submit_id'],
		'meta' => array(
			'field_' . $field_id => array(
				'id'    => $field_id,
				'value' => 'EDITED',
			),
			'field_' . $textarea => array(
				'id'    => $textarea,
				'value' => 'Edited inquiry',
			),
		),
	);

	$response = wp_remote_post(
		Lab::SITE_URL . '/wp-json/ws-form/v1/submit/' . $sent['submit_id'] . '/put/',
		array(
			'headers' => array(
				'Host'           => Lab::SITE_HOST,
				'X-Lab-Scenario' => 'edit',
				'Authorization'  => 'Basic ' . base64_encode( $user->user_login . ':' . $password[0] ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- HTTP Basic auth.
			),
			// Sent like the WS Form admin script: a JSON "data" field next to the nonce.
			'body'    => array(
				'wsf_nonce' => wp_create_nonce( WS_FORM_POST_NONCE_ACTION_NAME ),
				'data'      => wp_json_encode(
					array(
						'id'     => $form['form_id'],
						'submit' => $edited,
					)
				),
			),
		)
	);

	WP_Application_Passwords::delete_application_password( get_current_user_id(), $password[1]['uuid'] );

	Lab::same( 200, wp_remote_retrieve_response_code( $response ), 'The admin edit request succeeds' );
	Lab::same( 'Edited inquiry', Lab::saved_value( $sent['submit_id'], $textarea ), 'Other fields can be edited' );
	Lab::same( '0001', Lab::saved_value( $sent['submit_id'], $field_id ), 'The number survives an admin edit' );
}

/**
 * The README example numbers a paid form once the payment is confirmed.
 *
 * @return void
 */
function wsfsn_test_readme_example() {
	$readme  = file_get_contents( dirname( __DIR__, 2 ) . '/README.md' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file.
	$example = file_get_contents( __DIR__ . '/readme-example.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file.
	$code    = trim( substr( $example, strpos( $example, "add_filter( 'wsform_submission_number_assign_now'" ) ) );

	Lab::check( false !== strpos( $readme, $code ), 'README.md shows the tested example' );

	$form = wsfsn_test_form( 'Paid' );
	Lab_Forms::add_action( $form['form_id'], 'monetico_payment' );

	$sent = Lab::submit( $form, array(), 'readme' );
	Lab::check( $sent['submit_id'] > 0, 'The paid form submission is saved' );
	Lab::same( '', wsfsn_test_number( $sent ), 'A paid form waits for the payment' );

	require_once __DIR__ . '/readme-example.php';
	do_action( 'wsform_monetico_payment_updated', $sent['submit_id'], 'failed', array(), 'pending' );
	Lab::same( '', wsfsn_test_number( $sent ), 'A failed payment gives no number' );

	do_action( 'wsform_monetico_payment_updated', $sent['submit_id'], 'paid', array(), 'failed' );
	Lab::same( '0001', wsfsn_test_number( $sent ), 'A confirmed payment gives the number' );
	Lab::same( '0001', Lab::saved_value( $sent['submit_id'], $form['number_field_id'] ), 'The number is written into the submission' );

	remove_filter( 'wsform_submission_number_assign_now', 'mysite_wait_for_payment' );
}

/**
 * Fifty simultaneous submissions get fifty distinct numbers, without gaps.
 *
 * @return void
 */
function wsfsn_test_concurrency() {
	$form    = wsfsn_test_form( 'Concurrency' );
	$results = Lab::submit_in_parallel( $form, 50 );
	$numbers = array();

	foreach ( $results as $result ) {
		$numbers[] = (int) wsfsn_test_number( $result );
	}
	sort( $numbers );

	Lab::same( 50, count( array_filter( array_column( $results, 'submit_id' ) ) ), 'The 50 submissions are saved' );
	Lab::same( range( 1, 50 ), $numbers, 'The 50 submissions get numbers 1 to 50, each once' );
}

/**
 * A new year, or month, restarts the numbering at the period start.
 *
 * @return void
 */
function wsfsn_test_period_reset() {
	$paris = new DateTimeZone( 'Europe/Paris' );
	$cases = array(
		Format::RESET_YEARLY  => array( 'R-{YYYY}-{N}', '2026-12-31 23:59:59', '2027-01-01 00:00:00', 'R-2027-005' ),
		Format::RESET_MONTHLY => array( 'R-{YYYY}{MM}-{N}', '2026-11-30 23:59:59', '2026-12-01 00:00:00', 'R-202612-005' ),
		Format::RESET_NEVER   => array( 'R-{N}', '2026-12-31 23:59:59', '2027-01-01 00:00:00', 'R-007' ),
	);

	foreach ( $cases as $reset => $case ) {
		$sequence = Sequences::save(
			array(
				'name'         => 'Lab reset ' . $reset,
				'format'       => $case[0],
				'digits'       => 3,
				'reset'        => $reset,
				'period_start' => 5,
			)
		);

		$GLOBALS['wsfsn_test_created']['sequences'][] = $sequence['id'];

		Numbering::take( $sequence, new DateTimeImmutable( '2026-06-01 12:00:00', $paris ) );
		Numbering::take( $sequence, new DateTimeImmutable( $case[1], $paris ) );
		$next = Numbering::take( $sequence, new DateTimeImmutable( $case[2], $paris ) );

		Lab::same( $case[3], $next['formatted'], 'Reset rule "' . $reset . '" across a period change' );
	}
}

/**
 * Remove what the run created.
 *
 * @return void
 */
function wsfsn_test_clean_up() {
	global $wpdb;

	foreach ( array_unique( $GLOBALS['wsfsn_test_created']['forms'] ) as $form_id ) {
		$wpdb->delete( Schema::assignments_table(), array( 'form_id' => $form_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Test clean-up.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}wsf_submit_meta WHERE parent_id IN (SELECT id FROM {$wpdb->prefix}wsf_submit WHERE form_id = %d)", $form_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Test clean-up.
		$wpdb->delete( $wpdb->prefix . 'wsf_submit', array( 'form_id' => $form_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Test clean-up.
		Lab_Forms::delete( $form_id );
	}

	foreach ( array_unique( $GLOBALS['wsfsn_test_created']['sequences'] ) as $sequence_id ) {
		Sequences::delete( $sequence_id );
	}

	$log = array_filter( Change_Log::entries(), 'wsfsn_test_is_foreign_log_entry' );
	update_option( Change_Log::OPTION, array_values( $log ), false );

	wp_delete_file( WPMU_PLUGIN_DIR . '/wsform-submission-number-lab.php' );

	// The simulated releases must not show as a real update in the lab admin.
	$release = new WSForm_Submission_Number\Updater\Github_Release( 'willybahuaud', 'wsform-submission-number', WSForm_Submission_Number\Plugin::VERSION );
	delete_site_transient( $release->get_cache_key() );
	delete_site_transient( 'update_plugins' );
}

/**
 * Whether a log entry is about a sequence this run did not create.
 *
 * @param array $entry Log entry.
 *
 * @return bool
 */
function wsfsn_test_is_foreign_log_entry( $entry ) {
	return ! in_array( $entry['sequence_id'], $GLOBALS['wsfsn_test_created']['sequences'], true );
}

wp_set_current_user( 1 );
wp_mkdir_p( WPMU_PLUGIN_DIR );

// Lab requests to the site and Mailpit go through: only GitHub and WordPress.org are simulated.
add_filter( 'pre_http_request', array( 'WSForm_Submission_Number_Fake_Github', 'answer' ), 10, 3 );
copy( __DIR__ . '/lab-mu-plugin.php', WPMU_PLUGIN_DIR . '/wsform-submission-number-lab.php' );

$wsfsn_test_scenarios = array(
	'wsfsn_test_own_sequences',
	'wsfsn_test_shared_sequence_and_guard',
	'wsfsn_test_nothing_consumed',
	'wsfsn_test_email',
	'wsfsn_test_admin_edit',
	'wsfsn_test_readme_example',
	'wsfsn_test_concurrency',
	'wsfsn_test_period_reset',
	'wsfsn_test_updater_newer_release',
	'wsfsn_test_updater_up_to_date',
	'wsfsn_test_updater_tested_branch',
	'wsfsn_test_updater_missing_manifest',
	'wsfsn_test_updater_foreign_host',
	'wsfsn_test_updater_invalid_releases',
	'wsfsn_test_updater_failures',
	'wsfsn_test_updater_no_token',
);

try {
	foreach ( $wsfsn_test_scenarios as $wsfsn_test_scenario ) {
		WP_CLI::log( $wsfsn_test_scenario );
		call_user_func( $wsfsn_test_scenario );
	}
} finally {
	wsfsn_test_clean_up();
}

Lab::report();
