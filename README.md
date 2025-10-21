[![Packagist Version](https://img.shields.io/packagist/v/solution25/infoplus-commerce.svg)](https://packagist.org/packages/solution25/infoplus-commerce)
[![Packagist Downloads](https://img.shields.io/packagist/dt/solution25/infoplus-commerce.svg)](https://packagist.org/packages/solution25/infoplus-commerce)
[![License: MIT](https://img.shields.io/badge/license-MIT-green.svg)](https://github.com/solution25/InfoPlus/blob/main/LICENSE)

# InfoPlusCommerce Integration for Shopware 6

## Introduction

The **InfoPlusCommerce Plugin** seamlessly integrates your Shopware 6 store with **InfoPlusCommerce ERP**, providing real-time synchronization of products, categories, customers, inventory, and orders.

This integration ensures your data stays consistent across both platforms — improving operational efficiency, reducing manual work, and preventing synchronization errors.

---

##  Key Features

###  Bi-Directional Synchronization
- Sync **Products**, **Categories**, **Customers**, **Orders**, and **Inventory** between Shopware 6 and InfoPlusCommerce.

###  Configurable Sync Options
- Enable or disable syncs for each entity type individually.
- Define retry behavior for failed sync attempts.

###  Inventory Management
- Keep stock levels and warehouse data accurate in real time.

###  Order Sync
- Automatically push Shopware orders to InfoPlusCommerce for fulfillment.
- Receive order status and tracking updates from InfoPlusCommerce.

###  Customer Sync
- Sync customer profiles and addresses seamlessly between platforms.

###  Intelligent Retry Handling
- Automatically retries failed syncs with configurable limits to ensure data integrity.

###  Admin Panel Integration
- Full configuration available directly within Shopware Admin.

---

##  Compatibility
- ✅ **Shopware 6.6.x**

---

##  Installation & Activation

### Via GitHub

```bash
git clone https://github.com/solution25com/infoplus-integration-shopware-6-solution25.git
```

## Packagist
 ```
  composer require solution25/infoplus-commerce
  ```

2. **Install the Plugin in Shopware 6**

- Log in to your Shopware 6 Administration panel.
- Navigate to Extensions > My Extensions.
- Locate the newly cloned plugin and click Install.

3. **Activate the Plugin**
- Once installed, toggle the plugin to activate it.

4. **Verify Installation**

- After activation, you will see Infoplus in the list of installed plugins.
- The plugin name, version, and installation date should appear.

## Plugin Configuration

After installing the plugin, you can configure your **Infoplus** credentials and options through the Shopware Administration panel.

### Accessing the Configuration

1. Go to **Extensions > My extensions > Infoplus configure**
2. Select the **Sales Channel** you want to configure
3. Set the following fields:
  
 - API Key
 - Base Domain (https://xxx.infopluswms.com)
 - LOB ID
 - Warehouse ID
 - Default Carrier ID

 <img width="2928" height="1434" alt="image" src="https://github.com/user-attachments/assets/3ecb8f78-aed6-4eaf-a88f-6a5211257971" />

 ### Accessing the Infoplus Settings
 1. Go to **Settings > System > Infoplus settings**

 <img width="2926" height="1432" alt="image" src="https://github.com/user-attachments/assets/2b4d9e18-9474-4d6e-8023-830a5141aea0" />



