<?php
/**
 * Protection of numbers against admin edits.
 *
 * @package WSForm_Submission_Number
 */

namespace WSForm_Submission_Number;

use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keeps a given number when an admin saves an edited submission.
 *
 * WS Form hides non-editable fields from the edit form in its admin script,
 * but its server accepts any field value. The meta write filter only gets a
 * field ID, so the submission ID is read from the REST route beforehand.
 */
class Edit_Guard {

	/**
	 * Route of the admin "save submission" request.
	 */
	const EDIT_ROUTE = '#^/ws-form/v1/submit/(\d+)/put/?$#';

	/**
	 * Submission being edited in this request, 0 otherwise.
	 *
	 * @var int
	 */
	private $edited_submit_id = 0;

	/**
	 * Hook into the REST API and WS Form.
	 *
	 * @return void
	 */
	public function boot() {
		add_filter( 'rest_request_before_callbacks', array( $this, 'watch_edit_request' ), 10, 3 );
		add_filter( 'wsf_submit_meta_update', array( $this, 'keep_number' ), 10, 2 );
	}

	/**
	 * Remember which submission an admin edit request targets.
	 *
	 * @param mixed           $response Response so far, returned untouched.
	 * @param array           $handler  Route handler.
	 * @param WP_REST_Request $request  Request.
	 *
	 * @return mixed
	 */
	public function watch_edit_request( $response, $handler, $request ) {
		if ( preg_match( self::EDIT_ROUTE, $request->get_route(), $matches ) ) {
			$this->edited_submit_id = (int) $matches[1];
		}

		return $response;
	}

	/**
	 * Put the recorded number back when an edit tries to change it.
	 *
	 * @param mixed $meta_value Value about to be written.
	 * @param int   $field_id   Field ID.
	 *
	 * @return mixed
	 */
	public function keep_number( $meta_value, $field_id ) {
		if ( $this->edited_submit_id < 1 ) {
			return $meta_value;
		}

		$assignment = Assignments::find( $this->edited_submit_id, (int) $field_id );

		return $assignment ? $assignment['formatted'] : $meta_value;
	}
}
