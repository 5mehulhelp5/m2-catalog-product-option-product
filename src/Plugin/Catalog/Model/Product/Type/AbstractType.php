<?php

declare(strict_types=1);

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */

namespace Infrangible\CatalogProductOptionProduct\Plugin\Catalog\Model\Product\Type;

use FeWeDev\Base\Variables;
use Infrangible\CatalogProductOptionProduct\Helper\Data;
use Infrangible\Core\Helper\Attribute;
use Magento\Catalog\Model\Product;
use Magento\Framework\Exception\LocalizedException;

/**
 * Adds the code of the selected product to the options of the order item as "option_value_code".
 *
 * The code is the value of the attribute that the option names as "Product Code" (option_product_code_attribute).
 */
class AbstractType
{
    /**
     * @param Data $helper
     * @param Attribute $attributeHelper
     * @param Variables $variables
     */
    public function __construct(
        protected Data $helper,
        protected Attribute $attributeHelper,
        protected Variables $variables
    ) {
    }

    /**
     * Adds the codes of the selected products to the options.
     *
     * @param Product\Type\AbstractType $subject
     * @param array $result
     * @phpstan-param array<string, mixed> $result
     * @param Product $product
     *
     * @return array
     * @phpstan-return array<string, mixed>
     * @throws LocalizedException
     * @noinspection PhpUnusedParameterInspection
     */
    public function afterGetOrderOptions(Product\Type\AbstractType $subject, array $result, Product $product): array
    {
        if (! isset($result[ 'options' ]) || ! is_array($result[ 'options' ])) {
            return $result;
        }

        foreach ($result[ 'options' ] as $key => $optionData) {
            if (! is_array($optionData)) {
                continue;
            }

            $option = $this->helper->getProductOption($product, $optionData[ 'option_id' ] ?? null);

            if ($option === null) {
                continue;
            }

            $code = $this->getCode($option, $optionData[ 'option_value' ] ?? null);

            if ($code !== null) {
                $result[ 'options' ][ $key ][ 'option_value_code' ] = $code;
            }
        }

        return $result;
    }

    /**
     * Returns the value of the code attribute of the selected product.
     *
     * It is null if the option has no code attribute or the product no value.
     *
     * @param Product\Option $option
     * @param mixed $value
     *
     * @return string|null
     * @throws LocalizedException
     */
    private function getCode(Product\Option $option, mixed $value): ?string
    {
        $attributeCode = $this->variables->stringValueOrDefault($option->getData('option_product_code_attribute'));

        if ($attributeCode === '') {
            return null;
        }

        $selectedProduct = $this->helper->getSelectedProduct($option, $value);

        if ($selectedProduct === null) {
            return null;
        }

        $code = $this->attributeHelper->getProductAttributeValue(
            $this->variables->intValue($selectedProduct->getId()),
            $attributeCode,
            $this->variables->intValue($selectedProduct->getStoreId())
        );

        return is_scalar($code) && (string)$code !== '' ? (string)$code : null;
    }
}
