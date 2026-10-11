# 1.0.6 - 11/10/2026
  * Fix: Licença inválida ou expirada agora é reportada como tal — o aviso do admin não mostra mais o genérico "servidor de atualizações temporariamente inacessível". Com uma chave preenchida e recusa 401/403, o aviso passa a "credencial de licença inválida ou expirada"; o aviso genérico de servidor some enquanto houver chave.
  * Fix: O verificador de atualizações não apaga mais o erro de licença a cada carregamento — a recusa era escondida por um erro de servidor antigo em vez de aparecer; o erro só é limpo quando a chave muda ou após uma verificação bem-sucedida.

# 1.0.5 - 26/09/2026
  * Fix: O sync da licença ao abrir a tela passa a olhar `domain_authorized` em vez do `status` da licença. Desativar a licença neste domínio mantém o status local em `pending` e a tela não volta mais para "Ativa" a cada recarregamento.
  * Fix: `expired`/`invalid` continuam sendo o veredito da própria licença e passam como vêm da API; falha de transporte segue sem mexer no estado guardado.
  * Fix: A desativação limpa o cache/transient do estado da licença (opções do site e cache do verificador de atualizações), para nenhuma cópia antiga responder "active" depois.

# 1.0.4 - 26/09/2026
  * Fix: O placeholder do campo da chave de licença passa a mostrar o formato real — 32 caracteres em 4 grupos de 8, com alfabeto sem 0/O/1/I/L — em vez do exemplo aposentado `FSWEB-XXXX-XXXX-XXXX`.
  * Fix: A data de expiração da licença é formatada em UTC e nunca mais aparece como a string ISO crua; o valor não escorrega um dia para trás e fica igual no primeiro render e depois do AJAX/refresh.
  * Fix: Tradução pt_BR atualizada com o novo placeholder da chave.

# 1.0.3 - 26/09/2026
  * Fix: License activation and deactivation no longer depend on another FSWEB plugin's constants — each plugin now resolves the license API URL from its own constant, so an outdated sibling plugin can no longer point this one at the retired endpoint.

# 1.0.2 - 26/09/2026
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
