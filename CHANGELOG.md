# 1.0.0 - 18/10/2026
  * New: conversation ledger — one table for conversations and one for messages, with token counts and estimated cost.
  * New: Conversations screen with total spent per conversation, transcript view and delete action.
  * New: Usage and spending settings — input/output price per 1M tokens and an optional maximum spend per conversation.
  * New: the assistant refuses new messages in a conversation after its estimated cost reaches the configured limit.
  * Improved: settings, tutorial and readme now state clearly that the provider account, API key and bill belong to the merchant; the plugin includes no credits and takes no markup.
  * Improved: uninstall now drops the conversation and message tables.

  * New: Free edition for WordPress.org — fully functional basic feature set, no license key and no trial limits.
  * New: AI chat widget with OpenAI as the AI provider.
  * New: Catalogue search, product details and store questions through WooCommerce tools.
  * New: Basic appearance — assistant name, welcome message, icon, colours and widget position.
  * New: Rate limiting per visitor to protect the chat endpoint.
  * New: Filters `fsweb_aic_providers`, `fsweb_aic_settings_defaults`, `fsweb_aic_settings_all`, `fsweb_aic_settings_sanitize`, `fsweb_aic_woo_tools` and `fsweb_aic_woo_tool_result` so the PRO add-on can extend the plugin without touching its code.
  * Note: Advanced features (extra AI providers, add to cart, checkout hand-off, custom avatar, auto-open, custom CSS, automatic updates) live in the separate AI Automated Contact PRO add-on.
