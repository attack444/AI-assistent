=== AI Puffer – Chat. Create. Automate. (formerly AI Power) ===
Contributors: senols
Tags: ai, chatbot, openai, ai writer, automation
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 2.4.58
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Chat. Create. Automate.

== Description ==

**AI Puffer** is the **complete AI plugin for WordPress** — a full set of **artificial intelligence tools** to transform your site. From **AI chatbot** and **content generation** to **image creation, automation, and AI training** on your own data, AIP gives you everything in one place, right inside your WordPress dashboard.

Our **"Bring Your Own API Key"** model lets you connect to top AI providers (OpenAI, Google Gemini, Microsoft Azure, OpenRouter, DeepSeek, xAI and Ollama). No hidden credits — you use your own account and control your costs.

[📖 Documentation & Guides](https://docs.aipower.org/)  

### Why Choose AIP?

* **All-in-One** – Chatbot, AI Writer, AI Forms, Image Generator, Automation, WooCommerce AI tools, and more.
* **Train on Your Data** – Build your own **AI knowledge base** from posts, pages, products, PDFs, or files.
* **Voice + Chat** – Real-time voice agents and voice input for interactive AI experiences.
* **WooCommerce AI** – Generate product descriptions, titles, SEO tags, and sell AI credits to customers.
* **Fast & Flexible** – Works with OpenAI GPT-5/4o, Google Gemini & Imagen, Azure, Replicate, and others.
* **Secure** – 100% hosted on your WordPress site. Your data stays with you.

---

### 🚀 Key Features

#### 🤖 AI Chatbot
- Create custom **AI chatbots** for WordPress or any external site (embed with shortcode or HTML).
- Train bots on your **own website content** or external files.
- Enable **web search** (OpenAI or Google) for real-time answers.
- Add **voice input & playback**, triggers, and usage limits.

#### ✍️ AI Content Generator
- Generate **high-quality articles, blog posts, or product descriptions**.
- Input ideas via text, CSV, RSS feeds, or URLs.
- SEO-friendly output with custom templates, placeholders, and **Smart SEO** score improvement.

#### 📝 AI Forms
- Drag-and-drop **AI-powered forms** to process user input into useful outputs — from outlines to support replies.
- Connect forms to **web search**, uploaded files, image analysis, workflows, and your AI training data.

#### ⚙️ AI Automation Engine
- Schedule recurring or one-time AI tasks.
- Automate content creation, Smart SEO improvement, comment replies, or vector indexing.

#### 🎨 AI Image Generator
- Convert text to image with **OpenAI GPT Image, Google Imagen, and Replicate models**.
- Pull free stock images from **Pexels** or **Pixabay**.
- Works in posts, tasks, chatbot, and forms.

#### 📚 AI Training / Vector Database
- Build a **knowledge base** from your posts, products, PDFs, or uploaded files.
- Supports **OpenAI Vector Stores**, **Pinecone**, **Qdrant** and **Chroma**.
- Long content is chunked before embedding for safer external vector indexing.
- Use in Chatbot or Forms for **context-aware AI answers**.

#### 🛒 WooCommerce AI Tools
- Bulk-generate or enhance product descriptions, titles, and tags.
- Sell **AI credits** to customers via WooCommerce.

#### 🛠 Content Assistant
- Bulk-enhance existing posts, generate SEO titles/excerpts.
- Works in Block Editor, Classic Editor, or directly from the post list.

#### 🔌 REST API Access
- Call text, image, embedding, and chatbot functions programmatically from other apps.

---

== Installation ==

1. Install via Plugins → Add New, or upload to `/wp-content/plugins/gpt3-ai-content-generator`.
2. Activate via the **Plugins** menu.
3. Go to **AIP → Dashboard** and enter your API key for at least one provider (e.g., OpenAI).
4. Click **Sync Models** to load available AI models.
5. Explore modules (Chat, Write, Automate, etc.) and start using AI features.

---

== Frequently Asked Questions ==

= Do I need to buy credits from you? =  
No. AIP works with your **own API key** from AI providers like OpenAI, Google Gemini, etc. You pay them directly for usage.

= Which AI providers and models are supported? =  
We support **OpenAI** (GPT-5, GPT-4o, GPT-3.5, GPT Image, etc.), **Google** (Gemini, Imagen), **Microsoft Azure OpenAI**, **OpenRouter**, **DeepSeek**, **Ollama** and **Replicate**.

= Can I train the AI on my own content? =  
Yes. Use the **Train** module to index posts, pages, WooCommerce products, PDFs, or uploaded files into a **vector store**. Then link that knowledge base to your Chatbot or Forms.

= How do I limit AI usage for visitors or members? =  
The **Usage & Billing** tools let you set guest, user, or role-based usage limits for Chat, Forms, and Images. Limits can reset daily, weekly, monthly, or never.

= Can I monetize my AI tools? =  
Yes. Sell **credit packages** via WooCommerce. Credits are deducted when pricing rules apply to AI usage.

= What makes AIP different from other AI plugins? =  
AIP is **all-in-one** — instead of installing separate plugins for chatbots, content writing, AI forms, and WooCommerce AI, you get them all in one optimized toolkit with centralized settings.

= Is AIP compatible with GPT-5 and other latest models? =  
Yes. AIP supports GPT-5, GPT-4o, GPT-4 Turbo, Google Gemini 1.5, Imagen 4.0, and more.

---

== Screenshots ==

1. Main dashboard with quick access to all modules.
2. Add-ons page for enabling/disabling features.
3. Chatbot builder with real-time preview.
4. Content Writer with single, bulk, and RSS generation.
5. Automated Tasks scheduler.
6. Drag-and-drop AI Form builder.
7. AI Image Generator interface.
8. AI Training vector store management.
9. Usage & Billing system.
10. WooCommerce AI integration.

---

== Changelog ==

= 2.4.58 =

- Redesigned Content Assistant.
- Improved batch content updates.
- Redesigned Add to knowledge base.

= 2.4.57 =

- Improved Settings module.
- Standardized missing-provider notices across modules.
- Improved Chatbot live preview.

= 2.4.56 =

- Redesigned AI Forms with a cleaner overview, five ready-made templates, and a more compact form editor.
- Improved form building with persistent layout controls, resizable columns, clearer drop targets, streamlined field settings, and automatic prompt-variable names for newly added fields.
- Added model syncing, modern model, knowledge base, and web search settings dialogs, stronger prompt validation, and preview support for any form containing fields.
- Added checkbox-based bulk export and deletion, one-off shortcode options, and clearer import and export actions.
- Standardized table headers, footers, pagination, dialogs, buttons across AI Forms and related admin screens.
- Stopped creating legacy default forms on activation or update; existing forms remain available.
- Fixed Chatbot Manage Sources updates incorrectly reporting a missing OpenAI API key.

= 2.4.55 =

- Refined Automations with a compact overview, clearer task schedules, consistent statuses, and responsive task and queue tables.
- Added task pagination with rows-per-page controls, queue pagination, and always-visible queue search and status filters.
- Added safer checkbox-based bulk queue deletion, clearer cron health controls, and direct links to completed posts.

= 2.4.54 =

- Improved knowledge base module.

= 2.4.53 =

- Improved log details, retention controls, billing workflows.

= 2.4.52 =

- Improved chatbot knowledge sources.

= 2.4.51 =

- Added GPT-5.6 Sol, GPT-5.6 Terra, and GPT-5.6 Luna to the recommended OpenAI models.

= 2.4.49 =

- Redesigned Automations with a compact, guided setup experience for creating content, rewriting posts, building knowledge bases, and replying to comments.
- Made task creation faster with inline topic entry, Batch Editor, Quick Paste, CSV, RSS, URL, and Google Sheets workflows, plus a quick-create option using recommended defaults.
- Unified text, image, and embedding model selection with clearer provider setup guidance and smarter available-model defaults.
- Refined task editing, image and SEO controls, scheduling, publishing settings, validation, and prompt customization for a more consistent workflow.
- Modernized the Tasks and Queue screens with clearer statuses, schedules, cron health, queue tools, and safer deletion confirmations.

= 2.4.48 =

- Refined the Content Writer workspace layout.
- Improved Content Writer autosave feedback so normal saves stay quiet while errors remain visible.
- Improved Content Writer generation progress and error displays, including clearer provider API errors.
- Refined Automations task and queue panels.

= 2.4.47 =

- Improved the AI Puffer top navigation so module links, Usage, Settings, and Upgrade adapt more cleanly across desktop, tablet, and narrow responsive widths.
- Moved Usage into the utility navigation area and kept the main module navigation focused on the primary tools.
- Updated default module visibility so Chatbots, Content Writer, Automations, AI Forms, and Knowledge Base are enabled by default, while Images can be enabled from Settings > Modules when needed.

= 2.4.46 =

- Polished the settings screens and top navigation styling.

= 2.4.45 =

- Fixed automated task scheduler cleanup so orphaned task cron hooks are pruned automatically.

= 2.4.44 =

- Fixed automated task Run Now actions for content-writing tasks so manual runs load the shared task modules correctly.
- Added task IDs to Run Now content-writing queue items so RSS history tracking matches scheduled runs.

= 2.4.43 =

- Removed the dashboard and moved module toggles to Settings > Modules.
- Simplified the chatbot settings interface.

= 2.4.42 =

- Fixed an unclear file upload error shown when required vector store processing files are missing from the installation.
- Updated the pricing page to support newly introduced currencies.

= 2.4.41 =

- Fixed a shortcode rendering issue in some WordPress setups.
- Fixed database table creation on servers with stricter MySQL/MariaDB index length limits.

= 2.4.39 =

- Fixed popup chatbot accessibility warnings caused by focusable controls inside hidden popup and hint containers.

= 2.4.38 =

Brought back PHP 7.4 support due to popular demand from PHP 7.4 fans.

= 2.4.37 =

- Code cleanup.

= 2.4.36 =

- Code cleanup.

= 2.4.35 =

- Fixed WordPress AI Connectors approval conflict that could block OpenAI vector-store indexing when the WordPress AI OpenAI connector plugin was active.
- Improved AI Puffer-managed WordPress AI connector status reporting in the WordPress AI dashboard.

= 2.4.34 =

- Added Claude Opus 4.8 to Anthropic recommended models.
- Improved Role Manager compatibility with custom roles from access management plugins.
- Improved admin styling isolation from other plugins.

= 2.4.33 =

Performance improvements.

= 2.4.32 =

- General bug fixes and improvements.

= 2.4.31 =

- General bug fixes and improvements.

= 2.4.29 =

- General bug fixes and improvements.

= 2.4.28 =

- Fixed a WordPress AI Client compatibility issue.
- Improved embedding batches.
- Improved Role Manager permissions for core modules, WordPress utilities, Usage, and Settings.

= 2.4.27 =

- Improved vector store list refresh and stale cache handling across OpenAI, Pinecone, Qdrant, and Chroma.
- Fixed the AI Forms OpenAI vector store selector in Knowledge Base settings.

= 2.4.26 =

- Improved webhook events.

= 2.4.25 =

- Added WordPress AI Connectors.

Read more: [WordPress AI Connectors](https://docs.aipower.org/wordpress-ai-connectors)

= 2.4.24 =

- Added WordPress 7.0 compatibility updates.
- Removed deprecated Google Gemini 3.1 Flash Lite Preview and added Gemini 3.5 Flash.
- Fixed long-content chunking for Pinecone, Qdrant, and Chroma so large WordPress posts can be embedded in safe chunks.
- Improved Qdrant strict-mode filters.
- Improved Chroma collection lookup/delete reliability.
