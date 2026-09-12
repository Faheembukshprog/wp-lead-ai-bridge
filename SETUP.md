# Setup Guide — WordPress + AI Lead Automation Demo

Follow these in order. Total time for local setup: roughly 45-60 minutes the first time.

## 1. Local environment (XAMPP)

1. Install XAMPP, start Apache and MySQL.
2. Download WordPress, unzip it into `xampp/htdocs/lead-demo`.
3. Visit `http://localhost/lead-demo`, create the database (`phpMyAdmin` > New > `lead_demo`), finish the WordPress install wizard.
4. Log in to `/wp-admin`.

## 2. Core plugins

1. Plugins > Add New: install and activate **WooCommerce**, run its setup wizard (store address, currency, no payment gateway needed for a demo — enable "Cash on delivery" or WooCommerce's test gateway).
2. Install and activate **Astra** (theme) and **Elementor**.
3. Install and activate **Contact Form 7**.
4. Install and activate this plugin: copy the `wp-lead-ai-bridge` folder into `wp-content/plugins/`, then Plugins > Activate **WP Lead AI Bridge**.
   - This creates its log table automatically on activation.

## 3. Demo products

WooCommerce > Products > Add New. Create 3 simple products (name, price, short description, one image each). Anything plausible — they exist to prove the store works, not to sell anything.

## 4. Contact form

1. Contact > Add New in CF7. Use field names the plugin already recognizes:
   ```
   [text* your-name]
   [email* your-email]
   [textarea your-message]
   [text product-interest]
   ```
2. Publish the form, note its shortcode, and place it on a "Contact / Inquire" page (build the page quickly in Elementor, drop the CF7 shortcode widget in).
3. If your form field names differ, open `wp-lead-ai-bridge.php` and adjust the arrays in `lai_handle_cf7_submission()`.

## 5. Configure the plugin

1. In wp-admin, go to **Lead AI Bridge > Settings**.
2. Leave the webhook URL blank for now and submit a test lead on the front end — check **Lead AI Bridge > Lead Log**. Status should show `skipped`, meaning it logged correctly but had nowhere to send yet. This proves step 2/3 work before touching n8n.

## 6. Import and run the n8n workflow

1. Install n8n (`npx n8n` is the fastest path, or use n8n Cloud's free tier).
2. In n8n: Workflows > Import from File > select `n8n-workflow/lead-ai-pipeline.json`.
3. Open the **Webhook - Receive Lead** node, copy its Test URL (or Production URL once the workflow is activated).
4. Paste that URL into wp-admin > Lead AI Bridge > Settings > n8n Webhook URL, save.
5. The **Classify Lead (Mock Mode)** node runs with zero credentials — you can test the whole pipeline right away.
6. To use a real LLM: open the disabled **Classify Lead (OpenAI - optional)** node, add your OpenAI API key as an HTTP Header Auth credential (`Authorization: Bearer sk-...`), reconnect it in place of the mock node, and enable it.
7. Add your Google Sheets credential to **Append to Google Sheet** (create a blank sheet named `Leads` with a header row: Date, Source, Name, Email, Message, Product Interest, Lead Type, Urgency), paste its Sheet ID into the node.
8. Configure **Send Notification Email** with real SMTP credentials, or swap in the disabled Telegram node if you'd rather get a phone alert.
9. Activate the workflow (top-right toggle in n8n).

## 7. Test checklist

- [ ] Submit the CF7 form on the live site.
- [ ] Confirm CF7's own email notification still arrives (this should always work, independent of n8n).
- [ ] Check **Lead AI Bridge > Lead Log** — status should be `sent`, HTTP 200.
- [ ] Check the Google Sheet — a new row appeared with the AI-classified lead type and urgency.
- [ ] Check your inbox (or Telegram) — the notification email arrived with the suggested reply.
- [ ] Stop n8n and submit the form again — confirm the site doesn't break, CF7 email still sends, and the log now shows `failed` with the connection error recorded. This is the "fails gracefully" requirement working as designed.

## 8. Putting it live (free/cheap hosting)

- **InfinityFree** or **000webhost** — free, fine for a portfolio demo, slower and less reliable.
- **Hostinger's cheapest shared plan** — a few dollars a month, one-click WordPress install, noticeably faster and more credible for recruiters clicking a live link.
- Either way: export the local database and files, use a plugin like **All-in-One WP Migration** or **UpdraftPlus** to move the site, update the site URL, and re-point the n8n webhook URL in Settings if it changed.
- For n8n itself, the free tier of n8n Cloud is the simplest path to a permanent webhook URL you don't have to keep your own laptop running for.
