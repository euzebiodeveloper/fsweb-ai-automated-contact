# 🤖 AI Automated Contact

[![WordPress](https://img.shields.io/badge/wordpress-6.0%2B-blue?style=flat-square)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/php-8.1%2B-purple?style=flat-square)](https://www.php.net/)
[![WooCommerce](https://img.shields.io/badge/woocommerce-required-96588a?style=flat-square)](https://woocommerce.com/)
[![Version](https://img.shields.io/badge/version-1.0.2-orange?style=flat-square)](https://flowsyncweb.com/)
[![License](https://img.shields.io/badge/license-GPLv2-blue.svg?style=flat-square)](https://opensource.org/licenses/GPL-2.0)

**Contributors:** [flowsyncweb](https://github.com/flowsyncweb)  
**Website:** [flowsyncweb.com](https://flowsyncweb.com/)  
**Tags:** woocommerce, chat, ai, virtual assistant, customer support  
**Requires at least:** 6.0  
**Tested up to:** 7.1  
**Requires PHP:** 8.1  
**Stable version:** 1.0.2  
**License:** GPLv2 or later

AI-powered virtual assistant for WooCommerce: it answers questions, finds products, builds the cart and takes the customer to checkout.

---

## ✨ Features

- 🤖 **Multiple AI providers** — OpenAI (ChatGPT), Anthropic (Claude), DeepSeek, xAI (Grok), Groq and any OpenAI-compatible endpoint
- 🔎 **Catalogue aware** — searches products in real time, with price, availability, images and variations
- 🛒 **Builds the cart** — confirms the item with the customer, adds it to the cart and hands over the checkout
- 🎨 **Configurable widget** — icon, colours, position, auto open and custom CSS
- 🧠 **Your context, your rules** — you write the store context the assistant must follow
- 🔐 **License & Automatic Updates** — activate a license key to receive one-click updates from your own server

---

## 📝 Description

**AI Automated Contact** puts an AI assistant on your WooCommerce store. The customer opens the chat, describes what they are looking for, and the assistant searches the catalogue, presents the options, confirms the choice and adds it to the cart — sending the customer straight to checkout.

The assistant also answers store questions (delivery times, shipping, payment methods, returns) using the context you write in the settings. API keys never leave the server: the widget only talks to the plugin's own REST endpoints.

---

## ⚙️ How to Use

1. Go to **AI Contact** and pick your AI provider — OpenAI, Anthropic, DeepSeek, xAI, Groq or any OpenAI-compatible endpoint.
2. Paste the API key and choose the model. The list is pre-filled per provider, and you can type any specific model.
3. Write the **store context**: delivery times, shipping, payment methods, returns and tone of voice. This is what the assistant follows.
4. Enable the catalogue and the cart so the assistant can search products, list variations and add items.
5. Style the widget: icon, colours, auto open and custom CSS.
6. Tick **Show the chat on the site** and save. Use the connection test to validate your key first.

---

## 📦 Installation

1. Upload the `fsweb-ai-automated-contact` folder to the `/wp-content/plugins/` directory (or install the ZIP via **Plugins → Add New → Upload Plugin**).
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Enter your license key on the welcome screen.
4. Go to **AI Contact**, pick the AI provider, paste the API key, choose the model and write your store context.
5. Tick **Show the chat on the site** and save.

---

## 🎯 Use Cases

- **Product discovery** — the customer says "I am looking for a bluetooth headphone" and the assistant searches the catalogue, showing options with price and availability.
- **Product questions** — sizes, colours, variations and stock for a specific item, answered from your own catalogue.
- **Cart and checkout** — once the customer confirms, the assistant adds the item to the cart and hands over the checkout button.
- **Store policies** — delivery times, shipping, payment methods and returns, answered from the context you wrote.
- **Out-of-hours support** — the assistant keeps answering while nobody is online.

---

## 🔧 Minimum Requirements

- WordPress 6.0 or higher
- PHP 8.1 or higher
- WooCommerce (installed and active)
- An API key from one of the supported AI providers
- A valid license key (for automatic updates)

---

## 🧩 Dependencies

- **WooCommerce** — provides the catalogue, the cart and the checkout the assistant works with.
- **FSWEB Update Checker** — a self-contained, versioned library bundled inside the plugin that powers license-based automatic updates from your own server.

---

## 📖 Documentation & Support

- Settings, licensing and troubleshooting: the **Tutorial** and **License** screens inside the plugin.
- Issues and feature requests: [github.com/euzebiodeveloper/fsweb-ai-automated-contact](https://github.com/euzebiodeveloper/fsweb-ai-automated-contact)

---

## 📢 Give your customers an assistant that actually sells, with AI Automated Contact
