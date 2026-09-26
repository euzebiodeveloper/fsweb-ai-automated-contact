=== AI Automated Contact ===
Contributors: flowsyncweb
Donate link: https://flowsyncweb.com/
Tags: woocommerce, chat, ai, virtual assistant, customer support
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.0.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered virtual assistant for WooCommerce: it answers questions, finds products, builds the cart and takes the customer to checkout.

== Description ==

AI Automated Contact puts an AI virtual assistant on your WooCommerce store. The customer opens the chat, describes what they are looking for (for example, "bluetooth headphone"), and the assistant searches the catalogue, shows the products with price and availability, confirms the item and adds it to the cart, then sends the customer straight to checkout.

Beyond selling, the assistant answers questions about the store — delivery times, shipping, payment methods, returns — using the context you write yourself in the settings.

**Main features**

* Multiple AI providers: OpenAI (ChatGPT), Anthropic (Claude), DeepSeek, xAI (Grok) and Groq.
* Model list pre-filled per provider, with a free field for any specific model.
* Any OpenAI-compatible endpoint (base URL + key + model) through the "Other" provider.
* Editable store context — you write it, the assistant follows it.
* WooCommerce integration: product search, details, attributes, variations, cart and checkout.
* Safe by design: the assistant only reads the catalogue and works with the visitor's own cart.
* Floating chat widget with configurable colour, position, assistant name and welcome message.
* API keys stay on the server — they are never exposed to the visitor's browser.
* Per-visitor message limit against abuse.
* License with per-domain activation and automatic updates.

== Installation ==

1. Upload the `fsweb-ai-automated-contact` folder to `/wp-content/plugins/` (or install the ZIP through **Plugins → Add New → Upload Plugin**).
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Enter your license key on the welcome screen.
4. Go to **AI Contact**, pick the provider, paste the API key, choose the model and write your store context.
5. Tick **Show the chat on the site** and save.

== Frequently Asked Questions ==

= How does the assistant work with the catalogue? =

It looks up products, prices and availability in real time, confirms with the customer and then adds the item to the cart. There are no tools for creating, editing or deleting content.

= Do I need WooCommerce? =

WooCommerce is required for product search and the cart. Without it the assistant still handles general questions, but without catalogue access.

= Where are my API keys stored? =

In your WordPress database (`wp_options`). They are never sent to the browser: the widget only talks to the plugin's own REST endpoints.

= Is the conversation stored on the server? =

No. The history stays in the visitor's browser `sessionStorage` and is sent with each message only to give the model context.

= Can I use products with options (colour, size)? =

Yes. For variable products the assistant lists the available options, asks which one the customer wants and only then adds the chosen variation to the cart.

== Screenshots ==

1. The floating chat button on the storefront.
2. The chat window — greeting, customer message, typing indicator and message field.

== Changelog ==

= 1.0.2 =
* The plugin now uses its own license API URL constant (`FSWEB_AI_AUTOMATED_CONTACT_LICENSE_API_URL`), so sibling plugins cannot override it.
* License activation, validation and deactivation now talk to the FSWEB license API on `api.flowsyncweb.com`; the legacy License Manager for WooCommerce bridge is gone.
* The response body is the source of truth again: a business rejection is shown as it comes from the API, and a transport failure keeps the current license state and shows a neutral notice.

= 1.0.1 =
* Settings, tutorial, onboarding and license screens standardised with hero, quick actions, cards, pills and icon buttons.
* Complete pt_BR translation of the standardised screens; the source is now 100% English, including the labels the JS rewrites.
* English documentation: README.md with badges, features and requirements, readme with screenshots, plugin banner and icon.
* Fix: the readme parser accepts lowercase `readme.txt` and the "View details" popup shows every section again.
* Fix: the onboarding license block got the id the JS uses to close it, and the manual license-check action was removed from the license screen.

= 1.0.0 =
* Initial release.
* AI virtual assistant with multiple providers (OpenAI, Anthropic, DeepSeek, xAI and Groq).
* Per-provider model catalogue with a free field for any other model.
* Editable store context, with a ready-made service prompt.
* WooCommerce integration: product search, details, variations, cart and checkout.
* Configurable public chat widget: icon, colours, auto open and custom CSS.
* Per-domain license with onboarding and automatic updates.

== Upgrade Notice ==

= 1.0.2 =
License fixed against the new license API.

= 1.0.1 =
Standardised admin screens, complete pt_BR translation and readme fixes.

= 1.0.0 =
First public release.
