<?php

/**
 * WooCommerce tool set exposed to the AI agent.
 *
 * The agent is a support agent: it may READ the catalogue and ADD items to the
 * cart, and nothing else. Every tool defined here is a whitelisted WordPress
 * function with sanitized arguments — there is no generic "run this" tool, so
 * the model cannot update, delete or publish anything on the site.
 *
 * Only products that are in stock are offered, and products with options
 * (colour, size…) are presented as variations so the customer picks one before
 * anything is added to the cart.
 *
 * @link       https://flowsyncweb.com
 * @since      1.0.0
 *
 * @package    Fsweb_Ai_Automated_Contact
 * @subpackage Fsweb_Ai_Automated_Contact/includes
 */

/**
 * WooCommerce tool provider.
 *
 * @since      1.0.0
 * @package    Fsweb_Ai_Automated_Contact
 * @subpackage Fsweb_Ai_Automated_Contact/includes
 */
class Fsweb_Ai_Automated_Contact_Woo_Tools {

	/**
	 * Whether WooCommerce is available.
	 *
	 * @since  1.0.0
	 * @return bool
	 */
	public function is_available() {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Neutral tool definitions for the provider clients.
	 *
	 * Descriptions are written in English because they are the contract with
	 * the model, not merchant or customer facing copy.
	 *
	 * @since  1.0.0
	 * @return array
	 */
	public function definitions() {
		$settings = Fsweb_Ai_Automated_Contact_Settings::all();

		if ( ! $this->is_available() || 'yes' !== $settings['woo_enabled'] ) {
			return array();
		}

		$tools = array(
			array(
				'name'        => 'search_products',
				'description' => 'Search the store catalogue and returns the products that are in stock, with id, name, price, availability, image, url and a short summary. Pass a term to search by keyword, or omit the term to browse the catalogue (newest first) — use that when the customer asks what the store sells or wants to see options. You can also filter by category. Use order "recent" when the customer asks for new arrivals.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'term'     => array(
							'type'        => 'string',
							'description' => 'Search term in the customer language (e.g. "bluetooth headphone"). Omit it to browse the catalogue instead of searching.',
						),
						'category' => array(
							'type'        => 'string',
							'description' => 'Product category slug or name to restrict the results (e.g. "headphones").',
						),
						'limit'    => array(
							'type'        => 'integer',
							'description' => 'Maximum number of products to return (1 to 10).',
						),
						'order'    => array(
							'type'        => 'string',
							'enum'        => array( 'relevance', 'recent' ),
							'description' => 'relevance (default) or recent for the newest products. Browsing without a term always returns the newest first.',
						),
					),
				),
			),
			array(
				'name'        => 'list_categories',
				'description' => 'List the store product categories with the number of products in each. Use it when the customer asks what the store sells, or to guide them before searching.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(),
				),
			),
			array(
				'name'        => 'product_details',
				'description' => 'Full details of one product: description, stock, price, link, attributes and, for products with options, the list of variations (colour, size…) with their ids, prices and availability. Call this before offering a product that has options.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'product_id' => array(
							'type'        => 'integer',
							'description' => 'Product id.',
						),
					),
					'required'   => array( 'product_id' ),
				),
			),
			array(
				'name'        => 'view_cart',
				'description' => 'Current cart of the customer, with items, total and the cart and checkout links.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(),
				),
			),
			array(
				'name'        => 'store_info',
				'description' => 'General store information: name, currency, available payment methods and store url. Use it for general questions.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(),
				),
			),
		);

		$tools[] = array(
			'name'        => 'send_checkout',
			'description' => 'Hand the checkout over to the customer: the chat shows a checkout button. Call it ONLY when the customer says they are done shopping — never right after adding an item — and never paste the link in your message.',
			'parameters'  => array(
				'type'       => 'object',
				'properties' => array(),
			),
		);

		if ( 'yes' === $settings['allow_add_to_cart'] ) {
			$tools[] = array(
				'name'        => 'add_to_cart',
				'description' => 'Add a product to the customer cart. Use it ONLY after the customer confirms the item. For products with options, pass the chosen variation_id — if the product has options and no variation_id is given, the tool answers with the available variations so you can ask the customer.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'product_id'   => array(
							'type'        => 'integer',
							'description' => 'Product id to add.',
						),
						'variation_id' => array(
							'type'        => 'integer',
							'description' => 'Variation id, when the product has options.',
						),
						'quantity'     => array(
							'type'        => 'integer',
							'description' => 'Quantity (1 to 10). Defaults to 1.',
						),
					),
					'required'   => array( 'product_id' ),
				),
			);
		}

		return $tools;
	}

	/**
	 * Execute a tool call.
	 *
	 * @since  1.0.0
	 * @param  string $name Tool name.
	 * @param  array  $args Arguments from the model.
	 * @return array<string,mixed> Result payload (always serializable).
	 */
	public function execute( $name, $args ) {
		$args = is_array( $args ) ? $args : array();

		switch ( $name ) {
			case 'search_products':
				return $this->search_products( $args );
			case 'list_categories':
				return $this->list_categories();
			case 'product_details':
				return $this->product_details( $args );
			case 'add_to_cart':
				return $this->add_to_cart( $args );
			case 'view_cart':
				return $this->cart_payload();
			case 'send_checkout':
				return $this->checkout_payload();
			case 'store_info':
				return $this->store_info();
			default:
				return array(
					'error' => 'unknown_tool',
				);
		}
	}

	/**
	 * Search published products that are in stock.
	 *
	 * @since  1.0.0
	 * @param  array $args Tool arguments.
	 * @return array<string,mixed>
	 */
	private function search_products( array $args ) {
		$term     = isset( $args['term'] ) ? sanitize_text_field( $args['term'] ) : '';
		$category = isset( $args['category'] ) ? sanitize_text_field( $args['category'] ) : '';
		$limit    = isset( $args['limit'] ) ? absint( $args['limit'] ) : (int) Fsweb_Ai_Automated_Contact_Settings::get( 'product_limit', 5 );
		$limit    = max( 1, min( 10, $limit ) );
		$order    = isset( $args['order'] ) ? sanitize_key( $args['order'] ) : 'relevance';

		$query_args = array(
			'post_type'           => 'product',
			'post_status'         => 'publish',
			'posts_per_page'      => $limit,
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
			// Never offer something the customer cannot buy.
			'meta_query'          => array(
				array(
					'key'   => '_stock_status',
					'value' => 'instock',
				),
			),
		);

		if ( '' !== $term ) {
			$query_args['s'] = $term;
		}

		// No keyword means "show me the catalogue": newest first.
		if ( '' === $term || 'recent' === $order ) {
			$query_args['orderby'] = 'date';
			$query_args['order']   = 'DESC';
		}

		if ( '' !== $category ) {
			$term_object = get_term_by( 'slug', sanitize_title( $category ), 'product_cat' );

			if ( ! $term_object ) {
				$term_object = get_term_by( 'name', $category, 'product_cat' );
			}

			if ( $term_object ) {
				$query_args['tax_query'] = array(
					array(
						'taxonomy' => 'product_cat',
						'field'    => 'term_id',
						'terms'    => array( (int) $term_object->term_id ),
					),
				);
			}
		}

		$query    = new WP_Query( $query_args );
		$products = array();

		foreach ( $query->posts as $post ) {
			$product = function_exists( 'wc_get_product' ) ? wc_get_product( $post->ID ) : null;

			if ( ! $product || ! $product->is_visible() || ! $product->is_in_stock() ) {
				continue;
			}

			$products[] = $this->summarize( $product );
		}

		wp_reset_postdata();

		if ( empty( $products ) ) {
			$categories = $this->list_categories();

			return array(
				'found'      => 0,
				'message'    => '' === $term
					? __( 'No product in stock for this filter.', 'fsweb-ai-automated-contact' )
					: __( 'No product found for that term. Try another word.', 'fsweb-ai-automated-contact' ),
				'categories' => isset( $categories['categories'] ) ? $categories['categories'] : array(),
			);
		}

		return array(
			'found'    => count( $products ),
			'order'    => '' === $term ? 'recent' : $order,
			'products' => $products,
		);
	}

	/**
	 * Product categories with how many products each one holds.
	 *
	 * @since  1.0.0
	 * @return array<string,mixed>
	 */
	private function list_categories() {
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => true,
				'number'     => 15,
				'orderby'    => 'count',
				'order'      => 'DESC',
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array(
				'found'      => 0,
				'categories' => array(),
			);
		}

		$categories = array();

		foreach ( $terms as $term ) {
			// Skip the WooCommerce catch-all bucket.
			if ( 'uncategorized' === $term->slug ) {
				continue;
			}

			$categories[] = array(
				'name'  => wp_strip_all_tags( $term->name ),
				'slug'  => $term->slug,
				'total' => (int) $term->count,
			);
		}

		return array(
			'found'      => count( $categories ),
			'categories' => $categories,
		);
	}

	/**
	 * Details for a single product, variations included.
	 *
	 * @since  1.0.0
	 * @param  array $args Tool arguments.
	 * @return array<string,mixed>
	 */
	private function product_details( array $args ) {
		$product_id = isset( $args['product_id'] ) ? absint( $args['product_id'] ) : 0;
		$product    = $product_id && function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;

		if ( ! $product || 'publish' !== get_post_status( $product_id ) ) {
			return array(
				'error'   => 'product_not_found',
				'message' => __( 'Product not found.', 'fsweb-ai-automated-contact' ),
			);
		}

		$data = $this->summarize( $product );

		$data['description'] = wp_strip_all_tags( (string) $product->get_short_description() );
		$data['categories']  = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'names' ) );
		$data['attributes']  = $this->product_attributes( $product );
		$data['reviews']     = (int) $product->get_review_count();

		if ( is_wp_error( $data['categories'] ) ) {
			$data['categories'] = array();
		}

		if ( $product->is_type( 'variable' ) ) {
			$data['variations'] = $this->variations_payload( $product );
			$data['has_options'] = true;
		}

		return $data;
	}

	/**
	 * Add a product (or a chosen variation) to the customer cart.
	 *
	 * @since  1.0.0
	 * @param  array $args Tool arguments.
	 * @return array<string,mixed>
	 */
	private function add_to_cart( array $args ) {
		$settings = Fsweb_Ai_Automated_Contact_Settings::all();

		if ( 'yes' !== $settings['allow_add_to_cart'] ) {
			return array(
				'error'   => 'cart_disabled',
				'message' => __( 'Adding to the cart is disabled in this store.', 'fsweb-ai-automated-contact' ),
			);
		}

		$product_id   = isset( $args['product_id'] ) ? absint( $args['product_id'] ) : 0;
		$variation_id = isset( $args['variation_id'] ) ? absint( $args['variation_id'] ) : 0;
		$quantity     = isset( $args['quantity'] ) ? absint( $args['quantity'] ) : 1;
		$quantity     = max( 1, min( 10, $quantity ) );

		if ( ! function_exists( 'wc_get_product' ) || ! $product_id ) {
			return array(
				'error'   => 'invalid_product',
				'message' => __( 'Invalid product.', 'fsweb-ai-automated-contact' ),
			);
		}

		$product = wc_get_product( $product_id );

		if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
			return array(
				'error'   => 'product_unavailable',
				'message' => __( 'This product is not available for purchase right now.', 'fsweb-ai-automated-contact' ),
			);
		}

		// A variation id only exists for products with options. A stray value
		// (the model often sends one) makes WooCommerce reject the whole add,
		// which looked like "the cart refuses the second product".
		if ( ! $product->is_type( 'variable' ) ) {
			$variation_id = 0;
		}

		// Products with options: the customer must pick one first.
		if ( $product->is_type( 'variable' ) ) {
			$variations = $this->variations_payload( $product );

			if ( ! $variation_id ) {
				return array(
					'error'      => 'choose_variation',
					'message'    => __( 'This product has options. Show them to the customer, ask which one they want, then add the chosen variation.', 'fsweb-ai-automated-contact' ),
					'product_id' => $product_id,
					'options'    => $variations,
					'url'        => get_permalink( $product_id ),
				);
			}

			$variation = wc_get_product( $variation_id );

			if ( ! $variation
				|| (int) $variation->get_parent_id() !== $product_id
				|| ! $variation->is_purchasable()
				|| ! $variation->is_in_stock() ) {
				return array(
					'error'   => 'variation_unavailable',
					'message' => __( 'That option is not available right now. Offer another one from the list.', 'fsweb-ai-automated-contact' ),
					'options' => $variations,
				);
			}
		}

		$added = false;

		if ( function_exists( 'wc_load_cart' ) && ( ! function_exists( 'WC' ) || ! WC()->cart ) ) {
			wc_load_cart();
		}

		$notices = array();

		if ( function_exists( 'WC' ) && WC()->cart ) {
			$key = WC()->cart->add_to_cart( $product_id, $quantity, $variation_id );

			if ( $key ) {
				$added = true;
			} else {
				$notices = function_exists( 'wc_get_notices' ) ? wc_get_notices( 'error' ) : array();

				error_log(
					sprintf(
						'[FSWEB AIC] add_to_cart falhou: produto=%d variacao=%d qtd=%d | %s',
						$product_id,
						$variation_id,
						$quantity,
						wp_json_encode( $notices )
					)
				);

				if ( function_exists( 'wc_clear_notices' ) ) {
					wc_clear_notices();
				}
			}
		}

		$cart = $this->cart_payload();

		$cart['added']   = $added;
		$cart['product'] = $this->summarize( $variation_id ? wc_get_product( $variation_id ) : $product );

		if ( ! $added ) {
			// The REST session has no cart: give the client a direct link.
			$cart['fallback_url'] = add_query_arg(
				array(
					'add-to-cart' => $product_id,
					'quantity'    => $quantity,
				),
				function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/' )
			);
			$cart['message'] = __( 'The cart could not be changed from the chat. Send the link so the customer can add the product.', 'fsweb-ai-automated-contact' );

			if ( ! empty( $notices ) ) {
				$cart['notices'] = $notices;
			}
		} else {
			$cart['message'] = __( 'Product added to the cart.', 'fsweb-ai-automated-contact' );
		}

		return $cart;
	}

	/**
	 * Checkout payload: only used when the customer is finished shopping.
	 *
	 * @since  1.0.0
	 * @return array<string,mixed>
	 */
	private function checkout_payload() {
		$cart = $this->cart_payload();

		return array(
			'message'       => __( 'Ready to finish the purchase.', 'fsweb-ai-automated-contact' ),
			'checkout_url'  => $cart['checkout_url'],
			'show_checkout' => true,
			'count'         => $cart['count'],
			'total'         => $cart['total'],
		);
	}

	/**
	 * General store information.
	 *
	 * @since  1.0.0
	 * @return array<string,mixed>
	 */
	private function store_info() {
		$data = array(
			'store'    => get_bloginfo( 'name' ),
			'url'      => home_url( '/' ),
			'currency' => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '',
			'language' => get_bloginfo( 'language' ),
			'contact'  => sanitize_email( get_option( 'admin_email' ) ),
		);

		if ( function_exists( 'WC' ) && WC()->payment_gateways() ) {
			$methods = array();

			foreach ( WC()->payment_gateways()->get_available_payment_gateways() as $gateway ) {
				$methods[] = wp_strip_all_tags( $gateway->get_title() );
			}

			$data['payments'] = array_values( array_unique( $methods ) );
		}

		return $data;
	}

	/**
	 * Cart payload.
	 *
	 * @since  1.0.0
	 * @return array<string,mixed>
	 */
	private function cart_payload() {
		$payload = array(
			'items'        => array(),
			'count'        => 0,
			'total'        => '',
			'cart_url'     => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : '',
			'checkout_url' => function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : '',
		);

		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return $payload;
		}

		foreach ( WC()->cart->get_cart() as $item ) {
			$product = isset( $item['data'] ) ? $item['data'] : null;

			$payload['items'][] = array(
				'product_id' => isset( $item['product_id'] ) ? (int) $item['product_id'] : 0,
				'name'       => $product ? $product->get_name() : '',
				'quantity'   => isset( $item['quantity'] ) ? (int) $item['quantity'] : 0,
				'subtotal'   => function_exists( 'wc_price' ) && $product ? $this->plain_text( wc_price( (float) $item['line_subtotal'] ) ) : '',
			);
		}

		$payload['count'] = (int) WC()->cart->get_cart_contents_count();
		$payload['total'] = $this->plain_text( WC()->cart->get_cart_total() );

		return $payload;
	}

	/**
	 * Turn a WooCommerce price HTML string into plain readable text.
	 *
	 * `wp_strip_all_tags()` leaves HTML entities behind — the currency symbol
	 * arrives as `&#82;&#36;` and the card ended up showing that literally.
	 *
	 * @since  1.0.0
	 * @param  string $html Price HTML.
	 * @return string
	 */
	private function plain_text( $html ) {
		$text = wp_strip_all_tags( (string) $html );
		$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
		$text = preg_replace( '/\xC2\xA0|\x{00A0}/u', ' ', $text );
		$text = preg_replace( '/\s+/u', ' ', $text );

		return trim( (string) $text );
	}

	/**
	 * Compact product payload used inside tool results.
	 *
	 * @since  1.0.0
	 * @param  \WC_Product|null $product Product object.
	 * @return array<string,mixed>
	 */
	private function summarize( $product ) {
		if ( ! $product || ! is_object( $product ) ) {
			return array();
		}

		$image_id = (int) $product->get_image_id();

		return array(
			'product_id' => (int) $product->get_id(),
			'name'       => wp_strip_all_tags( $product->get_name() ),
			'price'      => $this->plain_text( $product->get_price_html() ),
			'available'  => $product->is_in_stock(),
			'url'        => get_permalink( $product->get_id() ),
			'image'      => $image_id ? wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' ) : '',
			'summary'    => wp_trim_words( wp_strip_all_tags( (string) $product->get_short_description() ), 24 ),
			'on_sale'    => $product->is_on_sale(),
		);
	}

	/**
	 * Purchasable variations of a product with options.
	 *
	 * @since  1.0.0
	 * @param  \WC_Product $product Variable product.
	 * @return array<int,array<string,mixed>>
	 */
	private function variations_payload( $product ) {
		$variations = array();

		foreach ( $product->get_children() as $child_id ) {
			$variation = wc_get_product( $child_id );

			if ( ! $variation || ! $variation->is_purchasable() || ! $variation->is_in_stock() ) {
				continue;
			}

			$variations[] = array(
				'variation_id' => (int) $child_id,
				'label'        => wp_strip_all_tags( wc_get_formatted_variation( $variation, true ) ),
				'price'        => $this->plain_text( $variation->get_price_html() ),
				'available'    => true,
			);
		}

		return $variations;
	}

	/**
	 * Visible attributes of a product.
	 *
	 * @since  1.0.0
	 * @param  \WC_Product $product Product object.
	 * @return array<string,string>
	 */
	private function product_attributes( $product ) {
		$out = array();

		foreach ( $product->get_attributes() as $attribute ) {
			if ( ! is_object( $attribute ) || ! method_exists( $attribute, 'get_name' ) ) {
				continue;
			}

			$name = wc_attribute_label( $attribute->get_name() );
			$raw  = $attribute->is_taxonomy()
				? wp_get_post_terms( $product->get_id(), $attribute->get_name(), array( 'fields' => 'names' ) )
				: $attribute->get_options();

			if ( is_wp_error( $raw ) ) {
				continue;
			}

			$out[ $name ] = implode( ', ', array_map( 'wp_strip_all_tags', (array) $raw ) );
		}

		return $out;
	}
}
