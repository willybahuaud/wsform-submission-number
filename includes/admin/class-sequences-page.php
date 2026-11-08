<?php
/**
 * The "WS Form > Numbering" screen.
 *
 * @package WSForm_Submission_Number
 */

namespace WSForm_Submission_Number\Admin;

use WSForm_Submission_Number\Change_Log;
use WSForm_Submission_Number\Plugin;
use WSForm_Submission_Number\Sequences;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lists sequences and edits one at a time.
 *
 * A page of its own rather than a tab of WS Form > Settings: a list with a
 * live preview, a guarded counter and a change log does not fit settings
 * made of single values.
 */
class Sequences_Page {

	const SLUG = 'wsform-submission-number';

	const CAPABILITY = 'manage_options';

	const SAVE_ACTION = 'wsform_submission_number_save';

	const DELETE_ACTION = 'wsform_submission_number_delete';

	/**
	 * Hook into the admin.
	 *
	 * @return void
	 */
	public function boot() {
		// After WS Form's own submenus.
		add_action( 'admin_menu', array( $this, 'add_page' ), 100 );
		add_action( 'admin_post_' . self::SAVE_ACTION, array( $this, 'handle_save' ) );
		add_action( 'admin_post_' . self::DELETE_ACTION, array( $this, 'handle_delete' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Add the page under the WS Form menu.
	 *
	 * @return void
	 */
	public function add_page() {
		add_submenu_page(
			'ws-form',
			__( 'Submission Numbers', 'wsform-submission-number' ),
			__( 'Numbering', 'wsform-submission-number' ),
			self::CAPABILITY,
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Load the preview script on this page only.
	 *
	 * @param string $hook_suffix Current admin page.
	 *
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( false === strpos( $hook_suffix, self::SLUG ) ) {
			return;
		}

		wp_enqueue_style( 'wsform-submission-number-admin', Plugin::get_url( 'assets/css/admin.css' ), array(), Plugin::VERSION );
		wp_enqueue_script( 'wsform-submission-number-admin', Plugin::get_url( 'assets/js/admin.js' ), array(), Plugin::VERSION, true );
		wp_localize_script(
			'wsform-submission-number-admin',
			'wsformSubmissionNumberAdmin',
			array(
				'confirmDelete' => __( 'Delete this sequence? Numbers already given stay on their submissions, and the forms using it switch to their own sequence.', 'wsform-submission-number' ),
			)
		);
	}

	/**
	 * Show the list, or the edit form when a sequence is asked for.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Display only.
		$sequence_id = isset( $_GET['sequence'] ) ? sanitize_key( wp_unslash( $_GET['sequence'] ) ) : '';
		$notices     = Notices::from_query( $_GET );
		// phpcs:enable

		$fields = Usage::fields();
		$usage  = Usage::map( $fields );

		if ( '' !== $sequence_id ) {
			$this->render_edit( $sequence_id, $usage, $notices );
			return;
		}

		load_template(
			WSFORM_SUBMISSION_NUMBER_PATH . 'templates/sequences-list.php',
			false,
			array(
				'sequences'      => Sequences::all(),
				'usage'          => $usage,
				'without_saving' => Usage::forms_without_saving( $fields ),
				'log'            => array_slice( Change_Log::entries(), 0, 30 ),
				'notices'        => $notices,
				'page_url'       => self::url(),
			)
		);
	}

	/**
	 * Show the edit form of one sequence, or of a new shared one.
	 *
	 * @param string $sequence_id Sequence ID, or "new".
	 * @param array  $usage       Result of Usage::map().
	 * @param array  $notices     Notices to show.
	 *
	 * @return void
	 */
	private function render_edit( $sequence_id, $usage, $notices ) {
		$sequence = 'new' === $sequence_id ? null : Sequences::get( $sequence_id );

		if ( 'new' !== $sequence_id && ! $sequence ) {
			wp_safe_redirect( self::url() );
			exit;
		}

		$draft = get_transient( self::draft_key() );
		delete_transient( self::draft_key() );

		load_template(
			WSFORM_SUBMISSION_NUMBER_PATH . 'templates/sequence-edit.php',
			false,
			array(
				'sequence' => $sequence,
				'draft'    => is_array( $draft ) ? $draft : array(),
				'forms'    => $sequence && isset( $usage['by_sequence'][ $sequence['id'] ] ) ? $usage['by_sequence'][ $sequence['id'] ] : array(),
				'notices'  => $notices,
				'page_url' => self::url(),
			)
		);
	}

	/**
	 * Save a sequence, then come back with the outcome.
	 *
	 * @return void
	 */
	public function handle_save() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to manage sequences.', 'wsform-submission-number' ), 403 );
		}

		check_admin_referer( self::SAVE_ACTION );

		$input  = isset( $_POST['sequence'] ) && is_array( $_POST['sequence'] ) ? wp_unslash( $_POST['sequence'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized by Sequences::normalize().
		$result = Sequence_Editor::save( $input );

		$sequence_id = $result['sequence'] ? $result['sequence']['id'] : ( empty( $input['id'] ) ? 'new' : sanitize_key( $input['id'] ) );
		$query_args  = Notices::to_query( $result['errors'], $result['notices'], ! $result['errors'] );

		// Keep what was typed when the save failed, so nothing has to be typed again.
		if ( $result['errors'] ) {
			set_transient( self::draft_key(), array_map( 'sanitize_text_field', $input ), MINUTE_IN_SECONDS );
		}

		wp_safe_redirect( add_query_arg( $query_args, self::url( $sequence_id ) ) );
		exit;
	}

	/**
	 * Delete a shared sequence.
	 *
	 * @return void
	 */
	public function handle_delete() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to manage sequences.', 'wsform-submission-number' ), 403 );
		}

		$sequence_id = isset( $_GET['sequence'] ) ? sanitize_key( wp_unslash( $_GET['sequence'] ) ) : '';
		check_admin_referer( self::DELETE_ACTION . '_' . $sequence_id );

		$sequence = Sequences::get( $sequence_id );
		if ( $sequence && empty( $sequence['field_id'] ) ) {
			Sequences::delete( $sequence_id );
		}

		wp_safe_redirect( add_query_arg( 'deleted', '1', self::url() ) );
		exit;
	}

	/**
	 * Transient holding the values of a failed save, per user.
	 *
	 * @return string
	 */
	private static function draft_key() {
		return 'wsform_submission_number_draft_' . get_current_user_id();
	}

	/**
	 * URL of the page, or of one sequence.
	 *
	 * @param string $sequence_id Sequence ID, "new", or empty for the list.
	 *
	 * @return string
	 */
	public static function url( $sequence_id = '' ) {
		$args = array( 'page' => self::SLUG );
		if ( '' !== $sequence_id ) {
			$args['sequence'] = $sequence_id;
		}

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}
}
