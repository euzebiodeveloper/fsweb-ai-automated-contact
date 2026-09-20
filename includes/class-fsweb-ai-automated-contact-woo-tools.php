<?php

/**
 * WooCommerce tool set exposed to the AI agent.
 *
 * The agent is a support agent: it may READ the catalogue and ADD items to the
 * cart, and nothing else. Every tool defined here is a whitelisted wordpress
 * function with sanitized arguments — there is no generic "run this" tool, so
 * the model cannot update, delete or publish anything on the site.
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
	 * @since  1.0.0
	 * @return array
	 */
	public function definitions() {
		$settings = Fsweb_Ai_Automated_Contact_Settings::all();

		if ( ! $this->is_available() || 'yes' !== $settings['woo_enabled'] ) {
			return array();
		}

		$limit = (int) $settings['product_limit'];

		$tools = array(
			array(
				'name'        => 'buscar_produtos',
				'description' => 'Busca produtos na loja por termo, nome ou palavra-chave. Use sempre que o cliente mencionar um produto que quer encontrar. Retorna id, nome, preço formatado, disponibilidade, link e resumo.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'termo' => array(
							'type'        => 'string',
							'description' => 'Termo de busca em português (ex.: "fone de ouvido bluetooth").',
						),
						'limite' => array(
							'type'        => 'integer',
							'description' => 'Quantidade máxima de produtos a retornar (1 a 10).',
						),
					),
					'required'   => array( 'termo' ),
				),
			),
			array(
				'name'        => 'detalhes_produto',
				'description' => 'Retorna descrição, variações, estoque, preço e link de um produto específico pelo id.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'produto_id' => array(
							'type'        => 'integer',
							'description' => 'ID do produto.',
						),
					),
					'required'   => array( 'produto_id' ),
				),
			),
			array(
				'name'        => 'ver_carrinho',
				'description' => 'Retorna o conteúdo atual do carrinho do cliente, o total e os links do carrinho e do checkout.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(),
				),
			),
			array(
				'name'        => 'info_da_loja',
				'description' => 'Retorna informações gerais da loja: nome, moeda, formas de pagamento habilitadas e endereço da loja. Use para dúvidas gerais.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(),
				),
			),
		);

		if ( 'yes' === $settings['allow_add_to_cart'] ) {
			$tools[] = array(
				'name'        => 'adicionar_ao_carrinho',
				'description' => 'Adiciona um produto ao carrinho do cliente. Use SOMENTE depois de o cliente confirmar que quer adicionar. Retorna o carrinho atualizado e o link para finalizar a compra.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'produto_id' => array(
							'type'        => 'integer',
							'description' => 'ID do produto a adicionar.',
						),
						'quantidade' => array(
							'type'        => 'integer',
							'description' => 'Quantidade (1 a 10). Padrão 1.',
						),
					),
					'required'   => array( 'produto_id' ),
				),
			);
		}

		unset( $limit );

		return $tools;
	}

	/**
	 * Execute a tool call.
	 *
	 * @since  1.0.0
	 * @param  string $name Tool name.
	 * @param  array  $args Sanitized-by-contract arguments from the model.
	 * @return array<string,mixed> Result payload (always serializable).
	 */
	public function execute( $name, $args ) {
		$args = is_array( $args ) ? $args : array();

		switch ( $name ) {
			case 'buscar_produtos':
				return $this->search_products( $args );
			case 'detalhes_produto':
				return $this->product_details( $args );
			case 'adicionar_ao_carrinho':
				return $this->add_to_cart( $args );
			case 'ver_carrinho':
				return $this->view_cart();
			case 'info_da_loja':
				return $this->store_info();
			default:
				return array(
					'erro' => 'ferramenta_desconhecida',
				);
		}
	}

	/**
	 * Search published products.
	 *
	 * @since  1.0.0
	 * @param  array $args Tool arguments.
	 * @return array<string,mixed>
	 */
	private function search_products( array $args ) {
		$term  = isset( $args['termo'] ) ? sanitize_text_field( $args['termo'] ) : '';
		$limit = isset( $args['limite'] ) ? absint( $args['limite'] ) : (int) Fsweb_Ai_Automated_Contact_Settings::get( 'product_limit', 5 );
		$limit = max( 1, min( 10, $limit ) );

		if ( '' === $term ) {
			return array(
				'erro'    => 'termo_obrigatorio',
				'mensagem' => __( 'Informe um termo de busca.', 'fsweb-ai-automated-contact' ),
			);
		}

		$query = new WP_Query(
			array(
				'post_type'           => 'product',
				'post_status'         => 'publish',
				's'                   => $term,
				'posts_per_page'      => $limit,
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
			)
		);

		$products = array();

		foreach ( $query->posts as $post ) {
			$product = function_exists( 'wc_get_product' ) ? wc_get_product( $post->ID ) : null;

			if ( ! $product || ! $product->is_visible() ) {
				continue;
			}

			$products[] = $this->summarize( $product );
		}

		wp_reset_postdata();

		if ( empty( $products ) ) {
			return array(
				'encontrados' => 0,
				'mensagem'    => __( 'Nenhum produto encontrado para esse termo. Tente outra palavra.', 'fsweb-ai-automated-contact' ),
			);
		}

		return array(
			'encontrados' => count( $products ),
			'produtos'    => $products,
		);
	}

	/**
	 * Details for a single product.
	 *
	 * @since  1.0.0
	 * @param  array $args Tool arguments.
	 * @return array<string,mixed>
	 */
	private function product_details( array $args ) {
		$product_id = isset( $args['produto_id'] ) ? absint( $args['produto_id'] ) : 0;
		$product    = $product_id && function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;

		if ( ! $product || 'publish' !== get_post_status( $product_id ) ) {
			return array(
				'erro'     => 'produto_nao_encontrado',
				'mensagem' => __( 'Produto não encontrado.', 'fsweb-ai-automated-contact' ),
			);
		}

		$data                = $this->summarize( $product );
		$data['descricao']   = wp_strip_all_tags( (string) $product->get_short_description() );
		$data['categorias']  = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'names' ) );
		$data['atributos']   = $this->product_attributes( $product );
		$data['avaliacoes']  = (int) $product->get_review_count();

		if ( is_wp_error( $data['categorias'] ) ) {
			$data['categorias'] = array();
		}

		return $data;
	}

	/**
	 * Add a product to the WooCommerce cart of the current session.
	 *
	 * Returns the cart contents plus the cart and checkout permalinks, so the
	 * widget can send the customer straight to checkout.
	 *
	 * @since  1.0.0
	 * @param  array $args Tool arguments.
	 * @return array<string,mixed>
	 */
	private function add_to_cart( array $args ) {
		$settings = Fsweb_Ai_Automated_Contact_Settings::all();

		if ( 'yes' !== $settings['allow_add_to_cart'] ) {
			return array(
				'erro'     => 'adicao_desabilitada',
				'mensagem' => __( 'A adição ao carrinho está desabilitada nesta loja.', 'fsweb-ai-automated-contact' ),
			);
		}

		$product_id = isset( $args['produto_id'] ) ? absint( $args['produto_id'] ) : 0;
		$quantity   = isset( $args['quantidade'] ) ? absint( $args['quantidade'] ) : 1;
		$quantity   = max( 1, min( 10, $quantity ) );

		if ( ! function_exists( 'wc_get_product' ) || ! $product_id ) {
			return array(
				'erro'     => 'produto_invalido',
				'mensagem' => __( 'Produto inválido.', 'fsweb-ai-automated-contact' ),
			);
		}

		$product = wc_get_product( $product_id );

		if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
			return array(
				'erro'     => 'produto_indisponivel',
				'mensagem' => __( 'Este produto não está disponível para compra no momento.', 'fsweb-ai-automated-contact' ),
			);
		}

		if ( $product->is_type( 'variable' ) ) {
			return array(
				'erro'        => 'produto_variavel',
				'mensagem'    => __( 'Este produto tem variações. Envie o link do produto para o cliente escolher a variação.', 'fsweb-ai-automated-contact' ),
				'produto_id'  => $product_id,
				'link'        => get_permalink( $product_id ),
			);
		}

		$added = false;

		if ( function_exists( 'wc_load_cart' ) && ( ! function_exists( 'WC' ) || ! WC()->cart ) ) {
			wc_load_cart();
		}

		if ( function_exists( 'WC' ) && WC()->cart ) {
			$key = WC()->cart->add_to_cart( $product_id, $quantity );

			if ( $key ) {
				$added = true;
			}
		}

		$cart = $this->cart_payload();

		$cart['adicionado'] = $added;
		$cart['produto']    = $this->summarize( $product );

		if ( ! $added ) {
			// The REST session has no cart: give the client a direct link.
			$cart['fallback_url'] = add_query_arg(
				array(
					'add-to-cart' => $product_id,
					'quantity'    => $quantity,
				),
				function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/' )
			);
			$cart['mensagem']     = __( 'Não foi possível alterar o carrinho pelo chat. Envie o link para o cliente adicionar o produto.', 'fsweb-ai-automated-contact' );
		} else {
			$cart['mensagem'] = __( 'Produto adicionado ao carrinho.', 'fsweb-ai-automated-contact' );
		}

		return $cart;
	}

	/**
	 * Current cart contents.
	 *
	 * @since  1.0.0
	 * @return array<string,mixed>
	 */
	private function view_cart() {
		return $this->cart_payload();
	}

	/**
	 * General store information.
	 *
	 * @since  1.0.0
	 * @return array<string,mixed>
	 */
	private function store_info() {
		$data = array(
			'loja'    => get_bloginfo( 'name' ),
			'url'     => home_url( '/' ),
			'moeda'   => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '',
			'idioma'  => get_bloginfo( 'language' ),
			'contato' => sanitize_email( get_option( 'admin_email' ) ),
		);

		if ( function_exists( 'WC' ) && WC()->payment_gateways() ) {
			$methods = array();
			foreach ( WC()->payment_gateways()->get_available_payment_gateways() as $gateway ) {
				$methods[] = wp_strip_all_tags( $gateway->get_title() );
			}
			$data['pagamentos'] = array_values( array_unique( $methods ) );
		}

		return $data;
	}

	/**
	 * Cart payload shared by the cart and add-to-cart tools.
	 *
	 * @since  1.0.0
	 * @return array<string,mixed>
	 */
	private function cart_payload() {
		$payload = array(
			'itens'        => array(),
			'quantidade'   => 0,
			'total'        => '',
			'carrinho_url' => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : '',
			'checkout_url' => function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : '',
		);

		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return $payload;
		}

		foreach ( WC()->cart->get_cart() as $item ) {
			$product = isset( $item['data'] ) ? $item['data'] : null;

			$payload['itens'][] = array(
				'produto_id' => isset( $item['product_id'] ) ? (int) $item['product_id'] : 0,
				'nome'       => $product ? $product->get_name() : '',
				'quantidade' => isset( $item['quantity'] ) ? (int) $item['quantity'] : 0,
				'subtotal'   => function_exists( 'wc_price' ) && $product ? wp_strip_all_tags( wc_price( (float) $item['line_subtotal'] ) ) : '',
			);
		}

		$payload['quantidade'] = (int) WC()->cart->get_cart_contents_count();
		$payload['total']      = wp_strip_all_tags( WC()->cart->get_cart_total() );

		return $payload;
	}

	/**
	 * Compact product payload used inside tool results.
	 *
	 * @since  1.0.0
	 * @param  \WC_Product $product Product object.
	 * @return array<string,mixed>
	 */
	private function summarize( $product ) {
		$image_id = (int) $product->get_image_id();

		return array(
			'produto_id'  => (int) $product->get_id(),
			'nome'        => wp_strip_all_tags( $product->get_name() ),
			'preco'       => wp_strip_all_tags( $product->get_price_html() ),
			'disponivel'  => $product->is_in_stock(),
			'link'        => get_permalink( $product->get_id() ),
			'imagem'      => $image_id ? wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' ) : '',
			'resumo'      => wp_trim_words( wp_strip_all_tags( (string) $product->get_short_description() ), 24 ),
			'em_promocao' => $product->is_on_sale(),
		);
	}

	/**
	 * Visible attributes of a product (variations included).
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

		if ( $product->is_type( 'variable' ) ) {
			$variations = array();

			foreach ( $product->get_children() as $child_id ) {
				$variation = wc_get_product( $child_id );

				if ( $variation && $variation->is_purchasable() ) {
					$variations[] = array(
						'variacao_id' => (int) $child_id,
						'nome'        => wp_strip_all_tags( wc_get_formatted_variation( $variation, true ) ),
						'preco'       => wp_strip_all_tags( $variation->get_price_html() ),
						'disponivel'  => $variation->is_in_stock(),
					);
				}
			}

			$out['variacoes'] = $variations;
		}

		return $out;
	}
}
