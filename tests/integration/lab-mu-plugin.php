<?php
/**
 * Test switches for requests sent by run.php. Copied into mu-plugins during a run only.
 *
 * Reacts to the X-Lab-Scenario header, so a normal visit to the lab site
 * is never affected.
 *
 * @package WSForm_Submission_Number\Tests
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Lab file, never shipped.

$wsfsn_lab_scenario = isset( $_SERVER['HTTP_X_LAB_SCENARIO'] ) ? sanitize_key( wp_unslash( $_SERVER['HTTP_X_LAB_SCENARIO'] ) ) : '';

if ( '' !== $wsfsn_lab_scenario ) {
	// The runner calls the site over plain HTTP from inside the container.
	add_filter( 'wp_is_application_passwords_available', '__return_true' );
}

if ( 'spam' === $wsfsn_lab_scenario ) {
	add_filter( 'wsf_submit_spam_check', 'wsfsn_lab_mark_as_spam', 20 );
}

if ( 'readme' === $wsfsn_lab_scenario ) {
	require WP_CONTENT_DIR . '/plugins/wsform-submission-number/tests/integration/readme-example.php';
}

/**
 * Score every submission as spam.
 *
 * @return int
 */
function wsfsn_lab_mark_as_spam() {
	return 100;
}
