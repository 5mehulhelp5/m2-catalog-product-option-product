<?php

declare(strict_types=1);

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */

namespace Infrangible\CatalogProductOptionProduct\Model\Product\Option\Type;

use FeWeDev\Base\Variables;
use Infrangible\CatalogProductOptionProduct\Helper\Data;
use Magento\Catalog\Model\Product\Option\Type\DefaultType;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * The option type "product". The value of the option is the ID of its product, for a configurable product the ID of
 * the selected variant; "0" or no value means that the product was not chosen.
 */
class Product extends DefaultType
{
    /**
     * @param Session $checkoutSession
     * @param ScopeConfigInterface $scopeConfig
     * @param Data $helper
     * @param Variables $variables
     * @param array $data
     * @phpstan-param array<string, mixed> $data
     */
    public function __construct(
        Session $checkoutSession,
        ScopeConfigInterface $scopeConfig,
        protected Data $helper,
        protected Variables $variables,
        array $data = []
    ) {
        parent::__construct($checkoutSession, $scopeConfig, $data);
    }

    /**
     * Returns the price of the selected product.
     *
     * The price of unattached options is not part of the price of the product, the totals of the quote add it once per
     * cart item.
     *
     * @param mixed $optionValue
     * @param mixed $basePrice
     *
     * @return float
     * @throws LocalizedException
     */
    public function getOptionPrice($optionValue, $basePrice): float
    {
        $option = $this->getOption();

        if ($this->helper->isUnattached($option)) {
            return 0.0;
        }

        return $this->helper->getSelectedPrice($option, $optionValue);
    }

    /**
     * Validates the value of the option.
     *
     * It has to be the ID of the product of the option, or of a variant of it. Without value, a required option is
     * reported as missing.
     *
     * @param array $values
     * @phpstan-param array<int|string, mixed> $values
     *
     * @return $this
     * @throws LocalizedException
     */
    public function validateUserValue($values): static
    {
        $option = $this->getOption();
        $optionId = $option->getId();
        $value = $values[ $optionId ] ?? null;

        // an empty value counts as missing, Magento reports missing required options
        if ($this->isEmptyValue($value)) {
            unset($values[ $optionId ]);
        }

        parent::validateUserValue($values);

        if ($this->isEmptyValue($value)) {
            $this->unsetData('user_value');
            $this->setData('is_valid', true);
        } elseif (! $this->helper->isValidValue($option, $value)) {
            throw new LocalizedException(
                __('The selected product of the option "%1" is not available.', $option->getTitle())
            );
        }

        return $this;
    }

    /**
     * Returns the value of the option for the cart, null if no product was chosen.
     *
     * @return mixed
     * @throws LocalizedException
     */
    public function prepareForCart(): mixed
    {
        if (! $this->getData('is_valid')) {
            throw new LocalizedException(
                __('We can\'t add the product to the cart because of an option validation issue.')
            );
        }

        $value = $this->getData('user_value');

        return $this->isEmptyValue($value) ? null : $value;
    }

    /**
     * Returns the name of the selected product.
     *
     * @param mixed $optionValue
     *
     * @return string
     * @throws LocalizedException
     */
    public function getFormattedOptionValue($optionValue): string
    {
        return $this->getProductName($optionValue);
    }

    /**
     * Returns the name of the selected product.
     *
     * @param mixed $optionValue
     *
     * @return string
     * @throws LocalizedException
     */
    public function getPrintableOptionValue($optionValue): string
    {
        return $this->getProductName($optionValue);
    }

    /**
     * Returns the name of the selected product, or the title of the option if the product no longer exists.
     *
     * @param mixed $optionValue
     *
     * @return string
     * @throws LocalizedException
     */
    private function getProductName(mixed $optionValue): string
    {
        $option = $this->getOption();
        $product = $this->helper->getSelectedProduct($option, $optionValue) ?? $this->helper->getOptionProduct($option);

        return $this->variables->stringValueOrDefault(
            $product === null ? $option->getTitle() : $product->getName()
        );
    }

    /**
     * Checks if the value means that no product was chosen.
     *
     * @param mixed $value
     *
     * @return bool
     */
    private function isEmptyValue(mixed $value): bool
    {
        return $value === null || $value === '' || $value === '0' || $value === 0;
    }
}
