=== FSWEB AI Automated Contact ===
Contributors: flowsyncweb
Donate link: https://flowsyncweb.com/
Tags: woocommerce, chat, ai, virtual assistant, customer support
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered virtual assistant for WooCommerce: answers questions, finds products and guides the customer to the cart and checkout.

== Description ==

> <strong>FSWEB AI Automated Contact PRO</strong><br />
> This plugin is the free, fully functional version: the assistant name, welcome message, privacy note, temperature, token and message limits, the colour palette and everything below are editable here. The separate PRO add-on adds a custom avatar image, auto-open with delay, custom CSS and automatic updates. [Get the PRO version](https://flowsyncweb.com/produto/ai-automated-contact-pro/)

FSWEB AI Automated Contact puts an AI virtual assistant on your store. A visitor opens the chat, describes what they are looking for, and the assistant searches the catalogue, shows the products with price and availability, answers questions about delivery, payment and returns, and walks the customer to the cart and the checkout.

You bring the AI: connect your own account with OpenAI, Anthropic (Claude), DeepSeek, xAI (Grok), Groq, or any OpenAI-compatible endpoint. There is no shared key, no proxy and no markup — the requests are billed directly by the provider you choose.

= How it works =

1. The visitor clicks the chat button in the corner of the site.
2. They type what they need, in their own words.
3. The assistant searches the catalogue and answers with real products, prices, stock and links.
4. When the customer decides, the assistant adds the item to the cart and offers the checkout link inside the conversation.

= What the assistant can do =

* **Search the catalogue** by name, category, attribute or intent, and return products with price, availability, image and link.
* **Show product details**, including variations, attributes and stock.
* **Add to cart** after the customer confirms, and hand off to the checkout inside the chat.
* **Answer store questions** — delivery, shipping, payment, returns and opening hours — using the context you write in the settings.
* **Stay inside the limits** — a configurable message limit (30 messages per visitor every 10 minutes by default) protects your API budget.

= What it never does =

The assistant only reads. It does not create, edit, delete or publish posts, pages, products or orders. It does not read orders, customers, passwords or payment data. It does not change prices or stock, and it never completes a purchase by itself — the customer always confirms in the chat, and the checkout happens on the WooCommerce pages.

Conversations are stored on this site so the merchant can review token usage and estimated cost. The **Conversations** screen shows each conversation with its messages, total tokens and total spent, and lets the merchant delete any conversation. The plugin also offers an optional maximum spend per conversation: when the estimated cost reaches it, the assistant stops answering in that conversation.

= Setting it up =

The whole setup happens on one screen, **AI Contact** in the admin menu, and takes about five minutes: choose the provider, paste your API key, pick a model, write the store context, adjust the widget, and turn it on. Step by step instructions are in the Installation section below, and the **Tutorial** screen inside the plugin walks through the same steps with screenshots.

= Free and PRO =

This free version is complete and does not expire. It has no trial period, no license requirement and no disabled fields: every option in the settings screen works on its own, forever.

Extra options are provided by the separate **FSWEB AI Automated Contact PRO** add-on, installed alongside this plugin: a custom avatar image, auto-open with delay, custom CSS and automatic updates.

No premium code is bundled here. The free version keeps working if the add-on is never installed or is deactivated. The add-on owns its own license screen, **AI Contact → PRO license**, which appears only while the add-on is active.

= Requirements =

* WordPress 6.0 or newer, PHP 8.1 or newer.
* WooCommerce for the catalogue and cart features. Without WooCommerce the chat still answers store questions, but the product tools are unavailable.
* An API key from the AI provider of your choice.

== Installation ==

1. Install the plugin: search for **FSWEB AI Automated Contact** in **Plugins → Add New**, or upload the ZIP through **Plugins → Add New → Upload Plugin**.
2. Activate the plugin through the **Plugins** menu.
3. Open **AI Contact** in the admin menu.
4. Under **Artificial Intelligence**, choose your **provider** — OpenAI, Anthropic, DeepSeek, xAI, Groq or a custom OpenAI-compatible endpoint.
5. Paste the **API key** for that provider. Each provider keeps its own key, so you can switch between them without retyping anything. Use the **Test now** button to check the connection.
6. Pick a **model** from the list, or type a specific model name. For a custom endpoint, fill in the **Base URL** and the default model as well.
7. Under **Store context**, write how your store works: delivery times, shipping regions and costs, payment methods, returns, opening hours and the tone of voice you want. This is what the assistant uses to answer questions — the more specific, the better the answers.
8. Under **Appearance**, choose the chat icon, the brand colour and the launcher position (left or right). Use **Preview** to see the final widget before publishing.
9. Tick **Show the chat on the site** and click **Save Changes**.

The widget appears on the storefront immediately. To stop showing it without losing the configuration, untick the same box and save.

You need an API key from the provider, obtained directly from your account with them. Usage is billed by that provider according to your own plan.

== Frequently Asked Questions ==

= Do I need an API key? =

Yes. The plugin does not include AI credits: it connects to the provider you choose using your own key. The key is stored in your site's database, is used only server-side and is never sent to the visitor's browser.

= How much does it cost to run? =

Only what your provider charges for the tokens used. The plugin does not add a markup, sell credits or share a key — the account and the bill are yours. The configurable message limit (30 messages per visitor every 10 minutes by default) caps accidental spending. In **Settings → Usage and spending** you can enter the provider's price per one million tokens, see the total spent per conversation and set a maximum spend per conversation. The amounts are estimates based on the prices you enter; the provider's invoice is the source of truth.

= Which providers are supported? =

OpenAI, Anthropic (Claude), DeepSeek, xAI (Grok), Groq, and any endpoint that speaks the OpenAI chat completions API. You can keep keys for all of them and switch providers at any time without losing the configuration.

= Why does the plugin call the AI provider directly instead of using the WordPress AI Client? =

The core AI Client ships with WordPress 7.0 and uses a site-wide provider configuration. This plugin supports WordPress 6.0 and newer and lets each merchant pick one of five providers — or any OpenAI-compatible endpoint — with their own key, so it calls the provider HTTP API directly. No request is made until a visitor sends a chat message.

= Is WooCommerce required? =

WooCommerce is required for catalogue search, product details, add to cart and the checkout hand-off. Without WooCommerce the chat still answers store questions from the context you write, but the product tools are not available. The plugin does not break or block anything when WooCommerce is missing.

= Where are the conversations stored? =

On this site, in two dedicated database tables: one row per conversation with token counts and estimated cost, and one row per message. Provider API keys are stored in the WordPress options table and never leave the server. Any conversation can be deleted from **AI Contact → Conversations**. Uninstalling the plugin drops both tables.

= How is the amount spent calculated? =

The plugin reads the token usage returned by the provider and multiplies it by the input and output prices you enter in **Settings → Usage and spending** (US$ per one million tokens). Prices change, so copy the current values from your provider account. If you leave the prices at zero, the token counts are still stored but the spent column remains zero.

= Does the free version stop working after a while? =

No. The free version has no trial, no expiry date and no license requirement. The message limit defaults to 30 per visitor every 10 minutes and can be changed in **AI Contact → Assistant text and limits**.

= Can I change the assistant name, the welcome message or the colours? =

Yes. The assistant name, the welcome message, the privacy note, the temperature, the token limit, the message limit and the full colour palette are editable in the free plugin, under **AI Contact**. The PRO add-on adds a custom avatar image, auto-open with delay and custom CSS.

= Can I change the message limit? =

Yes. Under **AI Contact → Assistant text and limits** you can set it from 1 to 300 messages per visitor every 10 minutes. The default is 30.

= Will it work with my theme? =

Yes. The widget is rendered by the plugin with its own styles and does not depend on the theme. It sits above the page content and collapses to a single button on mobile.

= Does the assistant speak other languages? =

Yes. It replies in the language the customer writes in. For best results, write the store context in the same language your customers use, and pick a model that handles that language well.

= Does it modify products or orders? =

No. The assistant can only read the catalogue and add items to the cart. It cannot edit, delete or publish anything, and it cannot see orders, customers or payment data.

= Is there a PRO version? =

Yes. **FSWEB AI Automated Contact PRO** is a separate add-on that expands the assistant with an avatar from your media library, auto-open with delay, custom CSS and automatic updates. Details and purchase: https://flowsyncweb.com/produto/ai-automated-contact-pro/

It is an add-on, not a locked feature: the free version is complete, has no trial period and never stops working. The add-on's license is activated on its own screen, **AI Contact → PRO license**, which is registered by the add-on itself.

= What happens when I uninstall the plugin? =

Deactivating keeps your settings and the stored conversations. Uninstalling removes the plugin options, including the stored API keys, and drops the conversation and message tables.

== Screenshots ==

1. The chat widget on the storefront, with product results inside the conversation.
2. The settings screen: provider, model and API key.
3. The store context and appearance options, with the live Preview button.
4. The Preview modal showing the final widget before publishing.

== External services ==

This plugin connects to the AI provider selected by the merchant to generate the assistant's replies. Depending on the provider chosen, requests go to one of:

* OpenAI — https://api.openai.com/v1
* Anthropic — https://api.anthropic.com/v1
* DeepSeek — https://api.deepseek.com/v1
* xAI — https://api.x.ai/v1
* Groq — https://api.groq.com/openai/v1
* A custom OpenAI-compatible endpoint typed by the merchant

**What is sent:** the customer's chat message, the recent conversation history, the store context written by the merchant, and the catalogue data returned by the product search tools.
**What is not sent:** the site's database, WordPress users, orders, passwords or payment data.
**When:** only when a visitor sends a message to the chat and the widget is enabled.
**Authentication:** the merchant's own API key for the chosen provider, stored in the WordPress options table.

Provider terms of use and privacy policies:

* OpenAI — https://openai.com/policies/terms-of-use/ · https://openai.com/policies/privacy-policy/
* Anthropic — https://www.anthropic.com/legal/commercial-terms · https://www.anthropic.com/legal/privacy
* DeepSeek — https://cdn.deepseek.com/policies/en-US/deepseek-terms-of-use.html · https://cdn.deepseek.com/policies/en-US/deepseek-privacy-policy.html
* xAI — https://x.ai/legal/terms-of-service · https://x.ai/legal/privacy-policy
* Groq — https://groq.com/terms-of-use/ · https://groq.com/privacy-policy/

The plugin itself does not send store data to the plugin author. This free plugin performs no license check, telemetry or usage reporting.

== Changelog ==

= 1.0.1 =
* Fixed: removed the unused license client from the free plugin; the PRO add-on owns its license screen.
* Fixed: every setting accepted by the sanitizer (assistant name, welcome and privacy texts, temperature, token and message limits, palette) is now editable in the free settings screen.
* Fixed: settings are read lazily, so no translated default is loaded before the `init` hook (WordPress 6.7+ "translation loading too early" notice).
* Improved: the external services section documents the AI provider terms of use and privacy policies.

= 1.0.0 =
* New: conversation ledger — stores each conversation and its messages with token counts and estimated cost.
* New: **Conversations** screen with total messages, tokens and amount spent per conversation, plus a transcript view and delete action.
* New: **Usage and spending** settings — input/output price per one million tokens and an optional maximum spend per conversation.
* New: the assistant stops answering in a conversation when its estimated cost reaches the configured limit.
* Improved: the settings and tutorial now state clearly that the AI provider account, key and bill belong to the merchant; the plugin includes no credits and takes no markup.
* Improved: readme and in-plugin texts now describe local conversation storage and deletion.

* Initial free release.
* Chat assistant with OpenAI, Anthropic, DeepSeek, xAI, Groq and OpenAI-compatible endpoints.
* Catalogue search with name, price, availability, image and link.
* Product details including variations, attributes and stock.
* Add to cart after customer confirmation, with the checkout hand-off inside the chat.
* Store questions answered from the merchant's own context.
* Appearance: chat icon, one brand colour and launcher position.
* Live preview of the widget before publishing.
* Fixed rate limit of 30 messages per visitor every 10 minutes.

== Upgrade Notice ==

= 1.0.1 =
Removed the unused license client and unlocked every setting in the free edition.

= 1.0.0 =
First release. No action needed, just install and add your API key.

== Other Notes ==

= Source code =

This plugin is free software, licensed under GPLv2 or later. The full source code is public at https://github.com/euzebiodeveloper/fsweb-ai-automated-contact — the same code that is submitted to the WordPress.org directory, with no build step and no minified files.

= Credits =

Developed by Flowsyncweb (https://flowsyncweb.com/). The plugin bundles no third-party libraries; it uses the WordPress HTTP API to reach the AI providers.
