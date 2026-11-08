<?php
/**
 * Tests of the number format and the reset periods.
 *
 * @package WSForm_Submission_Number\Tests
 */

use PHPUnit\Framework\TestCase;
use WSForm_Submission_Number\Format;

/**
 * Format::render(), Format::period_key() and Format::find_problems().
 */
class FormatTest extends TestCase {

	/**
	 * A date in Paris, the day the plugin was started.
	 *
	 * @return DateTimeImmutable
	 */
	private function date() {
		return new DateTimeImmutable( '2026-11-08 15:30:00', new DateTimeZone( 'Europe/Paris' ) );
	}

	/**
	 * A typical quote number.
	 */
	public function test_renders_a_quote_number() {
		$this->assertSame( 'DEV-2026-0042', Format::render( 'DEV-{YYYY}-{N}', 42, 4, $this->date() ) );
	}

	/**
	 * Every date token.
	 */
	public function test_renders_date_tokens() {
		$this->assertSame( '26/11/08-7', Format::render( '{YY}/{MM}/{DD}-{N}', 7, 1, $this->date() ) );
	}

	/**
	 * {N:x} overrides the sequence padding.
	 */
	public function test_inline_padding_overrides_the_setting() {
		$this->assertSame( '000042', Format::render( '{N:6}', 42, 2, $this->date() ) );
	}

	/**
	 * A number longer than the padding is printed in full.
	 */
	public function test_never_cuts_a_long_number() {
		$this->assertSame( '123456', Format::render( '{N}', 123456, 4, $this->date() ) );
	}

	/**
	 * Unknown tokens and lowercase stay as typed, so the preview shows the typo.
	 */
	public function test_keeps_unknown_tokens() {
		$this->assertSame( '{n}-{YEAR}-0001', Format::render( '{n}-{YEAR}-{N}', 1, 4, $this->date() ) );
	}

	/**
	 * Padding is kept within bounds.
	 */
	public function test_sanitizes_digits() {
		$this->assertSame( 1, Format::sanitize_digits( 0 ) );
		$this->assertSame( 12, Format::sanitize_digits( 99 ) );
		$this->assertSame( 5, Format::sanitize_digits( '5' ) );
	}

	/**
	 * Period keys of each reset rule.
	 */
	public function test_period_keys() {
		$this->assertSame( 'all', Format::period_key( Format::RESET_NEVER, $this->date() ) );
		$this->assertSame( '2026', Format::period_key( Format::RESET_YEARLY, $this->date() ) );
		$this->assertSame( '2026-11', Format::period_key( Format::RESET_MONTHLY, $this->date() ) );
	}

	/**
	 * New Year's Eve and New Year's Day fall in two periods.
	 */
	public function test_year_change_opens_a_new_period() {
		$paris    = new DateTimeZone( 'Europe/Paris' );
		$last_day = new DateTimeImmutable( '2026-12-31 23:59:59', $paris );
		$first    = new DateTimeImmutable( '2027-01-01 00:00:00', $paris );

		$this->assertNotSame( Format::period_key( Format::RESET_YEARLY, $last_day ), Format::period_key( Format::RESET_YEARLY, $first ) );
		$this->assertNotSame( Format::period_key( Format::RESET_MONTHLY, $last_day ), Format::period_key( Format::RESET_MONTHLY, $first ) );
		$this->assertSame( Format::period_key( Format::RESET_NEVER, $last_day ), Format::period_key( Format::RESET_NEVER, $first ) );
	}

	/**
	 * The period follows the given timezone, not UTC.
	 */
	public function test_period_follows_the_site_timezone() {
		$utc_evening = new DateTimeImmutable( '2026-12-31 23:30:00', new DateTimeZone( 'UTC' ) );
		$in_paris    = $utc_evening->setTimezone( new DateTimeZone( 'Europe/Paris' ) );

		$this->assertSame( '2027-01', Format::period_key( Format::RESET_MONTHLY, $in_paris ) );
	}

	/**
	 * Formats that would print the same number twice are refused.
	 */
	public function test_finds_format_problems() {
		$this->assertSame( array(), Format::find_problems( '{N}', Format::RESET_NEVER ) );
		$this->assertSame( array( 'missing_number' ), Format::find_problems( 'DEV', Format::RESET_NEVER ) );
		$this->assertSame( array( 'missing_year' ), Format::find_problems( 'DEV-{N}', Format::RESET_YEARLY ) );
		$this->assertSame( array( 'missing_month' ), Format::find_problems( '{YYYY}-{N}', Format::RESET_MONTHLY ) );
		$this->assertSame( array( 'missing_year', 'missing_month' ), Format::find_problems( '{N}', Format::RESET_MONTHLY ) );
		$this->assertSame( array(), Format::find_problems( '{YY}{MM}-{N:3}', Format::RESET_MONTHLY ) );
	}
}
