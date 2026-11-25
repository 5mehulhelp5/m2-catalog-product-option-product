<?php

declare(strict_types=1);

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */

namespace Infrangible\CatalogProductOptionProduct\Plugin\Catalog\Model\Product;

use Infrangible\CatalogProductOptionProduct\Helper\Data;

/**
 * Options with a product of saved products return the price of the option as price and regular price.
 */
class Option
{
    /**
     * @param Data $helper
     */
    public function __construct(protected Data $helper)
    {
    }

    /**
     * Returns the price of the option.
     *
     * @param \Magento\Catalog\Model\Product\Option $subject
     * @param mixed $result
     *
     * @return mixed
     */
    public function afterGetPrice(\Magento\Catalog\Model\Product\Option $subject, mixed $result): mixed
    {
        return $this->isSavedProductOption($subject) ? $this->helper->getOptionPrice($subject) : $result;
    }

    /**
     * Returns the price of the option.
     *
     * @param \Magento\Catalog\Model\Product\Option $subject
     * @param mixed $result
     *
     * @return mixed
     */
    public function afterGetRegularPrice(\Magento\Catalog\Model\Product\Option $subject, mixed $result): mixed
    {
        return $this->isSavedProductOption($subject) ? $this->helper->getOptionPrice($subject) : $result;
    }

    /**
     * Checks if the option has a product and belongs to a saved product.
     *
     * @param \Magento\Catalog\Model\Product\Option $option
     *
     * @return bool
     */
    private function isSavedProductOption(\Magento\Catalog\Model\Product\Option $option): bool
    {
        return $this->helper->isProductOption($option) && $option->getProductId() && $option->getOptionId();
    }
}
