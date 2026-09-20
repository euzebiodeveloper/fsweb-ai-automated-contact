/**
 * FSWEB Update Checker — Admin Scripts
 *
 * Handles the version rollback modal interaction inside the
 * "Previous Versions" thickbox, the "Check for Updates"
 * inline AJAX action, and automatic update checks on page load.
 *
 * Depends on jQuery (available in WP admin) and the global
 * FSWEB_UpdateChecker_Rollback object (localized via wp_localize_script).
 */
( function( $, data ) {
	'use strict';

	var $doc = $( document );

	// ------------------------------------------------------------------//
	//  Previous Versions — thickbox radio / install
	// ------------------------------------------------------------------//

	/**
	 * Enable the install button when a non-current version radio
	 * is selected.
	 */
	$doc.on( 'change', 'input[name="fsweb_version"]', function() {
		$( '#fsweb-install-version' ).prop( 'disabled', false );
	} );

	/**
	 * Handle install button click: POST the selected version
	 * to the AJAX install endpoint.
	 */
	$doc.on( 'click', '#fsweb-install-version', function() {
		var $btn     = $( this );
		var $checked = $( 'input[name="fsweb_version"]:checked' );

		if ( ! $checked.length ) {
			return;
		}

		var downloadUrl = $checked.val();
		var version     = $checked.data( 'version' );
		var $result     = $( '#fsweb-install-result' );

		$btn.prop( 'disabled', true ).text( data.i18n.installing || 'Installing\u2026' );
		$result
			.removeClass( 'success error' )
			.hide()
			.html( '<p>' + ( data.i18n.downloading || 'Downloading and installing version' ) + ' ' + version + '\u2026</p>' )
			.show();

		$.post( window.ajaxurl, {
			action:       data.action,
			_ajax_nonce:  data.nonce,
			download_url: downloadUrl,
			version:      version
		}, function( response ) {
			if ( response.success ) {
				$result
					.removeClass( 'error' )
					.addClass( 'success' )
					.html( '<p>' + response.data.message + '</p>' );

				// Reload the parent plugins page after a short delay
				// so the version number updates in the UI.
				setTimeout( function() {
					if ( window.parent && window.parent.tb_remove ) {
						window.parent.tb_remove();
					}
					window.parent.location.reload();
				}, 1500 );
			} else {
				$btn.prop( 'disabled', false ).text( data.i18n.installBtn || 'Install Selected Version' );
				$result
					.removeClass( 'success' )
					.addClass( 'error' )
					.html( '<p>' + response.data.message + '</p>' );
			}
		}, 'json' ).fail( function( jqXHR, textStatus, errorThrown ) {
			$btn.prop( 'disabled', false ).text( data.i18n.installBtn || 'Install Selected Version' );
			$result
				.removeClass( 'success' )
				.addClass( 'error' )
				.html( '<p>' + ( data.i18n.networkError || 'Network error. Please try again.' ) + '</p>' );
		} );
	} );

	// ------------------------------------------------------------------//
	//  Check for Updates — inline AJAX (shared by auto + manual)
	// ------------------------------------------------------------------//

	/**
	 * Perform an update check and show the result inline.
	 *
	 * Called automatically on page load AND when the user clicks
	 * the "Check for Updates" link. On success with an available
	 * update, the update notice row is injected directly into the
	 * DOM — no page reload required.
	 *
	 * Auto-check (force=false) serves cached metadata when still
	 * fresh, avoiding an HTTP request on every page load. Manual
	 * clicks (force=true) always fetch from the remote API.
	 *
	 * @param {jQuery}  $row    The plugin <tr> element.
	 * @param {jQuery}  $link   The "Check for Updates" <a> element (carries data-action/data-nonce).
	 * @param {boolean} force   Whether to bypass the cache and force a fresh fetch.
	 * @param {boolean} silent  Suppress the "checking" text and inline notices (auto-check).
	 */
	function doUpdateCheck( $row, $link, force, silent ) {
		// Read action/nonce from the link itself so each plugin's
		// update-checker never relies on a shared global object.
		var action   = $link.data( 'action' );
		var nonce    = $link.data( 'nonce' );
		var origText = silent ? '' : $link.text();

		if ( ! silent ) {
			$link.text( data.i18n.checking || 'Checking\u2026' );
		}

		$.post( window.ajaxurl, {
			action:      action,
			_ajax_nonce: nonce,
			force:       force ? '1' : '0'
		}, function( response ) {
			if ( response.success ) {
				if ( response.data.has_update ) {
					// Update available — inject the native-looking
					// update row directly (no page reload).
					injectUpdateRow( $row, response.data );
				} else if ( ! silent ) {
					// Manual click — show the "latest version" confirmation.
					// Auto-check stays silent so the plugins page stays clean.
					showInlineNotice( $row, response.data.message, 'success' );
				}
			} else if ( ! silent ) {
				// Manual click — show the error inline.
				// Auto-check stays silent: PHP admin_notices already handles
				// license/server errors globally, no need to clutter each row.
				showInlineNotice( $row, response.data.message || 'Check failed.', 'warning' );
			}
		}, 'json' ).fail( function() {
			if ( ! silent ) {
				showInlineNotice( $row, data.i18n.networkError || 'Network error.', 'error' );
			}
		} ).always( function() {
			if ( ! silent ) {
				$link.text( origText );
			}
		} );
	}

	/**
	 * Inject a WordPress-native update notice row below the plugin row.
	 *
	 * Builds the same <tr class="plugin-update-tr"> markup that WordPress
	 * core uses for plugin updates, so it looks and behaves identically:
	 * the "View version X.Y.Z details" link opens the thickbox, and the
	 * "update now" link triggers the standard WordPress upgrade flow.
	 *
	 * @param {jQuery} $row     The plugin <tr> element.
	 * @param {object} update   Data from the AJAX response.
	 */
	function injectUpdateRow( $row, update ) {
		// Remove any previous inline notice or update row.
		$row.next( '.fsweb-inline-notice' ).remove();
		$row.next( '.plugin-update-tr:not(.fsweb-inline-notice)' ).remove();

		// Add the `update` class so WordPress CSS fuses the rows.
		$row.addClass( 'update' );

		var message = wp.i18n && wp.i18n.sprintf
			? wp.i18n.sprintf(
				/* translators: 1: plugin name, 2: new version number */
				wp.i18n.__( 'There is a new version of %1$s available. <a href="%3$s" class="thickbox open-plugin-details-modal">View version %2$s details</a> or <a href="%4$s" class="update-link">update now</a>.' ),
				update.plugin_name,
				update.latest_version,
				update.details_url,
				update.update_url
			)
			: 'There is a new version of <strong>' + escHtml( update.plugin_name ) + '</strong> available. ' +
			  '<a href="' + escAttr( update.details_url ) + '" class="thickbox open-plugin-details-modal">' +
			  'View version ' + escHtml( update.latest_version ) + ' details</a> ' +
			  'or <a href="' + escAttr( update.update_url ) + '" class="update-link">update now</a>.';

		var $updateRow = $( '<tr class="plugin-update-tr active fsweb-auto-update-row" ' +
			'data-slug="' + escAttr( update.slug || update.plugin_basename.replace( /\/.*$/, '' ) ) + '" ' +
			'data-plugin="' + escAttr( update.plugin_basename ) + '">' +
			'<td colspan="4" class="plugin-update colspanchange">' +
			'<div class="update-message notice inline notice-warning notice-alt">' +
			'<p>' + message + '</p>' +
			'</div></td></tr>' );

		$row.after( $updateRow );

		// Re-bind thickbox for the newly injected link.
		if ( window.tb_init && typeof window.tb_init === 'function' ) {
			window.tb_init( 'a.thickbox, area.thickbox, input.thickbox' );
		}

		// Accessibility: announce the update to screen readers.
		if ( wp.a11y && wp.a11y.speak ) {
			wp.a11y.speak( update.message );
		}
	}

	/**
	 * Show (or replace) an inline notice below a plugin row.
	 *
	 * Uses WordPress-standard classes so it looks identical to
	 * the native update notification row. Adds the `update` class
	 * to the plugin row so the two rows are visually fused (no gap).
	 *
	 * @param {jQuery} $row    The plugin <tr> element.
	 * @param {string} message Notice text (plain text, will be escaped).
	 * @param {string} type    'warning', 'error', or 'success'.
	 */
	function showInlineNotice( $row, message, type ) {
		// Remove any previous inline notice or auto-update row.
		$row.next( '.fsweb-inline-notice' ).remove();
		$row.next( '.fsweb-auto-update-row' ).remove();

		var cls = 'notice-error';
		if ( 'warning' === type ) {
			cls = 'notice-warning';
		} else if ( 'success' === type ) {
			cls = 'notice-success';
		}

		// Add `update` class so WordPress CSS fuses the two rows together.
		$row.addClass( 'update' );

		var $notice = $( '<tr class="plugin-update-tr active fsweb-inline-notice">' +
			'<td colspan="4" class="plugin-update colspanchange">' +
			'<div class="update-message notice inline ' + cls + ' notice-alt">' +
			'<p>' + escHtml( message ) + '</p>' +
			'</div></td></tr>' );

		$row.after( $notice );
	}

	/**
	 * Escape HTML entities in a plain-text string.
	 *
	 * @param {string} str Raw string.
	 * @return {string} HTML-escaped string.
	 */
	function escHtml( str ) {
		var div = document.createElement( 'div' );
		div.appendChild( document.createTextNode( str ) );
		return div.innerHTML;
	}

	/**
	 * Escape a value for use inside an HTML attribute.
	 *
	 * @param {string} str Raw string.
	 * @return {string} Attribute-safe string.
	 */
	function escAttr( str ) {
		return String( str )
			.replace( /&/g, '&amp;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#39;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' );
	}

	/**
	 * Find the plugin row that contains a "Check for Updates" link.
	 *
	 * Walks up from the link to the closest <tr> that represents
	 * a plugin row on the plugins.php page.
	 *
	 * @param {jQuery} $link The "Check for Updates" <a> element.
	 * @return {jQuery} The plugin's <tr> element.
	 */
	function findPluginRow( $link ) {
		return $link.closest( 'tr' );
	}

	// ------------------------------------------------------------------//
	//  Manual "Check for Updates" click handler
	// ------------------------------------------------------------------//

	$doc.on( 'click', '.fsweb-check-updates', function( e ) {
		e.preventDefault();

		var $link = $( this );
		var $row  = findPluginRow( $link );

		doUpdateCheck( $row, $link, true, false );
	} );

	// ------------------------------------------------------------------//
	//  Auto-check on plugins page load
	// ------------------------------------------------------------------//

	/**
	 * Automatically run an update check for every plugin that has
	 * a "Check for Updates" link. This ensures the user sees the
	 * latest status immediately — no manual click required.
	 */
	$doc.ready( function() {
		$( '.fsweb-check-updates' ).each( function() {
			var $link = $( this );
			var $row  = findPluginRow( $link );

			// Only auto-check rows that don't already have an
			// active update row injected by WordPress core.
			// force=false => serve from cache when fresh (no HTTP).
			if ( ! $row.hasClass( 'update' ) ) {
				doUpdateCheck( $row, $link, false, true );
			}
		} );
	} );

} )( jQuery, window.FSWEB_UpdateChecker_Rollback || {} );
