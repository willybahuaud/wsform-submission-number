<?php
/**
 * Sequence settings.
 *
 * @package WSForm_Submission_Number
 */

namespace WSForm_Submission_Number;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores sequence settings in one option.
 *
 * A sequence is an array with these keys:
 * - id:           "shared-…" or "field-{field ID}"
 * - name:         label shown in the admin
 * - format:       such as "DEV-{YYYY}-{N}"
 * - digits:       padding of {N}
 * - reset:        never, yearly or monthly
 * - period_start: first number of each new period
 * - field_id:     owner field of a form's own sequence, 0 for a shared one
 *
 * Settings change rarely, so an option is fine here. The counters, which
 * change on every submission, live in their own table.
 */
class Sequences {

	const OPTION = 'wsform_submission_number_sequences';

	/**
	 * Field setting value meaning "a sequence of its own".
	 */
	const OWN = '';

	/**
	 * Every sequence, by ID.
	 *
	 * @return array<string,array>
	 */
	public static function all() {
		$sequences = get_option( self::OPTION, array() );

		return is_array( $sequences ) ? $sequences : array();
	}

	/**
	 * Shared sequences only, by ID, sorted by name.
	 *
	 * @return array<string,array>
	 */
	public static function shared() {
		$shared = array();

		foreach ( self::all() as $sequence_id => $sequence ) {
			if ( empty( $sequence['field_id'] ) ) {
				$shared[ $sequence_id ] = $sequence;
			}
		}

		uasort( $shared, array( __CLASS__, 'compare_names' ) );

		return $shared;
	}

	/**
	 * One sequence.
	 *
	 * @param string $sequence_id Sequence ID.
	 *
	 * @return array|null
	 */
	public static function get( $sequence_id ) {
		$sequences = self::all();

		return isset( $sequences[ $sequence_id ] ) ? $sequences[ $sequence_id ] : null;
	}

	/**
	 * Create or replace a sequence.
	 *
	 * @param array $sequence Sequence, see the class description.
	 *
	 * @return array The stored sequence.
	 */
	public static function save( $sequence ) {
		$sequence = self::normalize( $sequence );

		$sequences                    = self::all();
		$sequences[ $sequence['id'] ] = $sequence;
		update_option( self::OPTION, $sequences, true );

		return $sequence;
	}

	/**
	 * Delete a sequence and its counters.
	 *
	 * Numbers already given stay on their submissions.
	 *
	 * @param string $sequence_id Sequence ID.
	 *
	 * @return void
	 */
	public static function delete( $sequence_id ) {
		$sequences = self::all();
		unset( $sequences[ $sequence_id ] );
		update_option( self::OPTION, $sequences, true );

		Counter::delete_sequence( $sequence_id );
	}

	/**
	 * Find the sequence a field uses, creating its own one when needed.
	 *
	 * A form's own sequence is tied to the field ID. A duplicated or imported
	 * form gets new field IDs, so it starts its own sequence instead of
	 * inheriting the original counter. A shared sequence missing on this site,
	 * after an import for instance, falls back to the field's own sequence.
	 *
	 * @param string $chosen_id  Sequence ID chosen in the field settings.
	 * @param int    $field_id   Field ID.
	 * @param string $form_label Form name, used to name a new own sequence.
	 *
	 * @return array
	 */
	public static function resolve_for_field( $chosen_id, $field_id, $form_label ) {
		if ( self::OWN !== $chosen_id ) {
			$chosen = self::get( $chosen_id );
			if ( $chosen ) {
				return $chosen;
			}
		}

		$own_id = self::own_id( $field_id );
		$own    = self::get( $own_id );
		if ( $own ) {
			return $own;
		}

		return self::save(
			array(
				'id'       => $own_id,
				/* translators: %s: form name. */
				'name'     => sprintf( __( '%s (own sequence)', 'wsform-submission-number' ), $form_label ),
				'field_id' => (int) $field_id,
			)
		);
	}

	/**
	 * ID of a field's own sequence.
	 *
	 * @param int $field_id Field ID.
	 *
	 * @return string
	 */
	public static function own_id( $field_id ) {
		return 'field-' . (int) $field_id;
	}

	/**
	 * New ID for a shared sequence.
	 *
	 * Random rather than incremental, so a form imported from another site
	 * never matches an unrelated sequence by chance.
	 *
	 * @return string
	 */
	public static function new_shared_id() {
		return 'shared-' . strtolower( wp_generate_password( 10, false ) );
	}

	/**
	 * Fill in missing keys and clean values.
	 *
	 * @param array $sequence Raw sequence.
	 *
	 * @return array
	 */
	public static function normalize( $sequence ) {
		$defaults = array(
			'id'           => self::new_shared_id(),
			'name'         => '',
			'format'       => '{N}',
			'digits'       => 4,
			'reset'        => Format::RESET_NEVER,
			'period_start' => 1,
			'field_id'     => 0,
		);
		$sequence = array_intersect_key( wp_parse_args( $sequence, $defaults ), $defaults );

		$sequence['id']           = sanitize_key( $sequence['id'] );
		$sequence['name']         = sanitize_text_field( $sequence['name'] );
		$sequence['format']       = sanitize_text_field( $sequence['format'] );
		$sequence['digits']       = Format::sanitize_digits( $sequence['digits'] );
		$sequence['period_start'] = max( 1, (int) $sequence['period_start'] );
		$sequence['field_id']     = (int) $sequence['field_id'];

		if ( ! in_array( $sequence['reset'], Format::reset_rules(), true ) ) {
			$sequence['reset'] = Format::RESET_NEVER;
		}

		return $sequence;
	}

	/**
	 * Sort callback on sequence names.
	 *
	 * @param array $first  Sequence.
	 * @param array $second Sequence.
	 *
	 * @return int
	 */
	public static function compare_names( $first, $second ) {
		return strnatcasecmp( $first['name'], $second['name'] );
	}
}
