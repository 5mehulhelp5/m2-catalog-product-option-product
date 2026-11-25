<?php

declare(strict_types=1);

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */

namespace Infrangible\CatalogProductOptionProduct\Test\Unit\Plugin\Catalog\Model\Product\Type;

use FeWeDev\Base\Variables;
use Infrangible\CatalogProductOptionProduct\Helper\Data;
use Infrangible\CatalogProductOptionProduct\Plugin\Catalog\Model\Product\Type\AbstractType;
use Infrangible\CatalogProductOptionProduct\Test\Unit\Models;
use Infrangible\Core\Helper\Attribute;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Option;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;
use ReflectionException;

/**
 * Tests the code of the selected product in the options of order items.
 */
class AbstractTypeTest extends TestCase
{
    use Models;

    /**
     * @throws LocalizedException
     * @throws ReflectionException
     */
    public function testAddsTheCodeOfTheSelectedProduct(): void
    {
        $withCode = $this->createModel(Option::class, 'option_id', ['option_product_code_attribute' => 'sku']);
        $withoutCode = $this->createModel(Option::class, 'option_id');

        $selectedProduct = $this->createStub(Product::class);
        $selectedProduct->method('getId')->willReturn('15');
        $selectedProduct->method('getStoreId')->willReturn(1);

        $helper = $this->createStub(Data::class);
        $options = ['5' => $withCode, '6' => $withoutCode];

        $helper->method('getProductOption')->willReturnCallback(
            fn(Product $product, mixed $optionId): ?Option => $options[ $optionId ] ?? null
        );
        $helper->method('getSelectedProduct')->willReturnCallback(
            fn(Option $option, mixed $value): ?Product => $value === '15' ? $selectedProduct : null
        );

        $attributeHelper = $this->createMock(Attribute::class);
        $attributeHelper->expects($this->once())->method('getProductAttributeValue')->with(15, 'sku', 1)->willReturn(
            'BOTTLE'
        );

        $plugin = new AbstractType($helper, $attributeHelper, new Variables());

        $result = $plugin->afterGetOrderOptions(
            $this->createStub(Product\Type\AbstractType::class),
            [
                'options' => [
                    ['option_id' => '5', 'option_value' => '15'],
                    ['option_id' => '6', 'option_value' => '15'],
                    ['option_id' => '7', 'option_value' => '1'],
                    ['option_value' => '1']
                ]
            ],
            $this->createStub(Product::class)
        );

        $this->assertSame(
            [
                ['option_id' => '5', 'option_value' => '15', 'option_value_code' => 'BOTTLE'],
                ['option_id' => '6', 'option_value' => '15'],
                ['option_id' => '7', 'option_value' => '1'],
                ['option_value' => '1']
            ],
            $result[ 'options' ]
        );
    }
}
