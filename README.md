# WooCommerce Kainos.lt Feed

A WordPress WooCommerce plugin that generates a Kainos.lt compatible XML product feed.

## Features

- Generates XML feed for Kainos.lt
- Supports simple WooCommerce products
- Supports variable products
- Exports each variation as a separate product
- Automatically updates XML feed every 12 hours
- Manual “Generate XML Now” button
- Fixed manufacturer support
- WooCommerce SKU as manufacturer_code
- Product title as model
- Main product image export
- Product gallery images as additional_images
- Product categories with full category path
- Product description export
- Stock export
- Delivery time and delivery text settings
- Optional EAN meta key support
- Admin status page with XML URL, last generated date, product count and generation status

## XML fields

The plugin exports:

- title
- item_price
- manufacturer
- image_url
- product_url
- model
- manufacturer_code
- additional_images
- categories
- description
- stock
- delivery_time
- delivery_text
- ean_code, if available

## Installation

1. Download the plugin ZIP file.
2. Upload it in WordPress admin under Plugins → Add New → Upload Plugin.
3. Activate the plugin.
4. Go to WooCommerce → Kainos.lt Feed.
5. Configure delivery settings and optional EAN meta key.
6. Click “Generate XML Now”.

## Feed URL

The generated XML feed is available at:

/wp-content/uploads/kainos-lt-feed/products.xml

## Cron

The feed is regenerated automatically every 12 hours using WP-Cron.

## Requirements

- WordPress
- WooCommerce
- PHP 7.4 or newer

## Notes

- This plugin is created specifically for Kainos.lt XML feed requirements.
- Variable products are exported by variations, not by parent product.
- EAN is optional and exported only when available.
- Manufacturer is fixed as “Mokytoja Veronika” for this shop.

## Author

Developed by WebMode.lt  
https://webmode.lt
