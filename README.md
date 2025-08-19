# InfoPlusCommerce Shopware Plugin

## Overview
This plugin integrates Shopware 6 with InfoPlusCommerce to synchronize products, categories, customers, inventory, and orders.

## Installation
1. Copy the plugin to `custom/plugins/InfoPlusCommerce`.
2. Run `bin/console plugin:install --activate InfoPlusCommerce`.
3. Configure the plugin via the Shopware admin panel under Extensions > InfoPlusCommerce.

## Configuration
- **API Key**: InfoPlusCommerce API key.
- **Base Domain**: InfoPlusCommerce API base URL (e.g., https://api.infopluscommerce.com).
- **LOB ID**, **Warehouse ID**, **Carrier ID**: IDs from InfoPlusCommerce.
- **Sync Toggles**: Enable/disable sync for each entity.
- **Max Retry Attempts**: Number of retry attempts for API failures (default: 3).

## Usage
- Use the admin UI to configure settings and trigger manual syncs.
- Automatic syncs are triggered on entity create/update/delete events if enabled.
- Monitor sync results and errors in the admin UI.

## API Endpoints
See `open-api.yaml` for full endpoint documentation.

## Development
- **Tests**: Run `vendor/bin/phpunit tests/` for unit and integration tests.
- **Dependencies**: Install via `composer install`.

## Support
Contact support@infopluscommerce.com for API issues or open a GitHub issue for plugin bugs. 