<?php

declare(strict_types=1);

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */

namespace Infrangible\CatalogProductOptionProduct\Helper;

use FeWeDev\Base\Variables;
use Infrangible\Core\Helper\Stores;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Option;
use Magento\Catalog\Pricing\Price\FinalPrice;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface;

/**
 * The products of options with the type "product" and their prices.
 *
 * The product of an option is stored in "option_product_id". For a configurable product, the value of the option is
 * the ID of the selected variant.
 *
 * Observers of the event "catalog_product_option_product_price" can change the price of an option in a cart item,
 * observers of the event "catalog_product_option_product_qty" its quantity.
 */
class Data
{
    /** The type of the options with a product. */
    public const OPTION_TYPE = 'product';

    /** @var array<string, Product|null> products by option ID and store ID */
    private array $products = [];

    /**
     * @param Variables $variables
     * @param Stores $storeHelper
     * @param \Infrangible\Core\Helper\Product $productHelper
     * @param ManagerInterface $eventManager
     */
    public function __construct(
        protected Variables $variables,
        protected Stores $storeHelper,
        protected \Infrangible\Core\Helper\Product $productHelper,
        protected ManagerInterface $eventManager
    ) {
    }

    /**
     * Checks if the option has the type "product".
     *
     * @param Option|null $option
     *
     * @return bool
     * @phpstan-assert-if-true Option $option
     */
    public function isProductOption(?Option $option): bool
    {
        return $option !== null && $option->getType() === self::OPTION_TYPE;
    }

    /**
     * Returns the option of the product with the ID, if it has the type "product".
     *
     * @param Product $product
     * @param mixed $optionId
     *
     * @return Option|null
     */
    public function getProductOption(Product $product, mixed $optionId): ?Option
    {
        if (! is_numeric($optionId)) {
            return null;
        }

        $option = $product->getOptionById($this->variables->intValue($optionId));

        return $this->isProductOption($option) ? $option : null;
    }

    /**
     * Checks if the price of the option product is added once per cart item instead of once per piece.
     *
     * @param Option $option
     *
     * @return bool
     */
    public function isUnattached(Option $option): bool
    {
        return (bool)$option->getData('option_product_unattached');
    }

    /**
     * Returns the product of the option in the current store, null if the option has none or it does not exist.
     *
     * @param Option $option
     *
     * @return Product|null
     */
    public function getOptionProduct(Option $option): ?Product
    {
        $productId = $option->getData('option_product_id');

        if (! is_numeric($productId) || $this->variables->intValue($productId) <= 0) {
            return null;
        }

        $productId = $this->variables->intValue($productId);

        $storeId = $this->storeHelper->getStoreId();
        $key = sprintf('%s_%d_%d', $this->variables->stringValueOrDefault($option->getId()), $productId, $storeId);

        if (! array_key_exists($key, $this->products)) {
            $product = $this->productHelper->loadProduct($productId, $storeId);

            $this->products[ $key ] = $product->getId() ? $product : null;
        }

        return $this->products[ $key ];
    }

    /**
     * Returns the product of the option for the value of the option.
     *
     * For a configurable product, this is the variant with the value as ID (null if there is none), for other products
     * the product of the option.
     *
     * @param Option $option
     * @param mixed $value
     *
     * @return Product|null
     */
    public function getSelectedProduct(Option $option, mixed $value): ?Product
    {
        $product = $this->getOptionProduct($option);

        if ($product === null || $product->getTypeId() !== Configurable::TYPE_CODE) {
            return $product;
        }

        $variantId = $this->variables->stringValueOrDefault($value);

        foreach ($this->productHelper->getUsedProducts($product) as $usedProduct) {
            if ($this->variables->stringValueOrDefault($usedProduct->getId()) === $variantId) {
                return $usedProduct;
            }
        }

        return null;
    }

    /**
     * Checks if the value of the option selects its product.
     *
     * The value has to be the ID of the product, for a configurable product the ID of one of its variants.
     *
     * @param Option $option
     * @param mixed $value
     *
     * @return bool
     */
    public function isValidValue(Option $option, mixed $value): bool
    {
        $product = $this->getOptionProduct($option);

        if ($product === null) {
            return false;
        }

        if ($product->getTypeId() === Configurable::TYPE_CODE) {
            return $this->getSelectedProduct($option, $value) !== null;
        }

        return $this->variables->stringValueOrDefault($value) ===
            $this->variables->stringValueOrDefault($product->getId());
    }

    /**
     * Returns the price of the option.
     *
     * This is the price entered for the option, or the final price of its product, if no price was entered. The price
     * is a catalog price, with tax if the catalog prices include tax.
     *
     * @param Option $option
     *
     * @return float
     */
    public function getOptionPrice(Option $option): float
    {
        $price = $option->getData(Option::KEY_PRICE);

        if (is_numeric($price) && (float)$price > 0) {
            return (float)$price;
        }

        $product = $this->getOptionProduct($option);

        return $product === null ? 0.0 : $this->getProductPrice($product);
    }

    /**
     * Returns the price of the option for the value of the option.
     *
     * This is the final price of the selected variant of a configurable product, otherwise the price of the option.
     *
     * @param Option $option
     * @param mixed $value
     *
     * @return float
     */
    public function getSelectedPrice(Option $option, mixed $value): float
    {
        $product = $this->getOptionProduct($option);

        if ($product !== null && $product->getTypeId() === Configurable::TYPE_CODE) {
            $variant = $this->getSelectedProduct($option, $value);

            if ($variant !== null) {
                return $this->getProductPrice($variant);
            }
        }

        return $this->getOptionPrice($option);
    }

    /**
     * Returns the price of the option for the value of the option in the cart item of the product.
     *
     * Observers of the event "catalog_product_option_product_price" can change it, for example to multiply it with a
     * quantity.
     *
     * @param Product $product the product of the cart item
     * @param Option $option
     * @param mixed $value
     *
     * @return float
     */
    public function getCartOptionPrice(Product $product, Option $option, mixed $value): float
    {
        $transportObject = new DataObject(
            ['product' => $product, 'option' => $option, 'price' => $this->getSelectedPrice($option, $value)]
        );

        $this->eventManager->dispatch('catalog_product_option_product_price', ['data' => $transportObject]);

        $price = $transportObject->getData('price');

        return is_numeric($price) ? (float)$price : 0.0;
    }

    /**
     * Returns the quantity of the product of the unattached option in the cart item of the product.
     *
     * It is 1, unless an observer of the event "catalog_product_option_product_qty" changes it.
     *
     * @param Product $product the product of the cart item
     * @param Option $option
     *
     * @return float
     */
    public function getUnattachedQty(Product $product, Option $option): float
    {
        $transportObject = new DataObject(['product' => $product, 'option' => $option, 'qty' => 1]);

        $this->eventManager->dispatch('catalog_product_option_product_qty', ['data' => $transportObject]);

        $qty = $transportObject->getData('qty');

        return is_numeric($qty) ? (float)$qty : 1.0;
    }

    /**
     * Returns the selected unattached options of the product of a cart item, with the selected values by option ID.
     *
     * @param Product $product the product of the cart item, with the custom options of the item
     *
     * @return array
     * @phpstan-return array<int|string, array{option: Option, value: mixed}>
     */
    public function getSelectedUnattachedOptions(Product $product): array
    {
        $optionIds = $product->getCustomOption('option_ids')?->getValue();

        if (! is_string($optionIds) || $optionIds === '') {
            return [];
        }

        $options = [];

        foreach (explode(',', $optionIds) as $optionId) {
            $option = $this->getProductOption($product, $optionId);

            if ($option === null || ! $this->isUnattached($option)) {
                continue;
            }

            $value = $product->getCustomOption('option_' . $optionId)?->getValue();

            if ($value !== null && $value !== '' && $value !== '0') {
                $options[ $optionId ] = ['option' => $option, 'value' => $value];
            }
        }

        return $options;
    }

    /**
     * Returns the final price of the product as catalog price, without the adjustments for the display (tax).
     *
     * @param Product $product
     *
     * @return float
     */
    public function getProductPrice(Product $product): float
    {
        return $product->getPriceInfo()->getPrice(FinalPrice::PRICE_CODE)->getValue();
    }

    /**
     * Adds the products to the price configuration of the options with a product.
     *
     * The configuration of each option is stored under the ID of its product, the prices of the variants of a
     * configurable product under their IDs. The price box of the product page reads the configuration by the value of
     * the option, which is the ID of the product.
     *
     * @param array $config
     * @phpstan-param array<int|string, mixed> $config
     * @param array $options
     * @phpstan-param array<int|string, mixed> $options
     *
     * @return array
     * @phpstan-return array<int|string, mixed>
     */
    public function prepareProductOptionsConfig(array $config, array $options): array
    {
        foreach ($options as $option) {
            if (! $option instanceof Option || ! $this->isProductOption($option)) {
                continue;
            }

            $product = $this->getOptionProduct($option);

            if ($product === null) {
                continue;
            }

            $optionId = $this->variables->stringValueOrDefault($option->getId());
            $priceConfiguration = $config[ $optionId ] ?? [];

            $config[ $optionId ] = [$this->variables->stringValueOrDefault($product->getId()) => $priceConfiguration];

            if ($product->getTypeId() === Configurable::TYPE_CODE) {
                foreach ($this->productHelper->getUsedProductsPrices($product) as $usedProductId => $prices) {
                    $config[ $optionId ][ $usedProductId ][ 'prices' ] = $prices;
                }
            }
        }

        return $config;
    }
}
