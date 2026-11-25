<?php

declare(strict_types=1);

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */

namespace Infrangible\CatalogProductOptionProduct\Model\Sales\Total\Quote;

use Infrangible\CatalogProductOptionProduct\Helper\Data;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\Quote\Address\Total\AbstractTotal;
use Magento\Quote\Model\Quote\Item\AbstractItem;
use Magento\Tax\Model\Config;
use Magento\Tax\Model\Sales\Total\Quote\CommonTaxCollector;

/**
 * Adds the products of the selected unattached options of the cart items to the items as associated taxables.
 *
 * This happens before the tax is calculated. Magento calculates their tax with the tax class of the product of the
 * option and adds it to the tax of the quote; the collector "Tax" adds their prices to the items and the subtotal.
 */
class AssociatedTaxable extends AbstractTotal
{
    /** The type of the associated taxables. */
    public const TYPE = 'catalog_product_option_product';

    /**
     * @param Data $helper
     * @param Config $taxConfig
     * @param PriceCurrencyInterface $priceCurrency
     */
    public function __construct(
        protected Data $helper,
        protected Config $taxConfig,
        protected PriceCurrencyInterface $priceCurrency
    ) {
    }

    /**
     * Replaces the associated taxables of this type of the items.
     *
     * @param Quote $quote
     * @param ShippingAssignmentInterface $shippingAssignment
     * @param Total $total
     *
     * @return $this
     */
    public function collect(Quote $quote, ShippingAssignmentInterface $shippingAssignment, Total $total): static
    {
        parent::collect($quote, $shippingAssignment, $total);

        $store = $quote->getStore();
        $priceIncludesTax = $this->taxConfig->priceIncludesTax($store);

        foreach ($shippingAssignment->getItems() as $item) {
            if (! $item instanceof AbstractItem) {
                continue;
            }

            $taxables = $this->getOtherTaxables($item);

            if ($item->getParentItem() === null) {
                $product = $item->getProduct();

                foreach ($this->helper->getSelectedUnattachedOptions($product) as $optionId => $selectedOption) {
                    $option = $selectedOption[ 'option' ];
                    $value = $selectedOption[ 'value' ];

                    $taxProduct =
                        $this->helper->getSelectedProduct($option, $value) ?? $this->helper->getOptionProduct($option);
                    $basePrice = $this->helper->getSelectedPrice($option, $value);

                    $taxables[] = [
                        CommonTaxCollector::KEY_ASSOCIATED_TAXABLE_TYPE                  => self::TYPE,
                        CommonTaxCollector::KEY_ASSOCIATED_TAXABLE_CODE                  =>
                            sprintf('%s-%d-%s', self::TYPE, spl_object_id($item), $optionId),
                        CommonTaxCollector::KEY_ASSOCIATED_TAXABLE_UNIT_PRICE            =>
                            $this->priceCurrency->convert($basePrice, $store),
                        CommonTaxCollector::KEY_ASSOCIATED_TAXABLE_BASE_UNIT_PRICE       => $basePrice,
                        CommonTaxCollector::KEY_ASSOCIATED_TAXABLE_QUANTITY              =>
                            $this->helper->getUnattachedQty($product, $option),
                        CommonTaxCollector::KEY_ASSOCIATED_TAXABLE_TAX_CLASS_ID          =>
                            $taxProduct?->getData('tax_class_id'),
                        CommonTaxCollector::KEY_ASSOCIATED_TAXABLE_PRICE_INCLUDES_TAX    => $priceIncludesTax,
                        CommonTaxCollector::KEY_ASSOCIATED_TAXABLE_ASSOCIATION_ITEM_CODE =>
                            CommonTaxCollector::ASSOCIATION_ITEM_CODE_FOR_QUOTE
                    ];
                }
            }

            $item->setData('associated_taxables', $taxables);
        }

        return $this;
    }

    /**
     * Returns the associated taxables of the item that are not of this type (for example of Magento_Weee).
     *
     * @param AbstractItem $item
     *
     * @return array
     * @phpstan-return list<mixed>
     */
    private function getOtherTaxables(AbstractItem $item): array
    {
        $taxables = $item->getData('associated_taxables');

        if (! is_array($taxables)) {
            return [];
        }

        return array_values(
            array_filter(
                $taxables,
                fn(mixed $taxable): bool => ! is_array($taxable) ||
                    ($taxable[ CommonTaxCollector::KEY_ASSOCIATED_TAXABLE_TYPE ] ?? null) !== self::TYPE
            )
        );
    }
}
