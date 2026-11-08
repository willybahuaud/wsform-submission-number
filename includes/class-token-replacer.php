<?php
/**
 * Replacement of a single format token.
 *
 * @package WSForm_Submission_Number
 */

namespace WSForm_Submission_Number;

use DateTimeInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Holds the values a format needs, so preg_replace_callback gets a named method.
 */
class Token_Replacer {

	/**
	 * Counter value.
	 *
	 * @var int
	 */
	private $number;

	/**
	 * Default padding of {N}.
	 *
	 * @var int
	 */
	private $digits;

	/**
	 * Assignment date.
	 *
	 * @var DateTimeInterface
	 */
	private $date;

	/**
	 * Constructor.
	 *
	 * @param int               $number Counter value.
	 * @param int               $digits Default padding of {N}.
	 * @param DateTimeInterface $date   Assignment date.
	 */
	public function __construct( $number, $digits, DateTimeInterface $date ) {
		$this->number = $number;
		$this->digits = Format::sanitize_digits( $digits );
		$this->date   = $date;
	}

	/**
	 * Replace one token matched by Format::TOKEN_PATTERN.
	 *
	 * @param string[] $matches Full token, name, and the optional {N:x} padding.
	 *
	 * @return string
	 */
	public function replace( $matches ) {
		switch ( $matches[1] ) {
			case 'YYYY':
				return $this->date->format( 'Y' );
			case 'YY':
				return $this->date->format( 'y' );
			case 'MM':
				return $this->date->format( 'm' );
			case 'DD':
				return $this->date->format( 'd' );
		}

		$digits = isset( $matches[2] ) && '' !== $matches[2] ? Format::sanitize_digits( $matches[2] ) : $this->digits;

		// A number longer than the padding is printed in full, never cut.
		return str_pad( (string) $this->number, $digits, '0', STR_PAD_LEFT );
	}
}
