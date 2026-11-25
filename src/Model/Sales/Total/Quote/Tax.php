<?php

declare(strict_types=1);

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */

namespace Infrangible\CatalogProductOptionProduct\Model\Sales\Total\Quote;

use FeWeDev\Base\Variables;
use Magento\Framework\DataObject;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\Quote\Address\Total\AbstractTotal;
use Magento\Quote\Model\Quote\Item\AbstractItem;
use Magento\Tax\Model\Sales\Total\Quote\CommonTaxCollector;

/**
 * Adds the prices of the products of the selected unattached options to the subtotal and the cart items.
 *
 * The prices are those that Magento's tax collector calculated for the associated taxables (see "AssociatedTaxable"),
 * they are added to the prices, row totals and tax amounts of the cart items. The tax itself is already in the tax of
 * the quote.
 */
class Tax extends AbstractTotal
{
    /**
     * @param Variables $variables
     */
    public function __construct(protected Variables $variables)
    {
    }

    /**
     * Adds the prices.
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

        $details = $this->getTaxableDetails($total);

        foreach ($shippingAssignment->getItems() as $item) {
            if (! $item instanceof AbstractItem || $item->getParentItem() !== null) {
                continue;
            }

            $itemDetails = $details[ $this->variables->stringValueOrDefault($item->getData('tax_calculation_item_id')) ]
                ?? [];

            foreach ($itemDetails as $taxableDetails) {
                $this->addPrices($item, $total, $taxableDetails);
            }
        }

        return $this;
    }

    /**
     * Returns the details of the associated taxables of this module that Magento's tax collector calculated.
     *
     * @param Total $total
     *
     * @return array
     * @phpstan-return array<string, list<array<string, mixed>>> details by tax calculation ID of the item
     */
    private function getTaxableDetails(Total $total): array
    {
        $extraTaxableDetails = $total->getData('extra_taxable_details');
        $typeDetails = is_array($extraTaxableDetails) ? $extraTaxableDetails[ AssociatedTaxable::TYPE ] ?? null : null;
        $details = [];

        foreach (is_array($typeDetails) ? $typeDetails : [] as $itemCode => $itemDetails) {
            foreach (is_array($itemDetails) ? $itemDetails : [] as $taxableDetails) {
                if (is_array($taxableDetails)) {
                    $details[ (string)$itemCode ][] = $taxableDetails;
                }
            }
        }

        return $details;
    }

    /**
     * Adds the prices of one associated taxable to the item and the totals.
     *
     * Like Magento's tax collector, the price of the item is in the base currency, the converted price in the currency
     * of the quote.
     *
     * @param AbstractItem $item
     * @param Total $total
     * @param array $details
     * @phpstan-param array<string, mixed> $details
     *
     * @return void
     */
    private function addPrices(AbstractItem $item, Total $total, array $details): void
    {
        $detailsObject = new DataObject($details);

        $rowTotal = $this->getAmount($detailsObject, CommonTaxCollector::KEY_TAX_DETAILS_ROW_TOTAL);
        $rowTotalInclTax = $this->getAmount($detailsObject, CommonTaxCollector::KEY_TAX_DETAILS_ROW_TOTAL_INCL_TAX);
        $rowTax = $this->getAmount($detailsObject, CommonTaxCollector::KEY_TAX_DETAILS_ROW_TAX);
        $baseRowTotal = $this->getAmount($detailsObject, CommonTaxCollector::KEY_TAX_DETAILS_BASE_ROW_TOTAL);
        $baseRowTotalInclTax =
            $this->getAmount($detailsObject, CommonTaxCollector::KEY_TAX_DETAILS_BASE_ROW_TOTAL_INCL_TAX);
        $baseRowTax = $this->getAmount($detailsObject, CommonTaxCollector::KEY_TAX_DETAILS_BASE_ROW_TAX);

        $total->addTotalAmount('subtotal', $rowTotal);
        $total->addBaseTotalAmount('subtotal', $baseRowTotal);

        $totalAmounts = [
            'subtotal_incl_tax'            => $rowTotalInclTax,
            'base_subtotal_incl_tax'       => $baseRowTotalInclTax,
            'base_subtotal_total_incl_tax' => $baseRowTotalInclTax
        ];

        foreach ($totalAmounts as $key => $amount) {
            $total->setData($key, $this->getAmount($total, $key) + $amount);
        }

        foreach (['price' => $baseRowTotal, 'converted_price' => $rowTotal, 'price_incl_tax' => $rowTotalInclTax,
                     'row_total' => $rowTotal, 'row_total_incl_tax' => $rowTotalInclTax, 'tax_amount' => $rowTax,
                     'base_price' => $baseRowTotal, 'base_price_incl_tax' => $baseRowTotalInclTax,
                     'base_row_total' => $baseRowTotal, 'base_row_total_incl_tax' => $baseRowTotalInclTax,
                     'base_tax_amount' => $baseRowTax] as $key => $amount) {

            $item->setData($key, $this->getAmount($item, $key) + $amount);
        }
    }

    /**
     * Returns an amount of the object, 0 if it has none.
     *
     * @param DataObject $object
     * @param string $key
     *
     * @return float
     */
    private function getAmount(DataObject $object, string $key): float
    {
        $amount = $object->getData($key);

        return is_numeric($amount) ? (float)$amount : 0.0;
    }
}
