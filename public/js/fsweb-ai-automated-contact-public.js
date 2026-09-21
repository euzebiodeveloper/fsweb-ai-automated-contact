/**
 * Public chat widget behaviour.
 *
 * Vanilla JS, no dependencies. Talks only to the plugin REST namespace and
 * keeps the conversation in sessionStorage so a page reload does not lose the
 * thread. Provider API keys never reach this file.
 *
 * @package Fsweb_Ai_Automated_Contact
 */

( function () {
	'use strict';

	var config = window.FSWEB_Aic_Widget;

	if ( ! config || ! config.rest_url ) {
		return;
	}

	var STORAGE_KEY = 'fswebAicHistory';
	var MAX_HISTORY = 16;

	var widget = document.getElementById( 'fsweb-aic-widget' );
	var launcher = document.getElementById( 'fsweb-aic-launcher' );
	var panel = document.getElementById( 'fsweb-aic-panel' );
	var messages = document.getElementById( 'fsweb-aic-messages' );
	var composer = document.getElementById( 'fsweb-aic-composer' );
	var input = document.getElementById( 'fsweb-aic-input' );
	var sendButton = document.getElementById( 'fsweb-aic-send' );
	var closeButton = document.getElementById( 'fsweb-aic-close' );
	var resetButton = document.getElementById( 'fsweb-aic-reset' );
	var badge = document.getElementById( 'fsweb-aic-launcher-badge' );

	if ( ! widget || ! launcher || ! panel || ! messages || ! composer || ! input ) {
		return;
	}

	var history = [];
	var busy = false;
	var greeted = false;
	var lastProductsKey = '';

	function readHistory() {
		try {
			var raw = window.sessionStorage.getItem( STORAGE_KEY );
			var parsed = raw ? JSON.parse( raw ) : [];
			return Array.isArray( parsed ) ? parsed : [];
		} catch ( error ) {
			return [];
		}
	}

	function writeHistory() {
		try {
			window.sessionStorage.setItem( STORAGE_KEY, JSON.stringify( history.slice( -MAX_HISTORY ) ) );
		} catch ( error ) {
			// Storage unavailable (private mode): the thread stays in memory.
		}
	}

	function text( key, fallback ) {
		return config.i18n && config.i18n[ key ] ? config.i18n[ key ] : fallback;
	}

	// Remember that the visitor opened a product page: the chat reopens there
	// so the conversation carries on.
	function markReopen() {
		try {
			window.sessionStorage.setItem( STORAGE_KEY + '_reopen', '1' );
		} catch ( error ) {
			// Storage unavailable: the page still opens, just without the chat.
		}
	}

	function shouldReopen() {
		try {
			if ( window.sessionStorage.getItem( STORAGE_KEY + '_reopen' ) ) {
				window.sessionStorage.removeItem( STORAGE_KEY + '_reopen' );
				return true;
			}
		} catch ( error ) {
			// Storage unavailable.
		}

		return false;
	}

	function scrollToBottom() {
		messages.scrollTop = messages.scrollHeight;
	}

	function addRow( role, content ) {
		var row = document.createElement( 'div' );
		row.className = 'fsweb-aic-row fsweb-aic-row--' + role;

		var bubble = document.createElement( 'div' );
		bubble.className = 'fsweb-aic-bubble fsweb-aic-bubble--' + role;
		bubble.textContent = content;

		row.appendChild( bubble );
		messages.appendChild( row );
		scrollToBottom();

		return bubble;
	}

	function addTyping() {
		var row = document.createElement( 'div' );
		row.className = 'fsweb-aic-row fsweb-aic-row--agent';
		row.setAttribute( 'data-typing', '1' );

		var bubble = document.createElement( 'div' );
		bubble.className = 'fsweb-aic-bubble fsweb-aic-bubble--agent';

		var dots = document.createElement( 'span' );
		dots.className = 'fsweb-aic-typing';
		dots.innerHTML = '<span></span><span></span><span></span>';

		bubble.appendChild( dots );
		row.appendChild( bubble );
		messages.appendChild( row );
		scrollToBottom();

		return row;
	}

	function removeTyping() {
		var typing = messages.querySelector( '[data-typing="1"]' );

		if ( typing ) {
			typing.remove();
		}
	}

	// Safety net: never print raw entities from the catalogue.
	function decode( value ) {
		var holder = document.createElement( 'textarea' );
		holder.innerHTML = String( value == null ? '' : value );
		return holder.value;
	}

	function addProducts( products ) {
		if ( ! products || ! products.length ) {
			return;
		}

		// Do not repeat the same shelf twice in a row: the agent often looks
		// the same products up again while answering a follow-up question.
		var key = products.map( function ( product ) {
			return product.product_id;
		} ).join( ',' );

		if ( key === lastProductsKey ) {
			return;
		}

		lastProductsKey = key;

		var list = document.createElement( 'div' );
		list.className = 'fsweb-aic-products';

		products.forEach( function ( product ) {
			var card = document.createElement( 'div' );
			card.className = 'fsweb-aic-product';

			var thumb = document.createElement( 'div' );
			thumb.className = 'fsweb-aic-product__thumb';

			if ( product.image ) {
				var image = document.createElement( 'img' );
				image.src = product.image;
				image.alt = '';
				image.loading = 'lazy';

				thumb.appendChild( image );
			} else {
				// No featured image: show a discreet placeholder instead of a gap.
				thumb.className += ' fsweb-aic-product__thumb--empty';
				thumb.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 5h16a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Zm11.5 5.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3ZM5 17l4.5-5 3.5 3.5 2.5-2 4.5 3.5H5Z" fill="currentColor"/></svg>';
			}

			card.appendChild( thumb );

			var info = document.createElement( 'div' );
			info.className = 'fsweb-aic-product__info';

			var name = document.createElement( 'span' );
			name.className = 'fsweb-aic-product__name';
			name.textContent = decode( product.name );
			info.appendChild( name );

			var price = document.createElement( 'span' );
			price.className = 'fsweb-aic-product__price';
			price.textContent = decode( product.price );
			info.appendChild( price );

			if ( product.summary ) {
				var summary = document.createElement( 'span' );
				summary.className = 'fsweb-aic-product__summary';
				summary.textContent = decode( product.summary );
				info.appendChild( summary );
			}

			var actions = document.createElement( 'div' );
			actions.className = 'fsweb-aic-product__actions';

			if ( product.url ) {
				var link = document.createElement( 'a' );
				link.className = 'fsweb-aic-product__link';
				link.href = product.url;
				// Same tab, and the chat reopens on the next page so the
				// conversation continues instead of a blank tab.
				link.addEventListener( 'click', function () {
					markReopen();
				} );
				link.textContent = text( 'view_product', 'View product' );
				actions.appendChild( link );
			}

			if ( product.available === false ) {
				var out = document.createElement( 'span' );
				out.className = 'fsweb-aic-product__price';
				out.textContent = text( 'out_of_stock', 'Out of stock' );
				actions.appendChild( out );
			} else if ( product.product_id ) {
				var addButton = document.createElement( 'button' );
				addButton.type = 'button';
				addButton.className = 'fsweb-aic-product__button';
				addButton.textContent = text( 'add_to_cart', 'Add to cart' );
				addButton.addEventListener( 'click', function () {
					var request = ( config.i18n.add_request || 'Please add product id %s to my cart' ).replace( '%s', product.product_id );

					if ( product.name ) {
						request += ' (' + product.name + ')';
					}

					send( request );
				} );
				actions.appendChild( addButton );
			}

			info.appendChild( actions );
			card.appendChild( info );
			list.appendChild( card );
		} );

		messages.appendChild( list );
		scrollToBottom();
	}

	function addCheckoutCta( cart ) {
		if ( ! cart || ! cart.checkout_url ) {
			return;
		}

		var link = document.createElement( 'a' );
		link.className = 'fsweb-aic-cta';
		link.href = cart.checkout_url;
		link.textContent = text( 'go_checkout', 'Finalizar compra' );

		messages.appendChild( link );
		scrollToBottom();
	}

	function renderExisting() {
		var stored = readHistory();

		if ( ! stored.length ) {
			return;
		}

		stored.forEach( function ( entry ) {
			if ( entry && entry.content ) {
				addRow( entry.role === 'user' ? 'user' : 'agent', entry.content );
			}
		} );
	}

	function pushHistory( role, content ) {
		history.push( { role: role, content: content } );
		history = history.slice( -MAX_HISTORY );
		writeHistory();
	}

	function greet() {
		if ( greeted ) {
			return;
		}

		greeted = true;

		if ( ! history.length && config.welcome ) {
			addRow( 'agent', config.welcome );
			pushHistory( 'assistant', config.welcome );
		}
	}

	function openPanel() {
		panel.hidden = false;
		widget.classList.add( 'is-open' );
		launcher.setAttribute( 'aria-expanded', 'true' );

		if ( badge ) {
			badge.hidden = true;
		}

		greet();
		input.focus();
	}

	function closePanel() {
		panel.hidden = true;
		widget.classList.remove( 'is-open' );
		launcher.setAttribute( 'aria-expanded', 'false' );
	}

	function setBusy( state ) {
		busy = state;

		if ( sendButton ) {
			sendButton.disabled = state;
		}
	}

	function send( message ) {
		if ( busy || ! message ) {
			return;
		}

		setBusy( true );
		pushHistory( 'user', message );
		addRow( 'user', message );

		var typing = addTyping();
		var previous = history.slice( 0, -1 );

		window.fetch( config.rest_url + '/chat', {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': config.nonce
			},
			body: JSON.stringify( {
				message: message,
				history: previous
			} )
		} )
			.then( function ( response ) {
				return response.json().then( function ( payload ) {
					return { ok: response.ok, payload: payload };
				} );
			} )
			.then( function ( result ) {
				if ( typing ) {
					typing.remove();
				}

				var payload = result.payload || {};

				if ( ! result.ok ) {
					addRow( 'agent', payload.message || text( 'error', 'Erro inesperado.' ) );
					return;
				}

				var reply = payload.reply || '';
				addRow( 'agent', reply );
				pushHistory( 'assistant', reply );
				addProducts( payload.products );
				addCheckoutCta( payload.cart );
			} )
			.catch( function () {
				if ( typing ) {
					typing.remove();
				}

				addRow( 'agent', text( 'error', 'Não consegui responder agora.' ) );
			} )
			.then( function () {
				setBusy( false );
			} );
	}

	launcher.addEventListener( 'click', function () {
		if ( panel.hidden ) {
			openPanel();
		} else {
			closePanel();
		}
	} );

	if ( closeButton ) {
		closeButton.addEventListener( 'click', closePanel );
	}

	if ( resetButton ) {
		resetButton.addEventListener( 'click', function () {
			history = [];
			lastProductsKey = '';
			writeHistory();
			messages.textContent = '';
			greeted = false;
			greet();
		} );
	}

	composer.addEventListener( 'submit', function ( event ) {
		event.preventDefault();

		var message = input.value.trim();

		if ( ! message ) {
			return;
		}

		input.value = '';
		input.style.height = 'auto';
		send( message );
	} );

	input.addEventListener( 'input', function () {
		input.style.height = 'auto';
		input.style.height = Math.min( input.scrollHeight, 108 ) + 'px';
	} );

	input.addEventListener( 'keydown', function ( event ) {
		if ( 'Enter' === event.key && ! event.shiftKey ) {
			event.preventDefault();
			composer.dispatchEvent( new window.Event( 'submit', { cancelable: true } ) );
		}
	} );

	document.addEventListener( 'keydown', function ( event ) {
		if ( 'Escape' === event.key && ! panel.hidden ) {
			closePanel();
		}
	} );

	// Auto open once per visit, when the merchant enabled it.
	function maybeAutoOpen() {
		if ( ! config.auto_open || greeted ) {
			return;
		}

		try {
			if ( window.sessionStorage.getItem( STORAGE_KEY + '_auto' ) ) {
				return;
			}

			window.sessionStorage.setItem( STORAGE_KEY + '_auto', '1' );
		} catch ( error ) {
			// Storage unavailable: keep going, it just opens every visit.
		}

		var delay = parseInt( config.auto_open_delay, 10 );

		if ( isNaN( delay ) || delay < 0 ) {
			delay = 6;
		}

		window.setTimeout( function () {
			if ( panel.hidden && ! busy ) {
				openPanel();
			}
		}, delay * 1000 );
	}

	history = readHistory();
	widget.hidden = false;
	renderExisting();

	if ( history.length ) {
		greeted = true;
	}

	if ( shouldReopen() ) {
		openPanel();
	} else {
		maybeAutoOpen();
	}
} )();
