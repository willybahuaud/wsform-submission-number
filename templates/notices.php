<?php
/**
 * Notices of the sequences screen.
 *
 * @package WSForm_Submission_Number
 *
 * @var array $args Keys: notices (items with type and message).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

foreach ( $args['notices'] as $wsform_submission_number_notice ) {
	printf(
		'<div class="notice notice-%1$s"><p>%2$s</p></div>',
		esc_attr( $wsform_submission_number_notice['type'] ),
		esc_html( $wsform_submission_number_notice['message'] )
	);
}
