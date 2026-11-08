<?php
/**
 * The README example, word for word: number paid forms once the payment is confirmed.
 *
 * The run.php runner checks that README.md still contains this code.
 *
 * @package WSForm_Submission_Number\Tests
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- A theme's own prefix, as in the README.

add_filter( 'wsform_submission_number_assign_now', 'mysite_wait_for_payment', 10, 2 );
add_action( 'wsform_monetico_payment_updated', 'mysite_number_on_payment', 10, 2 );

/**
 * Les formulaires avec un paiement Monetico attendent le paiement.
 *
 * @param bool            $assign_now Numéroter maintenant ou pas.
 * @param \WS_Form_Submit $submit     Soumission.
 * @return bool
 */
function mysite_wait_for_payment( $assign_now, $submit ) {
	if ( false !== WS_Form_Action::get_form_action_config( $submit->form_object, 'monetico_payment' ) ) {
		return false;
	}

	return $assign_now;
}

/**
 * Donne le numéro quand Monetico confirme le paiement.
 *
 * @param int    $submission_id ID de la soumission WS Form.
 * @param string $new_status    "paid" ou "failed".
 * @return void
 */
function mysite_number_on_payment( $submission_id, $new_status ) {
	if ( 'paid' === $new_status ) {
		wsform_submission_number_assign( $submission_id );
	}
}
