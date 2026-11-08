<?php
/**
 * Plugin bootstrap.
 *
 * @package WSForm_Submission_Number
 */

namespace WSForm_Submission_Number;

use WSForm_Submission_Number\Admin\Sequences_Page;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Starts the plugin components.
 */
class Plugin {

	const VERSION = '0.1.0';

	/**
	 * Start every component.
	 *
	 * Runs on plugins_loaded, once WS Form is known to be active.
	 *
	 * @return void
	 */
	public static function boot() {
		// Before anything reads the tables, including on the front end after a file-copy update.
		Schema::maybe_install();

		$components = array(
			new Field_Type(),
			new Assigner(),
			new Edit_Guard(),
			new Variable(),
			new Form_Sync(),
		);

		if ( is_admin() ) {
			$components[] = new Sequences_Page();
		}

		foreach ( $components as $component ) {
			$component->boot();
		}
	}

	/**
	 * Get the URL of a plugin file.
	 *
	 * @param string $relative_path Path from the plugin folder.
	 *
	 * @return string
	 */
	public static function get_url( $relative_path ) {
		return plugins_url( $relative_path, WSFORM_SUBMISSION_NUMBER_FILE );
	}
}
