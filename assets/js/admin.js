/**
 * Sequences screen: live preview of the next number, and guard messages.
 *
 * Mirrors WSForm_Submission_Number\Format::render(). The server checks again
 * on save, so this only spares a round trip.
 *
 * @package WSForm_Submission_Number
 */

( function wsformSubmissionNumberAdmin() {
	'use strict';

	var TOKEN_PATTERN = /\{(N(?::(\d{1,2}))?|YYYY|YY|MM|DD)\}/g;
	var MAX_DIGITS = 12;

	/**
	 * Keep a padding value within bounds.
	 *
	 * @param {string|number} digits Raw value.
	 * @return {number} Padding.
	 */
	function sanitizeDigits( digits ) {
		return Math.max( 1, Math.min( MAX_DIGITS, parseInt( digits, 10 ) || 1 ) );
	}

	/**
	 * Pad a number with zeros, never cutting it.
	 *
	 * @param {number} number Number.
	 * @param {number} digits Padding.
	 * @return {string} Padded number.
	 */
	function padNumber( number, digits ) {
		var text = String( number );

		while ( text.length < digits ) {
			text = '0' + text;
		}

		return text;
	}

	/**
	 * Build the readable number.
	 *
	 * @param {string} pattern Format.
	 * @param {number} number  Counter value.
	 * @param {number} digits  Padding of {N}.
	 * @param {string} today   Site date, "YYYY-MM-DD".
	 * @return {string} Formatted number.
	 */
	function renderFormat( pattern, number, digits, today ) {
		var parts = today.split( '-' );

		/**
		 * Replace one token.
		 *
		 * @param {string} token   Full token.
		 * @param {string} name    Token name.
		 * @param {string} padding Optional {N:x} padding.
		 * @return {string} Replacement.
		 */
		function replaceToken( token, name, padding ) {
			switch ( name ) {
				case 'YYYY':
					return parts[ 0 ];
				case 'YY':
					return parts[ 0 ].slice( -2 );
				case 'MM':
					return parts[ 1 ];
				case 'DD':
					return parts[ 2 ];
			}

			return padNumber( number, padding ? sanitizeDigits( padding ) : digits );
		}

		return pattern.replace( TOKEN_PATTERN, replaceToken );
	}

	/**
	 * Refresh the preview and the guard messages of an editor.
	 *
	 * @param {HTMLFormElement} editor Sequence form.
	 * @return {void}
	 */
	function refreshEditor( editor ) {
		var last = editor.dataset.last === '' ? null : parseInt( editor.dataset.last, 10 );
		var next = parseInt( editor.querySelector( '#wsfsn-next' ).value, 10 ) || 1;
		var digits = sanitizeDigits( editor.querySelector( '#wsfsn-digits' ).value );
		var pattern = editor.querySelector( '#wsfsn-format' ).value;
		var reset = editor.querySelector( '#wsfsn-reset' ).value;

		editor.querySelector( '.wsfsn-preview' ).textContent = renderFormat( pattern, next, digits, editor.dataset.today );
		editor.querySelector( '.wsfsn-too-low' ).hidden = last === null || next > last;
		editor.querySelector( '.wsfsn-gap-warning' ).hidden = last === null || next <= last + 1;
		editor.querySelector( '[data-wsfsn-period-start]' ).hidden = reset === 'never';
	}

	/**
	 * Refresh the editor holding the field that changed.
	 *
	 * @param {Event} event Input or change event.
	 * @return {void}
	 */
	function onEditorInput( event ) {
		refreshEditor( event.currentTarget );
	}

	/**
	 * Ask before deleting a sequence.
	 *
	 * @param {Event} event Click event.
	 * @return {void}
	 */
	function confirmDelete( event ) {
		var settings = window.wsformSubmissionNumberAdmin || {};

		if ( ! window.confirm( settings.confirmDelete || '' ) ) {
			event.preventDefault();
		}
	}

	/**
	 * Wire the editor and the delete link.
	 *
	 * @return {void}
	 */
	function init() {
		var editor = document.querySelector( '.wsform-submission-number-editor' );
		var deleteLink = document.querySelector( '.wsfsn-delete' );

		if ( editor ) {
			editor.addEventListener( 'input', onEditorInput );
			editor.addEventListener( 'change', onEditorInput );
			refreshEditor( editor );
		}

		if ( deleteLink ) {
			deleteLink.addEventListener( 'click', confirmDelete );
		}
	}

	init();
}() );
