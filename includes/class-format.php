<?php
/**
 * Number format and reset periods.
 *
 * @package WSForm_Submission_Number
 */

namespace WSForm_Submission_Number;

use DateTimeInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns a counter value into a readable number, such as DEV-2026-0042.
 *
 * Pure functions with no WordPress call, so they can be unit tested alone.
 */
class Format {

	const RESET_NEVER   = 'never';
	const RESET_YEARLY  = 'yearly';
	const RESET_MONTHLY = 'monthly';

	/**
	 * Period key of sequences that never reset.
	 */
	const PERIOD_ALL = 'all';

	/**
	 * Highest padding accepted, for {N:x} and the sequence setting.
	 */
	const MAX_DIGITS = 12;

	/**
	 * Tokens: {N}, {N:6}, {YYYY}, {YY}, {MM}, {DD}.
	 */
	const TOKEN_PATTERN = '/\{(N(?::(\d{1,2}))?|YYYY|YY|MM|DD)\}/';

	/**
	 * Build the readable number.
	 *
	 * Unknown tokens stay as typed, so a typo shows up in the preview.
	 *
	 * @param string            $pattern Format, such as "DEV-{YYYY}-{N}".
	 * @param int               $number  Counter value.
	 * @param int               $digits  Padding used by {N}.
	 * @param DateTimeInterface $date    Assignment date, in the site timezone.
	 *
	 * @return string
	 */
	public static function render( $pattern, $number, $digits, DateTimeInterface $date ) {
		$replacer = new Token_Replacer( (int) $number, (int) $digits, $date );

		return (string) preg_replace_callback( self::TOKEN_PATTERN, array( $replacer, 'replace' ), $pattern );
	}

	/**
	 * Key of the counter period a date belongs to.
	 *
	 * Putting the period in the counter key resets the numbering without any
	 * scheduled task: the first number of a new month simply opens a new row.
	 *
	 * @param string            $reset Reset rule.
	 * @param DateTimeInterface $date  Assignment date, in the site timezone.
	 *
	 * @return string "all", "2026" or "2026-11".
	 */
	public static function period_key( $reset, DateTimeInterface $date ) {
		if ( self::RESET_YEARLY === $reset ) {
			return $date->format( 'Y' );
		}

		if ( self::RESET_MONTHLY === $reset ) {
			return $date->format( 'Y-m' );
		}

		return self::PERIOD_ALL;
	}

	/**
	 * List what makes a format unusable with a reset rule.
	 *
	 * A yearly reset without the year in the format would print the same
	 * number twice, one year apart.
	 *
	 * @param string $pattern Format.
	 * @param string $reset   Reset rule.
	 *
	 * @return string[] Problem codes: missing_number, missing_year, missing_month.
	 */
	public static function find_problems( $pattern, $reset ) {
		$problems = array();

		if ( ! preg_match( '/\{N(?::\d{1,2})?\}/', $pattern ) ) {
			$problems[] = 'missing_number';
		}

		$has_year = false !== strpos( $pattern, '{YYYY}' ) || false !== strpos( $pattern, '{YY}' );

		if ( self::RESET_NEVER !== $reset && ! $has_year ) {
			$problems[] = 'missing_year';
		}

		if ( self::RESET_MONTHLY === $reset && false === strpos( $pattern, '{MM}' ) ) {
			$problems[] = 'missing_month';
		}

		return $problems;
	}

	/**
	 * Known reset rules.
	 *
	 * @return string[]
	 */
	public static function reset_rules() {
		return array( self::RESET_NEVER, self::RESET_YEARLY, self::RESET_MONTHLY );
	}

	/**
	 * Keep a padding value within bounds.
	 *
	 * @param mixed $digits Raw value.
	 *
	 * @return int
	 */
	public static function sanitize_digits( $digits ) {
		return max( 1, min( self::MAX_DIGITS, (int) $digits ) );
	}
}
