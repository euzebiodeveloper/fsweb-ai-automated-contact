/**
 * Admin behaviour for AI Contact: license actions and provider test.
 *
 * @package Fsweb_Ai_Automated_Contact
 */

( function ( $ ) {
	'use strict';

	var config = window.FSWEB_Aic_Admin;

	if ( ! config ) {
		return;
	}

	function message( $target, type, text ) {
		$target
			.removeClass( 'notice-success notice-error notice-warning' )
			.addClass( 'notice-' + type )
			.text( text )
			.show();
	}

	function post( action, data ) {
		return $.post(
			config.ajax_url,
			$.extend(
				{
					action: action,
					nonce: config.nonce
				},
				data || {}
			)
		);
	}

	function statusLabel( status ) {
		var map = {
			active: config.i18n.status_active,
			expired: config.i18n.status_expired,
			invalid: config.i18n.status_invalid,
			pending: config.i18n.status_pending
		};

		return map[ status ] || config.i18n.status_inactive;
	}

	function applyLicenseStatus( status, expires ) {
		var $status = $( '#fsweb-aic-license-status' );
		$status
			.attr( 'class', 'fsweb-aic-status fsweb-aic-status--' + status )
			.text( statusLabel( status ) );

		if ( 'active' === status ) {
			$( '#fsweb-aic-license-key' ).prop( 'readonly', true );
			$( '#fsweb-aic-activate-license' ).hide();
			$( '#fsweb-aic-deactivate-license' ).show();
			$( '#fsweb-aic-validate-license' ).hide();
		} else {
			$( '#fsweb-aic-license-key' ).prop( 'readonly', false );
			$( '#fsweb-aic-activate-license' ).show();
			$( '#fsweb-aic-deactivate-license' ).hide();
			$( '#fsweb-aic-validate-license' ).show();
		}

		if ( expires ) {
			$( '#fsweb-aic-license-expires' ).text( expires );
			$( '#fsweb-aic-expires-row' ).show();
		} else {
			$( '#fsweb-aic-expires-row' ).hide();
		}
	}

	$( document ).on( 'click', '#fsweb-aic-activate-license, #fsweb-aic-onboarding-activate', function () {
		var onboarding = 'fsweb-aic-onboarding-activate' === this.id;
		var $button = $( this );
		var $message = onboarding ? $( '#fsweb-aic-onboarding-message' ) : $( '#fsweb-aic-license-message' );
		var key = onboarding ? $( '#fsweb-aic-onboarding-license-key' ).val() : $( '#fsweb-aic-license-key' ).val();

		if ( ! key ) {
			message( $message, 'error', config.i18n.empty_key );
			return;
		}

		$button.prop( 'disabled', true ).text( config.i18n.activating );

		post( 'fsweb_aic_activate_license', { license_key: key } )
			.done( function ( response ) {
				var payload = response && response.data ? response.data : {};

				if ( response && response.success ) {
					message( $message, 'success', payload.message || '' );
					applyLicenseStatus( payload.status || 'active', payload.expires || '' );

					if ( onboarding ) {
						$( '#fsweb-aic-onboarding-license' ).hide();
						$( '#fsweb-aic-onboarding-done' ).show();
						$( '#fsweb-aic-onboarding-message' ).hide();
					}
				} else {
					message( $message, 'error', ( payload && payload.message ) || config.i18n.network_error );
					applyLicenseStatus( ( payload && payload.status ) || 'invalid', '' );
				}
			} )
			.fail( function () {
				message( $message, 'error', config.i18n.network_error );
			} )
			.always( function () {
				$button.prop( 'disabled', false ).text( onboarding ? 'Ativar' : 'Ativar' );
			} );
	} );

	$( document ).on( 'click', '#fsweb-aic-deactivate-license', function () {
		if ( ! window.confirm( config.i18n.confirm_deactivate ) ) {
			return;
		}

		var $button = $( this );
		var $message = $( '#fsweb-aic-license-message' );

		$button.prop( 'disabled', true ).text( config.i18n.deactivating );

		post( 'fsweb_aic_deactivate_license', { license_key: $( '#fsweb-aic-license-key' ).val() } )
			.done( function ( response ) {
				var payload = response && response.data ? response.data : {};

				if ( response && response.success ) {
					message( $message, 'success', payload.message || '' );
					applyLicenseStatus( payload.status || 'pending', '' );
				} else {
					message( $message, 'error', ( payload && payload.message ) || config.i18n.network_error );
				}
			} )
			.fail( function () {
				message( $message, 'error', config.i18n.network_error );
			} )
			.always( function () {
				$button.prop( 'disabled', false ).text( 'Desativar' );
			} );
	} );

	$( document ).on( 'click', '#fsweb-aic-validate-license', function () {
		var $button = $( this );
		var $spinner = $( '#fsweb-aic-validate-spinner' );
		var $message = $( '#fsweb-aic-license-message' );

		$button.prop( 'disabled', true ).text( config.i18n.validating );
		$spinner.show();

		post( 'fsweb_aic_validate_license', { license_key: $( '#fsweb-aic-license-key' ).val() } )
			.done( function ( response ) {
				var payload = response && response.data ? response.data : {};

				if ( response && response.success ) {
					message( $message, 'success', payload.message || '' );
					applyLicenseStatus( payload.status || 'pending', payload.expires || '' );
				} else {
					message( $message, 'error', ( payload && payload.message ) || config.i18n.network_error );
				}
			} )
			.fail( function () {
				message( $message, 'error', config.i18n.network_error );
			} )
			.always( function () {
				$spinner.hide();
				$button.prop( 'disabled', false ).text( 'Verificar licença' );
			} );
	} );

	$( document ).on( 'click', '#fsweb-aic-test-provider', function () {
		var $button = $( this );
		var $spinner = $( '#fsweb-aic-test-spinner' );
		var $result = $( '#fsweb-aic-test-result' );

		$button.prop( 'disabled', true ).text( config.i18n.testing );
		$spinner.show();
		$result.removeClass( 'is-ok is-error' ).text( '' );

		post( 'fsweb_aic_test_provider' )
			.done( function ( response ) {
				var payload = response && response.data ? response.data : {};

				if ( response && response.success ) {
					$result.addClass( 'is-ok' ).text( payload.message || '' );
				} else {
					$result.addClass( 'is-error' ).text( ( payload && payload.message ) || config.i18n.network_error );
				}
			} )
			.fail( function () {
				$result.addClass( 'is-error' ).text( config.i18n.network_error );
			} )
			.always( function () {
				$spinner.hide();
				$button.prop( 'disabled', false ).text( 'Testar agora' );
			} );
	} );

	// Show only the model suggestions of the selected provider.
	$( document ).on( 'change', '#fsweb-aic-provider', function () {
		var provider = $( this ).val();

		$( '#fsweb-aic-model-list option' ).each( function () {
			var $option = $( this );
			$option.prop( 'disabled', $option.data( 'provider' ) !== provider );
		} );
	} );
} )( jQuery );
