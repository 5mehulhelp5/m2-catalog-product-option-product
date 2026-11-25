<?php

declare(strict_types=1);

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */

namespace Infrangible\CatalogProductOptionProduct\Test\Unit\Model\Sales\Total\Quote;

use FeWeDev\Base\Variables;
use Infrangible\CatalogProductOptionProduct\Helper\Data;
use Infrangible\CatalogProductOptionProduct\Model\Sales\Total\Quote\AssociatedTaxable;
use Infrangible\CatalogProductOptionProduct\Model\Sales\Total\Quote\Tax;
use Infrangible\CatalogProductOptionProduct\Test\Unit\Models;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Option;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Api\Data\ShippingInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\Quote\Item;
use Magento\Store\Model\Store;
use Magento\Tax\Model\Config;
use Magento\Tax\Model\Sales\Total\Quote\CommonTaxCollector;
use PHPUnit\Framework\TestCase;
use ReflectionException;

/**
 * Tests the totals of unattached options with a product: their associated taxables and their prices in the items.
 */
class TotalsTest extends TestCase
{
    use Models;

    /**
     * Returns a shipping assignment with the items.
     *
     * @param Item[] $items
     *
     * @return ShippingAssignmentInterface
     */
    private function createShippingAssignment(array $items): ShippingAssignmentInterface
    {
        $shipping = $this->createStub(ShippingInterface::class);
        $shipping->method('getAddress')->willReturn($this->createStub(Address::class));

        $shippingAssignment = $this->createStub(ShippingAssignmentInterface::class);
        $shippingAssignment->method('getShipping')->willReturn($shipping);
        $shippingAssignment->method('getItems')->willReturn($items);

        return $shippingAssignment;
    }

    /**
     * Returns a quote of a store.
     *
     * @return Quote
     */
    private function createQuote(): Quote
    {
        $quote = $this->createStub(Quote::class);
        $quote->method('getStore')->willReturn($this->createStub(Store::class));

        return $quote;
    }

    /**
     * @throws ReflectionException
     */
    public function testAssociatedTaxableReplacesTheTaxablesOfTheUnattachedOptions(): void
    {
        $option = $this->createModel(Option::class, 'option_id', ['option_id' => '7']);

        $optionProduct = $this->createStub(Product::class);
        $optionProduct->method('getData')->willReturnMap([['tax_class_id', null, '4']]);

        $helper = $this->createStub(Data::class);
        $helper->method('getSelectedUnattachedOptions')->willReturn(['7' => ['option' => $option, 'value' => '17']]);
        $helper->method('getSelectedProduct')->willReturn($optionProduct);
        $helper->method('getSelectedPrice')->willReturn(12.0);
        $helper->method('getUnattachedQty')->willReturn(2.0);

        $taxConfig = $this->createStub(Config::class);
        $taxConfig->method('priceIncludesTax')->willReturn(false);

        $priceCurrency = $this->createStub(PriceCurrencyInterface::class);
        $priceCurrency->method('convert')->willReturnCallback(fn(float $amount): float => $amount * 2);

        $weeeTaxable = [CommonTaxCollector::KEY_ASSOCIATED_TAXABLE_TYPE => 'weee', 'code' => 'weee'];
        $oldTaxable = [CommonTaxCollector::KEY_ASSOCIATED_TAXABLE_TYPE => AssociatedTaxable::TYPE, 'code' => 'old'];

        $item = $this->createModel(
            Item::class,
            'item_id',
            ['product' => $this->createStub(Product::class), 'associated_taxables' => [$weeeTaxable, $oldTaxable]]
        );

        $collector = new AssociatedTaxable($helper, $taxConfig, $priceCurrency);

        $collector->collect(
            $this->createQuote(),
            $this->createShippingAssignment([$item]),
            new Total([], $this->createStub(Json::class))
        );

        $taxables = $item->getData('associated_taxables');

        $this->assertIsArray($taxables);
        $this->assertCount(2, $taxables);
        $this->assertSame($weeeTaxable, $taxables[ 0 ]);
        $this->assertSame(AssociatedTaxable::TYPE, $taxables[ 1 ][ CommonTaxCollector::KEY_ASSOCIATED_TAXABLE_TYPE ]);
        $this->assertSame(24.0, $taxables[ 1 ][ CommonTaxCollector::KEY_ASSOCIATED_TAXABLE_UNIT_PRICE ]);
        $this->assertSame(12.0, $taxables[ 1 ][ CommonTaxCollector::KEY_ASSOCIATED_TAXABLE_BASE_UNIT_PRICE ]);
        $this->assertSame(2.0, $taxables[ 1 ][ CommonTaxCollector::KEY_ASSOCIATED_TAXABLE_QUANTITY ]);
        $this->assertSame('4', $taxables[ 1 ][ CommonTaxCollector::KEY_ASSOCIATED_TAXABLE_TAX_CLASS_ID ]);
        $this->assertFalse($taxables[ 1 ][ CommonTaxCollector::KEY_ASSOCIATED_TAXABLE_PRICE_INCLUDES_TAX ]);
    }

    /**
     * @throws ReflectionException
     */
    public function testTaxAddsThePricesOfTheTaxablesToTheItemAndTheSubtotal(): void
    {
        $item = $this->createModel(
            Item::class,
            'item_id',
            [
                'tax_calculation_item_id' => 'sequence-1',
                'price'                   => 50.0,
                'converted_price'         => 100.0,
                'row_total'               => 200.0,
                'row_total_incl_tax'      => 216.5,
                'tax_amount'              => 16.5,
                'base_row_total'          => 100.0
            ]
        );
        $otherItem = $this->createModel(
            Item::class,
            'item_id',
            ['tax_calculation_item_id' => 'sequence-2', 'row_total' => 10.0]
        );

        $total = new Total(
            [
                'extra_taxable_details' => [
                    AssociatedTaxable::TYPE => [
                        'sequence-1' => [
                            [
                                CommonTaxCollector::KEY_TAX_DETAILS_ROW_TOTAL               => 24.0,
                                CommonTaxCollector::KEY_TAX_DETAILS_ROW_TOTAL_INCL_TAX      => 25.98,
                                CommonTaxCollector::KEY_TAX_DETAILS_ROW_TAX                 => 1.98,
                                CommonTaxCollector::KEY_TAX_DETAILS_BASE_ROW_TOTAL          => 12.0,
                                CommonTaxCollector::KEY_TAX_DETAILS_BASE_ROW_TOTAL_INCL_TAX => 12.99,
                                CommonTaxCollector::KEY_TAX_DETAILS_BASE_ROW_TAX            => 0.99
                            ]
                        ]
                    ]
                ],
                'subtotal_incl_tax'     => 226.5
            ],
            $this->createStub(Json::class)
        );
        $total->setTotalAmount('subtotal', 210.0);
        $total->setBaseTotalAmount('subtotal', 105.0);

        $collector = new Tax(new Variables());

        $collector->collect($this->createQuote(), $this->createShippingAssignment([$item, $otherItem]), $total);

        $this->assertSame(234.0, $total->getTotalAmount('subtotal'));
        $this->assertSame(117.0, $total->getBaseTotalAmount('subtotal'));
        $this->assertSame(252.48, $total->getData('subtotal_incl_tax'));
        $this->assertSame(62.0, $item->getData('price'));
        $this->assertSame(124.0, $item->getData('converted_price'));
        $this->assertSame(224.0, $item->getData('row_total'));
        $this->assertSame(242.48, $item->getData('row_total_incl_tax'));
        $this->assertSame(18.48, $item->getData('tax_amount'));
        $this->assertSame(112.0, $item->getData('base_row_total'));
        $this->assertSame(10.0, $otherItem->getData('row_total'));
    }

    /**
     * @throws ReflectionException
     */
    public function testTaxWithoutTaxablesChangesNothing(): void
    {
        $item =
            $this->createModel(Item::class, 'item_id', ['tax_calculation_item_id' => 'sequence-1', 'row_total' => 5.0]);
        $total = new Total([], $this->createStub(Json::class));
        $total->setTotalAmount('subtotal', 5.0);

        (new Tax(new Variables()))->collect($this->createQuote(), $this->createShippingAssignment([$item]), $total);

        $this->assertSame(5.0, $total->getTotalAmount('subtotal'));
        $this->assertSame(5.0, $item->getData('row_total'));
    }
}
