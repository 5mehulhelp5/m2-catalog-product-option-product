<?php

declare(strict_types=1);

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */

namespace Infrangible\CatalogProductOptionProduct\Test\Unit\Model\Product\Option\Type;

use FeWeDev\Base\Variables;
use Infrangible\CatalogProductOptionProduct\Helper\Data;
use Infrangible\CatalogProductOptionProduct\Model\Product\Option\Type\Product;
use Infrangible\CatalogProductOptionProduct\Test\Unit\Models;
use Magento\Catalog\Model\Product as CatalogProduct;
use Magento\Catalog\Model\Product\Option;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;
use ReflectionException;

/**
 * Tests the validation, the cart value and the price of options with the type "product".
 */
class ProductTest extends TestCase
{
    use Models;

    /**
     * Returns the option type for the option, with a helper that accepts the value "5" and returns the price 7.
     *
     * @param array $optionData
     * @phpstan-param array<string, mixed> $optionData
     *
     * @return Product
     * @throws ReflectionException
     */
    private function createType(array $optionData = []): Product
    {
        $helper = $this->createStub(Data::class);
        $helper->method('isValidValue')->willReturnCallback(fn(Option $option, mixed $value): bool => $value === '5');
        $helper->method('getSelectedPrice')->willReturn(7.0);
        $helper->method('isUnattached')->willReturnCallback(
            fn(Option $option): bool => (bool)$option->getData('option_product_unattached')
        );

        $type = new Product(
            $this->createStub(Session::class),
            $this->createStub(ScopeConfigInterface::class),
            $helper,
            new Variables()
        );

        $type->setProduct($this->createStub(CatalogProduct::class));
        $type->setOption(
            $this->createModel(
                Option::class,
                'option_id',
                array_merge(['option_id' => '3', 'title' => 'Bottle', 'type' => 'product'], $optionData)
            )
        );

        return $type;
    }

    /**
     * @throws LocalizedException
     * @throws ReflectionException
     */
    public function testValidValueIsAddedToTheCart(): void
    {
        $type = $this->createType();

        $type->validateUserValue(['3' => '5']);

        $this->assertSame('5', $type->prepareForCart());
    }

    /**
     * @throws LocalizedException
     * @throws ReflectionException
     */
    public function testEmptyValueOfOptionalOptionIsNotAddedToTheCart(): void
    {
        foreach ([[], ['3' => '0'], ['3' => '']] as $values) {
            $type = $this->createType();

            $type->validateUserValue($values);

            $this->assertNull($type->prepareForCart());
        }
    }

    /**
     * @throws LocalizedException
     * @throws ReflectionException
     */
    public function testEmptyValueOfRequiredOptionIsRejected(): void
    {
        $type = $this->createType(['is_require' => '1']);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('The product\'s required option(s) weren\'t entered.');

        $type->validateUserValue(['3' => '0']);
    }

    /**
     * @throws LocalizedException
     * @throws ReflectionException
     */
    public function testValueOfAnotherProductIsRejected(): void
    {
        $type = $this->createType();

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('The selected product of the option "Bottle" is not available.');

        $type->validateUserValue(['3' => '6']);
    }

    /**
     * @throws LocalizedException
     * @throws ReflectionException
     */
    public function testPriceOfUnattachedOptionIsNotPartOfTheProductPrice(): void
    {
        $this->assertSame(7.0, $this->createType()->getOptionPrice('5', 10.0));
        $this->assertSame(0.0, $this->createType(['option_product_unattached' => '1'])->getOptionPrice('5', 10.0));
    }
}
