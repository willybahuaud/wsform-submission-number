<?php
/**
 * Edit form of a sequence.
 *
 * @package WSForm_Submission_Number
 *
 * @var array $args Keys: sequence (array|null for a new one), draft, forms, notices, page_url.
 */

use WSForm_Submission_Number\Admin\Sequences_Page;
use WSForm_Submission_Number\Assignments;
use WSForm_Submission_Number\Numbering;
use WSForm_Submission_Number\Sequences;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wsform_submission_number_is_new   = null === $args['sequence'];
$wsform_submission_number_sequence = Sequences::normalize( array_merge( (array) $args['sequence'], $args['draft'] ) );
$wsform_submission_number_status   = Numbering::status( $wsform_submission_number_sequence );
$wsform_submission_number_locked   = ! $wsform_submission_number_is_new && Assignments::sequence_used( $wsform_submission_number_sequence['id'] );
$wsform_submission_number_next     = isset( $args['draft']['next_number'] ) ? $args['draft']['next_number'] : $wsform_submission_number_status['next'];
$wsform_submission_number_resets   = array(
	'never'   => __( 'Never', 'wsform-submission-number' ),
	'yearly'  => __( 'Every year', 'wsform-submission-number' ),
	'monthly' => __( 'Every month', 'wsform-submission-number' ),
);
?>
<div class="wrap wsform-submission-number">
	<h1><?php echo $wsform_submission_number_is_new ? esc_html__( 'New shared sequence', 'wsform-submission-number' ) : esc_html( $wsform_submission_number_sequence['name'] ); ?></h1>
	<p><a href="<?php echo esc_url( $args['page_url'] ); ?>">&larr; <?php esc_html_e( 'All sequences', 'wsform-submission-number' ); ?></a></p>

	<?php load_template( WSFORM_SUBMISSION_NUMBER_PATH . 'templates/notices.php', false, array( 'notices' => $args['notices'] ) ); ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wsform-submission-number-editor"
		data-last="<?php echo esc_attr( null === $wsform_submission_number_status['last'] ? '' : $wsform_submission_number_status['last'] ); ?>"
		data-today="<?php echo esc_attr( wp_date( 'Y-m-d' ) ); ?>">
		<input type="hidden" name="action" value="<?php echo esc_attr( Sequences_Page::SAVE_ACTION ); ?>">
		<input type="hidden" name="sequence[id]" value="<?php echo esc_attr( $wsform_submission_number_is_new ? '' : $wsform_submission_number_sequence['id'] ); ?>">
		<?php wp_nonce_field( Sequences_Page::SAVE_ACTION ); ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="wsfsn-name"><?php esc_html_e( 'Name', 'wsform-submission-number' ); ?></label></th>
				<td><input type="text" id="wsfsn-name" name="sequence[name]" class="regular-text" required value="<?php echo esc_attr( $wsform_submission_number_sequence['name'] ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><label for="wsfsn-format"><?php esc_html_e( 'Format', 'wsform-submission-number' ); ?></label></th>
				<td>
					<input type="text" id="wsfsn-format" name="sequence[format]" class="regular-text code" required value="<?php echo esc_attr( $wsform_submission_number_sequence['format'] ); ?>" data-wsfsn-preview-source>
					<p class="description">
						<?php
						printf(
							/* translators: 1: number token, 2: number token with padding, 3: date tokens, 4: example format. */
							esc_html__( '%1$s is the number, padded to the digits below, %2$s pads it to 6 digits. %3$s are the year, short year, month and day the number is given. Anything else stays as typed, for example %4$s.', 'wsform-submission-number' ),
							'<code>{N}</code>',
							'<code>{N:6}</code>',
							'<code>{YYYY}</code> <code>{YY}</code> <code>{MM}</code> <code>{DD}</code>',
							'<code>DEV-{YYYY}-{N}</code>'
						);
						?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wsfsn-digits"><?php esc_html_e( 'Digits', 'wsform-submission-number' ); ?></label></th>
				<td>
					<input type="number" id="wsfsn-digits" name="sequence[digits]" class="small-text" min="1" max="12" value="<?php echo esc_attr( $wsform_submission_number_sequence['digits'] ); ?>" data-wsfsn-preview-source>
					<p class="description"><?php esc_html_e( 'Zeros added in front of {N}: 4 gives 0042. A longer number is never cut.', 'wsform-submission-number' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wsfsn-reset"><?php esc_html_e( 'Reset', 'wsform-submission-number' ); ?></label></th>
				<td>
					<select id="wsfsn-reset" name="sequence[reset]" <?php disabled( $wsform_submission_number_locked ); ?>>
						<?php foreach ( $wsform_submission_number_resets as $wsform_submission_number_value => $wsform_submission_number_label ) : ?>
							<option value="<?php echo esc_attr( $wsform_submission_number_value ); ?>" <?php selected( $wsform_submission_number_sequence['reset'], $wsform_submission_number_value ); ?>><?php echo esc_html( $wsform_submission_number_label ); ?></option>
						<?php endforeach; ?>
					</select>
					<?php if ( $wsform_submission_number_locked ) : ?>
						<input type="hidden" name="sequence[reset]" value="<?php echo esc_attr( $wsform_submission_number_sequence['reset'] ); ?>">
						<p class="description"><?php esc_html_e( 'This sequence already gave numbers: its reset rule is locked.', 'wsform-submission-number' ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
			<tr data-wsfsn-period-start>
				<th scope="row"><label for="wsfsn-period-start"><?php esc_html_e( 'First number of each new period', 'wsform-submission-number' ); ?></label></th>
				<td><input type="number" id="wsfsn-period-start" name="sequence[period_start]" class="small-text" min="1" value="<?php echo esc_attr( $wsform_submission_number_sequence['period_start'] ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><label for="wsfsn-next"><?php esc_html_e( 'Next number', 'wsform-submission-number' ); ?></label></th>
				<td>
					<input type="number" id="wsfsn-next" name="sequence[next_number]" class="small-text" min="1" value="<?php echo esc_attr( $wsform_submission_number_next ); ?>" data-wsfsn-preview-source>
					<p class="description">
						<?php
						if ( null === $wsform_submission_number_status['last'] ) {
							esc_html_e( 'No number given yet in the current period. Taking over from another tool? Enter the number to continue from.', 'wsform-submission-number' );
						} else {
							printf(
								/* translators: %s: last number given. */
								esc_html__( 'Last number given in the current period: %s. The next number can only go up.', 'wsform-submission-number' ),
								'<code>' . esc_html( $wsform_submission_number_status['last_formatted'] ) . '</code>'
							);
						}
						?>
					</p>
					<p class="wsfsn-gap-warning" hidden><?php esc_html_e( 'This skips some numbers: they will never be given.', 'wsform-submission-number' ); ?></p>
					<p class="wsfsn-too-low" hidden><?php esc_html_e( 'Too low: this number was already given.', 'wsform-submission-number' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Preview', 'wsform-submission-number' ); ?></th>
				<td><code class="wsfsn-preview"><?php echo esc_html( $wsform_submission_number_status['next_formatted'] ); ?></code></td>
			</tr>
			<?php if ( ! $wsform_submission_number_is_new ) : ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Used by', 'wsform-submission-number' ); ?></th>
					<td><?php echo $args['forms'] ? esc_html( implode( ', ', $args['forms'] ) ) : esc_html__( 'No form yet.', 'wsform-submission-number' ); ?></td>
				</tr>
			<?php endif; ?>
		</table>

		<?php submit_button( __( 'Save the sequence', 'wsform-submission-number' ) ); ?>
	</form>

	<?php if ( ! $wsform_submission_number_is_new && empty( $wsform_submission_number_sequence['field_id'] ) ) : ?>
		<p>
			<a class="wsfsn-delete submitdelete" href="
			<?php
			echo esc_url(
				wp_nonce_url(
					add_query_arg(
						array(
							'action'   => Sequences_Page::DELETE_ACTION,
							'sequence' => $wsform_submission_number_sequence['id'],
						),
						admin_url( 'admin-post.php' )
					),
					Sequences_Page::DELETE_ACTION . '_' . $wsform_submission_number_sequence['id']
				)
			);
			?>
														"><?php esc_html_e( 'Delete this sequence', 'wsform-submission-number' ); ?></a>
		</p>
	<?php endif; ?>
</div>
