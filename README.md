# SureCart Bulgaria Dual Pricing

A WordPress plugin that adds dual pricing (BGN/EUR) support for SureCart in compliance with Bulgarian pricing regulations.

## Description

This plugin extends SureCart to automatically display prices in both Bulgarian Lev (BGN) and Euro (EUR) on product pages and shop pages. This is required by Bulgarian law, which mandates that prices must be displayed in both the national currency (BGN) and Euro for consumer transparency.

### Features

- **Automatic Currency Conversion**: Automatically converts BGN prices to EUR using the official exchange rate
- **Dual Price Display**: Shows both BGN and EUR prices on product pages
- **Variant Support**: Handles dual pricing for product variants
- **List Price Support**: Displays both currencies for comparison/list prices
- **Seamless Integration**: Works natively with SureCart's block-based product pages

### How It Works

The plugin hooks into SureCart's price rendering system and:

1. Detects when prices are displayed in BGN
2. Calculates the equivalent EUR amount using the conversion rate
3. Displays both currencies to customers on the frontend

## ⚠️ Legal Disclaimer

**IMPORTANT: This plugin is provided as-is without any warranties or guarantees.**

- We are **not liable** for any legal, financial, or compliance issues that may arise from using this plugin
- This is open-source software that can be freely modified
- Currency conversion rates and pricing display requirements may change
- **You are solely responsible** for ensuring compliance with Bulgarian and EU pricing regulations
- **We strongly recommend consulting with a qualified attorney** before using this plugin in a production environment
- Use of this plugin does not constitute legal advice

By using this plugin, you accept full responsibility for its implementation and compliance with applicable laws.

## Requirements

- WordPress 6.0 or higher
- PHP 7.4 or higher
- **SureCart Plugin** (must be installed and activated)
- You must have Bulgarian currency as your store currency.

## Installation

### Step 1: Download the Plugin

1. Go to the [Releases](../../releases) page on GitHub
2. Download the latest `.zip` file from the release assets

### Step 2: Install to WordPress

#### Install via WordPress Admin (Recommended)

1. Log in to your WordPress admin dashboard
2. Navigate to **Plugins → Add New**
3. Click the **Upload Plugin** button at the top of the page
4. Click **Choose File** and select the downloaded `.zip` file
5. Click **Install Now**
6. After installation completes, click **Activate Plugin**

### Step 3: Verify Installation

Once activated, the plugin will automatically:

- Display dual pricing on all SureCart product pages and shop pages with BGN currency
- Show EUR equivalents alongside BGN prices using the standard €1 = 1.95583 BGN.
- No additional configuration required!

## Usage

The plugin works automatically once activated. Simply:

1. Create or edit your SureCart products as usual
2. Set prices in BGN
3. The plugin will automatically display the both BGN and EUR in product list pages and single product pages.

## Support

For issues, questions, or feature requests, please [open an issue](../../issues) on GitHub.

## License

This plugin is provided as-is for use with SureCart.

## Credits

Developed by [SureCart](https://surecart.com)
