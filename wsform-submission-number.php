<?php
/**
 * Plugin Name:       Submission Numbers for WS Form
 * Plugin URI:        https://github.com/willybahuaud/wsform-submission-number
 * Description:       Gives each WS Form submission a readable, continuous number, such as DEV-2026-0042, from sequences shared or not between forms.
 * Version:           0.1.0
 * Author:            Willy Bahuaud
 * Author URI:        https://wabeo.fr/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wsform-submission-number
 * Domain Path:       /languages
 * Requires PHP:      7.4
 * Requires at least: 6.0
 * Update URI:        https://github.com/willybahuaud/wsform-submission-number
 *
 * @package WSForm_Submission_Number
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WSFORM_SUBMISSION_NUMBER_FILE', __FILE__ );
define( 'WSFORM_SUBMISSION_NUMBER_PATH', plugin_dir_path( __FILE__ ) );

require_once WSFORM_SUBMISSION_NUMBER_PATH . 'includes/class-autoloader.php';
require_once WSFORM_SUBMISSION_NUMBER_PATH . 'includes/functions.php';
WSForm_Submission_Number\Autoloader::register();

register_activation_hook( __FILE__, array( 'WSForm_Submission_Number\Schema', 'install' ) );

add_action( 'plugins_loaded', 'wsform_submission_number_bootstrap' );
add_action( 'init', 'wsform_submission_number_load_textdomain', 0 );
add_action( 'init', 'wsform_submission_number_boot_updater' );

/**
 * Load the interface translations.
 *
 * @return void
 */
function wsform_submission_number_load_textdomain() {
	load_plugin_textdomain( 'wsform-submission-number', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

/**
 * Plug GitHub releases into plugin updates.
 *
 * Booted even without WS Form, so that a broken install can still update.
 *
 * @return void
 */
function wsform_submission_number_boot_updater() {
	$updater = new WSForm_Submission_Number\Updater\Updater(
		'willybahuaud',
		'wsform-submission-number',
		WSFORM_SUBMISSION_NUMBER_FILE,
		WSForm_Submission_Number\Plugin::VERSION,
		__( 'Submission Numbers for WS Form', 'wsform-submission-number' )
	);
	$updater->boot();
}

/**
 * Start the plugin when WS Form, LITE or PRO, is active.
 *
 * @return void
 */
function wsform_submission_number_bootstrap() {
	if ( ! class_exists( 'WS_Form_Submit' ) ) {
		add_action( 'admin_notices', 'wsform_submission_number_missing_ws_form_notice' );
		return;
	}

	WSForm_Submission_Number\Plugin::boot();
}

/**
 * Tell administrators that WS Form is required.
 *
 * @return void
 */
function wsform_submission_number_missing_ws_form_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	printf(
		'<div class="notice notice-error"><p>%s</p></div>',
		esc_html__( 'Submission Numbers for WS Form needs WS Form, LITE or PRO, to work.', 'wsform-submission-number' )
	);
}
