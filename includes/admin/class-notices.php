<?php
/**
 * Messages of the sequences screen.
 *
 * @package WSForm_Submission_Number
 */

namespace WSForm_Submission_Number\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Carries the outcome of a save through the redirect, as short codes.
 */
class Notices {

	/**
	 * Codes to put in the redirect URL.
	 *
	 * @param string[] $errors  Error codes.
	 * @param string[] $notices Notice codes.
	 * @param bool     $saved   Whether the sequence was saved.
	 *
	 * @return array Query arguments.
	 */
	public static function to_query( $errors, $notices, $saved ) {
		$query_args = array();

		if ( $saved ) {
			$query_args['saved'] = '1';
		}
		if ( $errors ) {
			$query_args['errors'] = implode( ',', $errors );
		}
		if ( $notices ) {
			$query_args['notices'] = implode( ',', $notices );
		}

		return $query_args;
	}

	/**
	 * Messages to show, read back from the URL.
	 *
	 * @param array $query Query string values.
	 *
	 * @return array[] Items with type (success, warning, error) and message.
	 */
	public static function from_query( $query ) {
		$items = array();

		if ( ! empty( $query['saved'] ) ) {
			$items[] = self::item( 'success', __( 'Sequence saved.', 'wsform-submission-number' ) );
		}
		if ( ! empty( $query['deleted'] ) ) {
			$items[] = self::item( 'success', __( 'Sequence deleted.', 'wsform-submission-number' ) );
		}

		foreach ( self::codes( $query, 'errors' ) as $code ) {
			$items[] = self::item( 'error', self::message( $code ) );
		}
		foreach ( self::codes( $query, 'notices' ) as $code ) {
			$items[] = self::item( 'warning', self::message( $code ) );
		}

		return array_values( array_filter( $items, array( __CLASS__, 'has_message' ) ) );
	}

	/**
	 * Text of a code.
	 *
	 * @param string $code Error or notice code.
	 *
	 * @return string Empty for an unknown code.
	 */
	public static function message( $code ) {
		$messages = array(
			'missing_name'   => __( 'Give the sequence a name.', 'wsform-submission-number' ),
			'missing_number' => __( 'The format needs {N}, the number itself.', 'wsform-submission-number' ),
			'missing_year'   => __( 'A sequence that resets every year or month needs the year in its format, {YYYY} or {YY}, or the same number would come back.', 'wsform-submission-number' ),
			'missing_month'  => __( 'A sequence that resets every month needs {MM} in its format, or the same number would come back.', 'wsform-submission-number' ),
			'reset_locked'   => __( 'This sequence already gave numbers: its reset rule can no longer change. Create a new sequence instead.', 'wsform-submission-number' ),
			'next_too_low'   => __( 'The next number must be higher than the last number given in this period, or the same number would be given twice.', 'wsform-submission-number' ),
			'gap'            => __( 'The next number skips some values: those numbers will never be given.', 'wsform-submission-number' ),
		);

		return isset( $messages[ $code ] ) ? $messages[ $code ] : '';
	}

	/**
	 * Codes listed in a query argument.
	 *
	 * @param array  $query Query string values.
	 * @param string $key   Argument name.
	 *
	 * @return string[]
	 */
	private static function codes( $query, $key ) {
		if ( empty( $query[ $key ] ) || ! is_string( $query[ $key ] ) ) {
			return array();
		}

		return array_map( 'sanitize_key', explode( ',', $query[ $key ] ) );
	}

	/**
	 * Build a notice item.
	 *
	 * @param string $type    success, warning or error.
	 * @param string $message Text.
	 *
	 * @return array
	 */
	private static function item( $type, $message ) {
		return array(
			'type'    => $type,
			'message' => $message,
		);
	}

	/**
	 * Filter callback dropping unknown codes.
	 *
	 * @param array $item Notice item.
	 *
	 * @return bool
	 */
	public static function has_message( $item ) {
		return '' !== $item['message'];
	}
}
