# Changelog

Todas as mudanças relevantes deste plugin são documentadas aqui.

## 1.0.0

### Adicionado

- Atendente virtual com IA multi-provedor: OpenAI, Anthropic (Claude), DeepSeek, xAI (Grok), Groq e endpoints compatíveis com OpenAI.
- Lista de modelos pré-selecionada por provedor, com campo livre para modelo específico.
- Contexto da loja editável pelo lojista (instruções prioritárias para a IA).
- Integração WooCommerce: busca de produtos, detalhes, atributos, variações, carrinho e link direto para o checkout.
- Ferramentas em modo somente leitura + adição ao carrinho, sem qualquer função de escrita no site.
- Widget de chat público com cor, posição, nome do atendente e mensagem de boas-vindas configuráveis.
- Endpoints REST próprios (`fsweb-aic/v1`) com nonce e limite de mensagens por visitante.
- Sistema de licença por domínio com tela de onboarding, verificação e desativação.
- FSWEB Update Checker integrado (classe e namespace próprios) para atualizações automáticas.
