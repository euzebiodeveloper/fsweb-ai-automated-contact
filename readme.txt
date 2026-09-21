=== AI Automated Contact ===
Contributors: flowsyncweb
Donate link: https://flowsyncweb.com/
Tags: woocommerce, chat, ai, atendimento, openai
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Atendente virtual com IA para WooCommerce: tira dúvidas, encontra produtos, monta o carrinho e leva o cliente ao checkout.

== Description ==

O AI Automated Contact coloca um atendente virtual com IA na sua loja WooCommerce. O cliente abre o chat, descreve o que procura (por exemplo, "fone de ouvido"), e a IA consulta o catálogo, apresenta os produtos com preço e disponibilidade, adiciona ao carrinho depois de confirmar e envia o cliente direto para a página de finalizar compra.

Além das vendas, o atendente responde dúvidas sobre a loja — prazos, frete, pagamento, trocas — usando o contexto que você mesmo escreve no painel.

**Principais recursos**

* Vários provedores de IA: OpenAI (ChatGPT), Anthropic (Claude), DeepSeek, xAI (Grok) e Groq.
* Lista de modelos pré-selecionada por provedor, com campo livre para qualquer modelo específico.
* Qualquer endpoint compatível com OpenAI (base URL + chave + modelo) pelo provedor "Outro".
* Contexto da loja editável — você escreve e a IA obedece.
* Integração WooCommerce: busca de produtos, detalhes, atributos, variações, carrinho e checkout.
* Atuação segura: o atendente trabalha com consulta ao catálogo e ao carrinho do próprio visitante.
* Widget de chat flutuante com cor, posição, nome do atendente e mensagem de boas-vindas configuráveis.
* Chaves de API ficam no servidor — nunca são expostas ao navegador do visitante.
* Limite de mensagens por visitante contra abuso.
* Licença com ativação por domínio e atualizações automáticas.

== Installation ==

1. Envie a pasta `fsweb-ai-automated-contact` para `/wp-content/plugins/` (ou instale o ZIP pelo painel).
2. Ative o plugin em *Plugins*.
3. Informe sua chave de licença na tela de boas-vindas.
4. Em *AI Contact*, escolha o provedor, cole a chave de API, selecione o modelo e escreva o contexto da loja.
5. Marque **Exibir o chat no site** e salve.

== Frequently Asked Questions ==

= Como o atendente trabalha com o catálogo? =

Ele consulta produtos, preços e disponibilidade em tempo real, confirma com o cliente e então adiciona o item ao carrinho. Não há ferramentas de criação, edição ou exclusão de conteúdo.

= Preciso do WooCommerce? =

O WooCommerce é necessário para busca de produtos e carrinho. Sem ele o atendente continua funcionando para dúvidas gerais, mas sem acessar o catálogo.

= Onde ficam minhas chaves de API? =

No banco de dados do seu WordPress (`wp_options`). Elas nunca são enviadas ao navegador: o widget conversa apenas com o REST do próprio plugin.

= O histórico da conversa é salvo no servidor? =

Não. O histórico fica no `sessionStorage` do navegador do visitante e é enviado junto de cada mensagem apenas para dar contexto ao modelo.

== Changelog ==

= 1.0.0 =
* Versão inicial.
* Atendente virtual com IA, multi-provedor.
* Integração WooCommerce (leitura + carrinho).
* Widget público configurável.
* Sistema de licença e atualizações automáticas.

== Upgrade Notice ==

= 1.0.0 =
Primeira versão pública.
