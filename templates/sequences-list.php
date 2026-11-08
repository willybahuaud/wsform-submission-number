<?php
/**
 * List of sequences.
 *
 * @package WSForm_Submission_Number
 *
 * @var array $args Keys: sequences, usage, without_saving, log, notices, page_url.
 */

use WSForm_Submission_Number\Admin\Sequences_Page;
use WSForm_Submission_Number\Numbering;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wsform_submission_number_reset_labels = array(
	'never'   => __( 'Never', 'wsform-submission-number' ),
	'yearly'  => __( 'Every year', 'wsform-submission-number' ),
	'monthly' => __( 'Every month', 'wsform-submission-number' ),
);
?>
<div class="wrap wsform-submission-number">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Submission Numbers', 'wsform-submission-number' ); ?></h1>
	<a href="<?php echo esc_url( Sequences_Page::url( 'new' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add a shared sequence', 'wsform-submission-number' ); ?></a>
	<hr class="wp-header-end">

	<?php load_template( WSFORM_SUBMISSION_NUMBER_PATH . 'templates/notices.php', false, array( 'notices' => $args['notices'] ) ); ?>

	<?php if ( $args['without_saving'] ) : ?>
		<div class="notice notice-warning inline">
			<p><?php esc_html_e( 'These forms have a Submission Number field but no "Save Submission" action. WS Form saves nothing for them, so they get no number:', 'wsform-submission-number' ); ?></p>
			<ul class="ul-disc">
				<?php foreach ( $args['without_saving'] as $wsform_submission_number_form_label ) : ?>
					<li><?php echo esc_html( $wsform_submission_number_form_label ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<?php if ( $args['usage']['missing'] ) : ?>
		<div class="notice notice-warning inline">
			<p><?php esc_html_e( 'These forms point to a shared sequence that does not exist on this site, probably after an import. They use their own sequence until you choose another one in the field settings:', 'wsform-submission-number' ); ?></p>
			<ul class="ul-disc">
				<?php foreach ( $args['usage']['missing'] as $wsform_submission_number_row ) : ?>
					<li><?php echo esc_html( $wsform_submission_number_row['form_label'] ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<?php if ( ! $args['sequences'] ) : ?>
		<p><?php esc_html_e( 'No sequence yet. Add a Submission Number field to a form and publish it: the form gets its own sequence. Or add a shared sequence to number several forms in a row.', 'wsform-submission-number' ); ?></p>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Sequence', 'wsform-submission-number' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Format', 'wsform-submission-number' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Reset', 'wsform-submission-number' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Last number', 'wsform-submission-number' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Next number', 'wsform-submission-number' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Forms', 'wsform-submission-number' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $args['sequences'] as $wsform_submission_number_sequence ) :
					$wsform_submission_number_status = Numbering::status( $wsform_submission_number_sequence );
					$wsform_submission_number_forms  = isset( $args['usage']['by_sequence'][ $wsform_submission_number_sequence['id'] ] ) ? $args['usage']['by_sequence'][ $wsform_submission_number_sequence['id'] ] : array();
					?>
					<tr>
						<td>
							<strong><a href="<?php echo esc_url( Sequences_Page::url( $wsform_submission_number_sequence['id'] ) ); ?>"><?php echo esc_html( $wsform_submission_number_sequence['name'] ); ?></a></strong>
							<br><span class="description"><?php echo empty( $wsform_submission_number_sequence['field_id'] ) ? esc_html__( 'Shared', 'wsform-submission-number' ) : esc_html__( 'Own to one form', 'wsform-submission-number' ); ?></span>
						</td>
						<td><code><?php echo esc_html( $wsform_submission_number_sequence['format'] ); ?></code></td>
						<td><?php echo esc_html( $wsform_submission_number_reset_labels[ $wsform_submission_number_sequence['reset'] ] ); ?></td>
						<td><?php echo '' === $wsform_submission_number_status['last_formatted'] ? '&mdash;' : '<code>' . esc_html( $wsform_submission_number_status['last_formatted'] ) . '</code>'; ?></td>
						<td><code><?php echo esc_html( $wsform_submission_number_status['next_formatted'] ); ?></code></td>
						<td><?php echo $wsform_submission_number_forms ? esc_html( implode( ', ', $wsform_submission_number_forms ) ) : '&mdash;'; ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<h2><?php esc_html_e( 'Next number changes', 'wsform-submission-number' ); ?></h2>
	<?php if ( ! $args['log'] ) : ?>
		<p><?php esc_html_e( 'No change yet.', 'wsform-submission-number' ); ?></p>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Date', 'wsform-submission-number' ); ?></th>
					<th scope="col"><?php esc_html_e( 'User', 'wsform-submission-number' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Sequence', 'wsform-submission-number' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Period', 'wsform-submission-number' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Next number', 'wsform-submission-number' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $args['log'] as $wsform_submission_number_entry ) :
					$wsform_submission_number_user = get_userdata( $wsform_submission_number_entry['user_id'] );
					?>
					<tr>
						<td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $wsform_submission_number_entry['time'] ) ); ?></td>
						<td><?php echo esc_html( $wsform_submission_number_user ? $wsform_submission_number_user->display_name : '#' . $wsform_submission_number_entry['user_id'] ); ?></td>
						<td><?php echo esc_html( $wsform_submission_number_entry['sequence_name'] ); ?></td>
						<td><?php echo esc_html( 'all' === $wsform_submission_number_entry['period'] ? '' : $wsform_submission_number_entry['period'] ); ?></td>
						<td>
							<?php
							echo null === $wsform_submission_number_entry['old_next']
								/* translators: %d: next number. */
								? esc_html( sprintf( __( 'Created, starts at %d', 'wsform-submission-number' ), $wsform_submission_number_entry['new_next'] ) )
								/* translators: 1: next number before, 2: next number after. */
								: esc_html( sprintf( __( '%1$d → %2$d', 'wsform-submission-number' ), $wsform_submission_number_entry['old_next'], $wsform_submission_number_entry['new_next'] ) );
							?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
