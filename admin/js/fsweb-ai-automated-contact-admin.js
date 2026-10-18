/**
 * Admin behaviour for AI Contact: provider test and live widget preview.
 *
 * @package Fsweb_Ai_Automated_Contact
 */

( function ( $ ) {
	'use strict';

	var config = window.FSWEB_Aic_Admin;

	if ( ! config ) {
		return;
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
			api_key: $( '#fsweb-aic-key-' + provider ).val()
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
				$button.prop( 'disabled', false ).text( config.i18n.test_now );
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

		$target.text(
			config.i18n
				.current_setup.replace( '%1$s', provider )
				.replace( '%2$s', model )
		);
	}

	// The model list is rebuilt for the chosen provider, with a free-text
	// fallback so a model released after this version still works.
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

	// Live preview of the widget: colours, icon, identity and position.
	function syncPreview() {
		var $preview = $( '#fsweb-aic-preview' );

		if ( ! $preview.length ) {
			return;
		}

		var primary = $( '#fsweb-aic-color-primary' ).val() || '#6e8b54';
		var title = $( '#fsweb-aic-pro-color-title' ).val() || '#ffffff';
		var background = $( '#fsweb-aic-pro-color-bg' ).val() || '#f6f8fc';
		var text = $( '#fsweb-aic-pro-color-text' ).val() || '#16202c';
		var bubble = $( '#fsweb-aic-pro-color-bubble' ).val() || '#ffffff';
		var type = $( '#fsweb-aic-avatar' ).val();
		var name = $.trim( $( '#fsweb-aic-pro-name' ).val() || '' );
		var welcome = $.trim( $( '#fsweb-aic-pro-welcome' ).val() || '' );
		var image = $.trim( $( '#fsweb-aic-pro-avatar' ).val() || '' );

		$preview.css( '--fsweb-aic-preview-primary', primary );
		$preview.find( '.fsweb-aic-preview__header' ).css( { background: primary, color: title } );
		$preview.find( '.fsweb-aic-preview__body' ).css( 'background', background );
		$preview.find( '.fsweb-aic-preview__bubble:not(.fsweb-aic-preview__bubble--user)' ).css( { color: text, background: bubble } );
		$preview.find( '.fsweb-aic-preview__footer' ).css( 'background', bubble );

		if ( name ) {
			$preview.find( '.fsweb-aic-preview__name' ).text( name );
		}

		if ( welcome ) {
			$( '#fsweb-aic-preview-bubble' ).text( welcome );
		}

		var $avatar = $preview.find( '.fsweb-aic-preview__avatar' );
		var $ball = $preview.find( '.fsweb-aic-preview__ball' );

		if ( image ) {
			$avatar.empty().append( $( '<img>' ).attr( 'src', image ).attr( 'alt', '' ) );
			$ball.empty().append( $( '<img>' ).attr( 'src', image ).attr( 'alt', '' ) );
		} else {
			var icons = config.avatar_icons || {};
			var icon = icons[ type ] || icons.chat || '';
			$avatar.html( icon );
			$ball.html( icon );
		}

		var position = $( 'input[name$="[position]"]:checked' ).val() || 'right';
		$( '#fsweb-aic-preview-stage' ).toggleClass( 'is-left', 'left' === position );
	}

	$( document ).on( 'input change', '#fsweb-aic-avatar, #fsweb-aic-color-primary, #fsweb-aic-pro-color-title, #fsweb-aic-pro-color-bg, #fsweb-aic-pro-color-text, #fsweb-aic-pro-color-bubble, #fsweb-aic-pro-name, #fsweb-aic-pro-welcome, #fsweb-aic-pro-avatar, input[name$="[position]"]', syncPreview );

	function closePreview() {
		$( '#fsweb-aic-preview-modal' ).prop( 'hidden', true );
		$( 'body' ).removeClass( 'fsweb-aic-preview-is-open' );
	}

	$( document ).on( 'click', '#fsweb-aic-preview-open', function () {
		syncPreview();
		$( '#fsweb-aic-preview-modal' ).prop( 'hidden', false );
		$( 'body' ).addClass( 'fsweb-aic-preview-is-open' );
		$( '#fsweb-aic-preview-modal .fsweb-aic-preview-modal__close' ).trigger( 'focus' );
	} );

	$( document ).on( 'click', '[data-fsweb-aic-close]', closePreview );

	$( document ).on( 'keydown', function ( event ) {
		if ( 'Escape' === event.key || 27 === event.keyCode ) {
			closePreview();
		}
	} );

	syncPreview();
} )( jQuery );
