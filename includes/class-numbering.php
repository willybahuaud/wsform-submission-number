<?php
/**
 * Numbers of a sequence.
 *
 * @package WSForm_Submission_Number
 */

namespace WSForm_Submission_Number;

use DateTimeImmutable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Glues a sequence's settings to its counter.
 */
class Numbering {

	/**
	 * Take the next number of a sequence.
	 *
	 * @param array             $sequence Sequence.
	 * @param DateTimeImmutable $date     Assignment date, in the site timezone.
	 *
	 * @return array|null Keys number, period and formatted, or null when the database failed.
	 */
	public static function take( $sequence, DateTimeImmutable $date ) {
		$period = Format::period_key( $sequence['reset'], $date );
		$number = Counter::next( $sequence['id'], $period, $sequence['period_start'] );

		if ( $number < 1 ) {
			return null;
		}

		return array(
			'number'    => $number,
			'period'    => $period,
			'formatted' => Format::render( $sequence['format'], $number, $sequence['digits'], $date ),
		);
	}

	/**
	 * What the current period looks like: last number given and next one.
	 *
	 * @param array                  $sequence Sequence.
	 * @param DateTimeImmutable|null $date     Date to look at, now by default.
	 *
	 * @return array Keys period, last (int|null), next, next_formatted, last_formatted.
	 */
	public static function status( $sequence, $date = null ) {
		$date   = $date ? $date : self::now();
		$period = Format::period_key( $sequence['reset'], $date );
		$last   = Counter::last( $sequence['id'], $period );
		$next   = null === $last ? (int) $sequence['period_start'] : $last + 1;

		return array(
			'period'         => $period,
			'last'           => $last,
			'last_formatted' => null === $last ? '' : Format::render( $sequence['format'], $last, $sequence['digits'], $date ),
			'next'           => $next,
			'next_formatted' => Format::render( $sequence['format'], $next, $sequence['digits'], $date ),
		);
	}

	/**
	 * Current date in the site timezone, which decides the year and month of a number.
	 *
	 * @return DateTimeImmutable
	 */
	public static function now() {
		return new DateTimeImmutable( 'now', wp_timezone() );
	}
}
