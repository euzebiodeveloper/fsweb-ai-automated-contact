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
		} else {
			$( '#fsweb-aic-license-key' ).prop( 'readonly', false );
			$( '#fsweb-aic-activate-license' ).show();
			$( '#fsweb-aic-deactivate-license' ).hide();
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

	$( document ).on( 'click', '#fsweb-aic-test-provider', function () {
		var $button = $( this );
		var $spinner = $( '#fsweb-aic-test-spinner' );
		var $result = $( '#fsweb-aic-test-result' );
		var provider = $( '#fsweb-aic-provider' ).val();

		$button.prop( 'disabled', true ).text( config.i18n.testing );
		$spinner.show();
		$result.removeClass( 'is-ok is-error' ).text( '' );

		post( 'fsweb_aic_test_provider', {
			provider: provider,
			model: $( '#fsweb-aic-model' ).val(),
			api_key: $( '#fsweb-aic-key-' + provider ).val(),
			base_url: $( '#fsweb-aic-base-url' ).val()
		} )
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

	// The "Current setup" line follows whatever is selected on screen.
	function syncCurrentSetup() {
		var $target = $( '#fsweb-aic-current-setup' );

		if ( ! $target.length || ! config.i18n.current_setup ) {
			return;
		}

		var provider = $( '#fsweb-aic-provider' ).val() || '';
		var model = $.trim( $( '#fsweb-aic-model' ).val() );
		var customModel = $.trim( $( '#fsweb-aic-custom-model' ).val() );

		if ( 'custom' === provider && customModel ) {
			model = customModel;
		}

		$target.text(
			config.i18n
				.current_setup.replace( '%1$s', provider )
				.replace( '%2$s', model )
		);
	}

	// Only the credentials of the selected provider are shown, so it is
	// clear that one AI is used at a time. The model list is rebuilt for
	// the chosen provider, with a free-text fallback for new models.
	function syncProvider( provider ) {
		$( '.fsweb-aic-credentials' ).each( function () {
			var $group = $( this );
			$group.prop( 'hidden', $group.data( 'provider' ) !== provider );
		} );

		var models = ( config.providers && config.providers[ provider ] && config.providers[ provider ].models ) || [];
		var $select = $( '#fsweb-aic-model-select' );
		var $input = $( '#fsweb-aic-model' );
		var $custom = $( '#fsweb-aic-model-custom' );
		var current = $.trim( $input.val() );

		if ( ! $select.length ) {
			return;
		}

		$select.empty();

		$.each( models, function ( index, model ) {
			$( '<option>' ).attr( 'value', model ).text( model ).appendTo( $select );
		} );

		$( '<option>' ).attr( 'value', '__custom__' ).text( config.i18n.custom_model ).appendTo( $select );

		if ( current && models.indexOf( current ) !== -1 ) {
			$select.val( current );
			$custom.prop( 'hidden', true );
			syncCurrentSetup();
			return;
		}

		if ( models.length ) {
			$select.val( models[ 0 ] );
			$input.val( models[ 0 ] );
			$custom.prop( 'hidden', true );
			syncCurrentSetup();
			return;
		}

		$select.val( '__custom__' );
		$custom.prop( 'hidden', false );
		syncCurrentSetup();
	}

	$( document ).on( 'change', '#fsweb-aic-provider', function () {
		syncProvider( $( this ).val() );
	} );

	$( document ).on( 'change', '#fsweb-aic-model-select', function () {
		var value = $( this ).val();
		var $input = $( '#fsweb-aic-model' );
		var $custom = $( '#fsweb-aic-model-custom' );

		if ( '__custom__' === value ) {
			$custom.prop( 'hidden', false );
			$input.trigger( 'focus' );
			syncCurrentSetup();
			return;
		}

		$input.val( value );
		$custom.prop( 'hidden', true );
		syncCurrentSetup();
	} );

	$( document ).on( 'input', '#fsweb-aic-model, #fsweb-aic-custom-model', syncCurrentSetup );
	syncCurrentSetup();

	syncProvider( $( '#fsweb-aic-provider' ).val() );

	// Restore the shipped context text.
	$( document ).on( 'click', '#fsweb-aic-context-reset', function () {
		if ( config.default_context ) {
			$( '#fsweb-aic-context' ).val( config.default_context );
		}
	} );
	// Avatar picker + live preview of the widget.
	function syncPreview() {
		var $preview = $( '#fsweb-aic-preview' );

		if ( ! $preview.length ) {
			return;
		}

		var primary = $( '#fsweb-aic-color-primary' ).val() || '#6e8b54';
		var title = $( '#fsweb-aic-color-title' ).val() || '#ffffff';
		var background = $( '#fsweb-aic-color-bg' ).val() || '#f6f8fc';
		var text = $( '#fsweb-aic-color-text' ).val() || '#16202c';
		var bubble = $( '#fsweb-aic-color-bubble' ).val() || '#ffffff';
		var type = $( '#fsweb-aic-avatar' ).val();
		var image = $.trim( $( '#fsweb-aic-avatar-image' ).val() );

		$preview.find( '.fsweb-aic-preview__header' ).css( { background: primary, color: title } );
		$preview.find( '.fsweb-aic-preview__body' ).css( 'background', background );
		$preview.find( '.fsweb-aic-preview__bubble' ).css( { color: text, background: bubble } );
		$preview.find( '.fsweb-aic-preview__ball' ).css( 'background', primary );

		var $avatar = $preview.find( '.fsweb-aic-preview__avatar' );

		if ( 'custom' === type && image ) {
			$avatar.html( $( '<img>' ).attr( 'src', image ).attr( 'alt', '' ) );
		} else {
			$avatar.text( 'A' );
		}
	}

	$( document ).on( 'change', '#fsweb-aic-avatar', function () {
		$( '#fsweb-aic-avatar-custom' ).prop( 'hidden', 'custom' !== $( this ).val() );
		syncPreview();
	} );

	var avatarFrame = null;

	$( document ).on( 'click', '#fsweb-aic-avatar-pick', function ( event ) {
		event.preventDefault();

		if ( ! window.wp || ! window.wp.media ) {
			return;
		}

		if ( ! avatarFrame ) {
			avatarFrame = window.wp.media( {
				title: config.i18n.choose_image || 'Choose image',
				button: { text: config.i18n.use_image || 'Use this image' },
				library: { type: 'image' },
				multiple: false
			} );

			avatarFrame.on( 'select', function () {
				var attachment = avatarFrame.state().get( 'selection' ).first().toJSON();
				$( '#fsweb-aic-avatar-image' ).val( attachment.url );
				syncPreview();
			} );
		}

		avatarFrame.open();
	} );

	$( document ).on( 'click', '#fsweb-aic-avatar-clear', function ( event ) {
		event.preventDefault();
		$( '#fsweb-aic-avatar-image' ).val( '' );
		syncPreview();
	} );

	$( document ).on( 'input change', '#fsweb-aic-avatar-image, #fsweb-aic-color-primary, #fsweb-aic-color-title, #fsweb-aic-color-bg, #fsweb-aic-color-text, #fsweb-aic-color-bubble', syncPreview );

	syncPreview();
} )( jQuery );
