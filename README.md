# Infrangible Catalog Product Option Product

Adds the custom option type **Product**: instead of a title and a price, the option links another catalog product, for
example an accessory, a service or a warranty. On the product page the option is a checkbox with the name and price of
the linked product; a configurable product is shown with its swatches, and the selected variant is added.

## Options with a product

The custom options of the product form in the admin have the type **Product** with these fields:

| Field                        | Use                                                                                    |
|------------------------------|----------------------------------------------------------------------------------------|
| **Product**                  | ID of the linked product (simple, virtual or configurable)                             |
| **Product Code**             | code of a product attribute, for example `sku`, whose value is stored with the order   |
| **Unattached**               | adds the price once per cart item instead of once per piece                            |
| **Attribute Option Mapping** | `srcAttrId:srcAttrOptionId:targetAttrId:targetAttrOptionId`, separated by commas       |
| **Attribute Option Preselect** | `attributeId:attributeOptionId` (or only `attributeId` for the first option), separated by commas |

- **Price:** the final price of the linked product, for a configurable product the final price of the selected
  variant, so special prices and catalog price rules apply. A price that is stored for the option (for example through
  the API) replaces the price of a simple product.
- **Attached options** are part of the price of the cart item, like other custom options, and are taxed with its tax
  class.
- **Unattached options** are added once per cart item, also for a quantity above 1. Their price is added to the row
  total and the subtotal, and their tax is calculated with the tax class of the linked product (or of the selected
  variant).
- **Product Code:** the order item gets the value of the attribute of the selected product as `option_value_code` in
  its options.
- **Attribute Option Mapping:** selecting an attribute option of the product of the page selects attribute options of
  the configurable product, for example the same color. **Attribute Option Preselect** selects attribute options when
  the page is opened.
- When a cart item is edited, the selected products and variants are preselected.
- The options are shown on the pages of simple, configurable and bundle products.

## Events

| Event                                                       | Data                                                              |
|-------------------------------------------------------------|-------------------------------------------------------------------|
| `catalog_product_option_product_price`                      | `data`: `product`, `option`, `price` (changeable)                 |
| `catalog_product_option_product_qty`                        | `data`: `product`, `option`, `qty` (changeable, unattached options) |
| `catalog_product_option_product_init_attribute_mappings`    | `data`: `product`, `option`, `result` (apply the mappings on load) |
| `catalog_product_option_product_init_attribute_preselects`  | `data`: `product`, `option`, `result` (apply the preselects on load) |

[Infrangible Catalog Product Option Qty](https://www.infrangible.dev/modules/catalog-product-option-qty) uses them to
add a quantity to options with a product.

## Other modules

When these modules are installed, options with a product work with them as well:

- [Infrangible Catalog Product Option Price](https://www.infrangible.dev/modules/catalog-product-option-price): the
  prices of unattached options are stored as unattached option prices of the cart items, and the custom options of the
  order items in the API get their price including tax.
- [Infrangible Catalog Product Option Cart](https://www.infrangible.dev/modules/catalog-product-option-cart): the
  options are shown with their price in the cart, unattached options as such.
- [Infrangible Catalog Product Option Code](https://www.infrangible.dev/modules/catalog-product-option-code): the
  option type is one of the types with a value code.

## Requirements

- Magento Open Source or Adobe Commerce 2.4.6 or newer
- PHP 8.2 or newer

## Installation

```bash
composer require infrangible/m2-catalog-product-option-product
bin/magento module:enable Infrangible_CatalogProductOptionProduct
bin/magento setup:upgrade
```

In production mode, also run `bin/magento setup:di:compile`.

## License

Infrangible Catalog Product Option Product is licensed under the MIT License - see the LICENSE file for details.
