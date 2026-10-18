=== AI Automated Contact ===
Contributors: flowsyncweb
Donate link: https://flowsyncweb.com/
Tags: woocommerce, chat, ai, virtual assistant, customer support
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered virtual assistant for WooCommerce: answers questions, finds products and guides the customer to the cart and checkout.

== Description ==

AI Automated Contact puts an AI virtual assistant on your WooCommerce store. The customer opens the chat, describes what they are looking for, and the assistant searches the catalogue, presents the products with price and availability, and answers questions about the store using the context you write.

The plugin includes a complete, working feature set:

* **AI chat widget** on the storefront.
* **Your AI, your key** — OpenAI, Anthropic (Claude), DeepSeek, xAI (Grok), Groq or any OpenAI-compatible endpoint, each with the merchant's own API key.
* **Catalogue search** with product name, price, availability, image and link.
* **Product details** including variations, attributes and stock.
* **Add to cart** after the customer confirms, with the checkout hand-off inside the chat.
* **Store questions** — delivery, shipping, payment, returns and opening hours, using the context you write.
* **Appearance** — assistant name, chat icon, colours and widget position.
* **Rate limiting** — a fixed limit of 30 messages per visitor every 10 minutes.

The assistant never changes, deletes or publishes anything on the site. It only reads the catalogue, adds items to the cart and answers questions.

Advanced options are available in the separate **AI Automated Contact PRO** add-on, installed alongside this plugin: custom message limit, custom avatar image, editable welcome message, auto-open with delay, custom CSS, license activation and automatic updates. The free version does not contain premium code; it keeps working on its own.

== Installation ==

1. Upload the `fsweb-ai-automated-contact` folder to `/wp-content/plugins/`, or install the ZIP through **Plugins → Add New → Upload Plugin**.
2. Activate the plugin through the **Plugins** menu.
3. Go to **AI Contact**, paste your OpenAI API key, choose a model and save.
4. Write the store context: delivery times, shipping, payment methods, returns and tone of voice.
5. Tick **Show the chat on the site** and save.

An OpenAI API key is required and is obtained directly from OpenAI. Usage is billed by OpenAI according to your account.

== Frequently Asked Questions ==

= Does the plugin require an API key? =

Yes. The plugin connects to the AI provider you choose and needs an API key from your own account with that provider. The key is stored only in this site's database and is never sent to the visitor's browser.

= Is WooCommerce required? =

WooCommerce is required for catalogue search, product details and cart-related features. The chat can still answer store questions without WooCommerce, but the product tools will be unavailable.

= Where are the conversations stored? =

Conversations stay in the visitor's browser for the current session. The plugin does not store chat messages in the WordPress database.

= Does the free version stop working after a while? =

No. The free version is fully functional, has no trial period and no license requirement. The only built-in limit is the fixed message rate limit of 30 messages per visitor every 10 minutes.

= How do I get the advanced features? =

They are provided by the separate AI Automated Contact PRO add-on. See the **Go PRO** screen inside the plugin for details.

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

Provider policies: https://openai.com/policies/privacy-policy/ · https://www.anthropic.com/legal/privacy · https://cdn.deepseek.com/policies/en-US/deepseek-privacy-policy.html · https://x.ai/legal/privacy-policy · https://groq.com/privacy-policy/

The plugin itself does not send data to the plugin author.

== Screenshots ==

1. The chat widget on the storefront.
2. The settings screen with the AI provider, store context and appearance options.

== Changelog ==

= 1.0.0 =
* Initial free release.
* Chat assistant with OpenAI, Anthropic, DeepSeek, xAI, Groq and OpenAI-compatible endpoints.
* Catalogue search, product details, add to cart and checkout hand-off.
* Store questions answered from the merchant's context.
* Appearance: assistant name, icon, colours and position.
* Fixed rate limit of 30 messages per visitor every 10 minutes.

== Upgrade Notice ==

= 1.0.0 =
* Initial free release.
