# Changelog

## 2.0.0

### Breaking changes

- Requires Magento 2.4.6 or newer and PHP 8.2 or newer. Older versions of Magento and PHP 7.1 to 8.1 are no longer
  supported; use version 1.x for these.
- Requires `infrangible/m2-core` 5.0, `infrangible/m2-catalog-product-option-wrapper` 2.0, `infrangible/m2-foundation`
  3.0 and `fewedev/base` 2.4.3.
- All classes have native types and use constructor property promotion; most constructors changed.
- `Helper\Data::getOptionPrice()` takes only the option; prices are catalog prices without the adjustments for the
  display. New methods return the selected product and price for a value (`getSelectedProduct()`,
  `getSelectedPrice()`), check values (`isValidValue()`) and return the selected unattached options of a cart item.
- `Block\Product\View\Options\Type\Product` takes the configuration of the swatches from Magento's swatch renderer;
  the copied methods of the renderer were removed. `getMainProduct()` returns the product of the page.
- The block `Block\Adminhtml\Product\Edit\Tab\Options\Type\Product` and its template were removed; they were not used
  by the product form and named a template that does not exist.
- The columns are declared in `etc/db_schema.xml` instead of `Setup/InstallSchema.php` and `UpgradeSchema.php`; they
  are unchanged.

### Fixed

- Unattached options were taxed twice in the tax of the order: Magento's tax collector got their prices including tax
  as prices without tax, with the tax class of the main product. In a test with 8.25% tax, the order had 21.06 instead
  of 20.63 tax, and the tax amount of the item did not contain the tax of the unattached options. Now Magento
  calculates their tax once, with the tax class of the linked product, and the item contains it.
- In stores with a display currency other than the base currency, the base price of unattached options was converted
  and the price in the display currency was not.
- The values of the options were not checked: any product ID was accepted, and a required option without a selected
  product ("0") was added to the cart without it.
- When a cart item was edited, the options with a simple product were not checked: the template compared the stored
  product ID with the option ID.
- The labels of the options referred to elements that do not exist.
- The results of the events `catalog_product_option_product_init_attribute_mappings` and
  `catalog_product_option_product_init_attribute_preselects` were swapped on the product page: each decided whether
  the other one was applied when the swatches were rendered.

### Added

- Translations in 24 languages.
- Unit tests.
- README with the fields of the option, the prices, the events and the other modules.

### Changed

- The checkbox of an option sets its value with a JavaScript component instead of a script in the template.
- The swatches no longer write the attribute configuration to the browser console.
- The module is loaded after the modules it uses.
- Checks with PHPStan level 9, the Magento coding standard and unit tests on every push.
