# 1.1.0 - 26/09/2026
  * Fix: A ativação, a validação e a desativação da licença passam a falar com a API nova de licenças (`https://api.flowsyncweb.com/api/license`) em vez do bridge legado do License Manager for WooCommerce, que respondia "License Manager for WooCommerce is not active.".
  * Fix: O corpo da resposta volta a ser a fonte da verdade — rejeição de negócio (success: false) aparece com a mensagem que a API manda, e falha de transporte (WP_Error ou 5xx) mantém o estado atual da licença e devolve um aviso neutro, sem alarmar o lojista.

# 1.0.1 - 26/09/2026
  * New: Telas de configurações, tutorial, onboarding e licença padronizadas com hero, ações rápidas, cards, pills e botões com ícone.
  * New: Tradução pt_BR completa das telas padronizadas; a fonte ficou 100% em inglês, inclusive os rótulos que o JS reescreve.
  * New: Documentação em inglês — README.md com badges, recursos e requisitos, readme com screenshots, banner e ícone do plugin.
  * Fix: O parser do readme aceita `readme.txt` em minúsculo e o "Ver detalhes" volta a mostrar todas as abas.
  * Fix: O bloco de licença do onboarding ganhou o id que o JS usa para fechar e a ação manual de verificar licença saiu da tela de licença.

# 1.0.0 - 20/09/2026
  * New: Atendente virtual com IA multi-provedor (OpenAI, Anthropic, DeepSeek, xAI e Groq).
  * New: Catálogo de modelos por provedor, com campo livre para modelo específico.
  * New: Contexto da loja editável, com prompt padrão de atendimento pré-preenchido.
  * New: Integração com o WooCommerce — busca de produtos, detalhes, variações, carrinho e link do checkout.
  * New: Widget público de chat com cor, posição, nome do atendente e mensagem de boas-vindas configuráveis.
  * New: Endpoints REST próprios com nonce e limite de mensagens por visitante.
  * New: Licença por domínio com onboarding, verificação e atualização automática.
