<?php
/**
 * Helpers for integration tests run inside the lab site.
 *
 * @package WSForm_Submission_Number\Tests
 */

/**
 * Assertions, HTTP submissions and Mailpit reads shared by the scenarios.
 *
 * No PHPUnit here: WS Form cannot be installed by a test runner, so the
 * scenarios run with "wp eval-file" on the lab site. Submissions go through
 * real HTTP requests, because WS Form ends its REST answers with exit.
 */
class WSForm_Submission_Number_Lab_Test {

	/**
	 * Address of the site seen from the server itself.
	 *
	 * Defaults suit a ddev site: the web server and Mailpit answer on 127.0.0.1
	 * inside the container. Override them with environment variables.
	 *
	 * @return string
	 */
	public static function site_url() {
		return getenv( 'WSFSN_LAB_SITE_URL' ) ? getenv( 'WSFSN_LAB_SITE_URL' ) : 'http://127.0.0.1';
	}

	/**
	 * Host name the site answers to, taken from the site address by default.
	 *
	 * @return string
	 */
	public static function site_host() {
		return getenv( 'WSFSN_LAB_SITE_HOST' ) ? getenv( 'WSFSN_LAB_SITE_HOST' ) : (string) wp_parse_url( home_url(), PHP_URL_HOST );
	}

	/**
	 * Mailpit API address.
	 *
	 * @return string
	 */
	public static function mailpit_url() {
		return getenv( 'WSFSN_LAB_MAILPIT_URL' ) ? getenv( 'WSFSN_LAB_MAILPIT_URL' ) : 'http://127.0.0.1:8025/api/v1';
	}

	/**
	 * Failed assertions.
	 *
	 * @var string[]
	 */
	public static $failures = array();

	/**
	 * Number of assertions.
	 *
	 * @var int
	 */
	public static $assertions = 0;

	/**
	 * Check that two values are identical.
	 *
	 * @param mixed  $expected Expected value.
	 * @param mixed  $actual   Actual value.
	 * @param string $label    What is checked.
	 *
	 * @return void
	 */
	public static function same( $expected, $actual, $label ) {
		self::check( $expected === $actual, $label . ' (expected ' . wp_json_encode( $expected ) . ', got ' . wp_json_encode( $actual ) . ')' );
	}

	/**
	 * Check that a condition holds.
	 *
	 * @param bool   $condition Condition.
	 * @param string $label     What is checked.
	 *
	 * @return void
	 */
	public static function check( $condition, $label ) {
		++self::$assertions;

		if ( ! $condition ) {
			self::$failures[] = $label;
			WP_CLI::warning( 'FAIL ' . $label );
		}
	}

	/**
	 * Request arguments for one public submission.
	 *
	 * @param array  $form      Form from WSForm_Submission_Number_Lab_Forms::create().
	 * @param array  $overrides Extra or replaced POST values.
	 * @param string $scenario  X-Lab-Scenario header value.
	 *
	 * @return array URL, headers and body.
	 */
	public static function submission_request( $form, $overrides = array(), $scenario = 'plain' ) {
		$fields = $form['fields'];
		$body   = array(
			'wsf_form_id'   => $form['form_id'],
			'wsf_post_mode' => 'submit',
		);

		foreach ( $fields['text'] as $field_id ) {
			$body[ 'field_' . $field_id ] = 'Ada';
		}
		$body[ 'field_' . $fields['email'][0] ]    = 'ada@example.org';
		$body[ 'field_' . $fields['textarea'][0] ] = 'Lab inquiry';
		$body[ 'field_' . $fields['checkbox'][0] ] = array( 'I consent' );

		return array(
			'url'     => self::site_url() . '/wp-json/ws-form/v1/submit/',
			'headers' => array(
				'Host'           => self::site_host(),
				'X-Lab-Scenario' => $scenario,
			),
			'body'    => array_merge( $body, $overrides ),
		);
	}

	/**
	 * Send one public submission.
	 *
	 * @param array  $form      Form from WSForm_Submission_Number_Lab_Forms::create().
	 * @param array  $overrides Extra or replaced POST values.
	 * @param string $scenario  X-Lab-Scenario header value.
	 *
	 * @return array Keys code, json, submit_id (0 when nothing was saved).
	 */
	public static function submit( $form, $overrides = array(), $scenario = 'plain' ) {
		$request  = self::submission_request( $form, $overrides, $scenario );
		$response = wp_remote_post(
			$request['url'],
			array(
				'headers' => $request['headers'],
				'body'    => $request['body'],
				'timeout' => 30,
			)
		);

		return self::read_response( $response );
	}

	/**
	 * Send many public submissions at the same time.
	 *
	 * @param array $form  Form.
	 * @param int   $count Number of simultaneous requests.
	 *
	 * @return array[] Results, as submit() returns them.
	 */
	public static function submit_in_parallel( $form, $count ) {
		$requests = array();

		for ( $index = 0; $index < $count; $index++ ) {
			$request    = self::submission_request( $form );
			$requests[] = array(
				'url'     => $request['url'],
				'headers' => $request['headers'],
				'data'    => $request['body'],
				'type'    => 'POST',
			);
		}

		$responses = WpOrg\Requests\Requests::request_multiple( $requests, array( 'timeout' => 60 ) );
		$results   = array();

		foreach ( $responses as $response ) {
			$results[] = self::read_response(
				$response instanceof WpOrg\Requests\Response
					? array(
						'response' => array( 'code' => $response->status_code ),
						'body'     => $response->body,
					)
					: new WP_Error( 'request_failed', 'Request failed' )
			);
		}

		return $results;
	}

	/**
	 * Decode a submission answer and find the saved submission.
	 *
	 * @param array|WP_Error $response HTTP response.
	 *
	 * @return array Keys code, json, submit_id.
	 */
	private static function read_response( $response ) {
		if ( is_wp_error( $response ) ) {
			return array(
				'code'      => 0,
				'json'      => null,
				'submit_id' => 0,
			);
		}

		$json = json_decode( wp_remote_retrieve_body( $response ), true );
		$hash = isset( $json['hash'] ) ? $json['hash'] : '';

		return array(
			'code'      => (int) wp_remote_retrieve_response_code( $response ),
			'json'      => $json,
			'submit_id' => self::submit_id_by_hash( $hash ),
		);
	}

	/**
	 * Saved submission with a hash.
	 *
	 * @param string $hash Submission hash.
	 *
	 * @return int 0 when none was saved.
	 */
	public static function submit_id_by_hash( $hash ) {
		global $wpdb;

		if ( '' === $hash ) {
			return 0;
		}

		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}wsf_submit WHERE hash = %s", $hash ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Test.
	}

	/**
	 * Value WS Form saved for a field of a submission.
	 *
	 * @param int $submit_id Submission ID.
	 * @param int $field_id  Field ID.
	 *
	 * @return string|null
	 */
	public static function saved_value( $submit_id, $field_id ) {
		global $wpdb;

		return $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->prefix}wsf_submit_meta WHERE parent_id = %d AND meta_key = %s", $submit_id, 'field_' . $field_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Test.
	}

	/**
	 * Text of the newest email whose text contains a string.
	 *
	 * @param string $needle Text to look for.
	 *
	 * @return string Empty when no email matches.
	 */
	public static function find_email_text( $needle ) {
		$search = wp_remote_get( self::mailpit_url() . '/search?limit=5&query=' . rawurlencode( '"' . $needle . '"' ) );
		$found  = json_decode( wp_remote_retrieve_body( $search ), true );

		if ( empty( $found['messages'][0]['ID'] ) ) {
			return '';
		}

		$message = json_decode( wp_remote_retrieve_body( wp_remote_get( self::mailpit_url() . '/message/' . $found['messages'][0]['ID'] ) ), true );

		return isset( $message['Text'] ) ? $message['Text'] : '';
	}

	/**
	 * Print the outcome and stop.
	 *
	 * @return void
	 */
	public static function report() {
		if ( self::$failures ) {
			WP_CLI::error( count( self::$failures ) . ' of ' . self::$assertions . ' checks failed.' );
		}

		WP_CLI::success( self::$assertions . ' checks passed.' );
	}
}
