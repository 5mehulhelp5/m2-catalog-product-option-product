<?php

declare(strict_types=1);

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */

namespace Infrangible\CatalogProductOptionProduct\Test\Unit\Helper;

use FeWeDev\Base\Variables;
use Infrangible\CatalogProductOptionProduct\Helper\Data;
use Infrangible\CatalogProductOptionProduct\Test\Unit\Models;
use Infrangible\Core\Helper\Stores;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Configuration\Item\Option\OptionInterface;
use Magento\Catalog\Model\Product\Option;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Pricing\Price\PriceInterface;
use Magento\Framework\Pricing\PriceInfo\Base;
use PHPUnit\Framework\TestCase;
use ReflectionException;

/**
 * Tests the products of options with the type "product", their values and prices.
 */
class DataTest extends TestCase
{
    use Models;

    /** @var array<int, Product> products that the product helper loads by ID */
    private array $products = [];

    /** @var array<int, Product[]> variants of the configurable products by product ID */
    private array $variants = [];

    /** @var int number of loaded products */
    private int $loadCount = 0;

    /** @var array<int, array{name: string, data: DataObject}> dispatched events */
    private array $events = [];

    /**
     * Returns the helper with a product helper that loads the products of the test.
     *
     * @param callable|null $observer called with the name and the data of each event
     *
     * @return Data
     */
    private function createHelper(?callable $observer = null): Data
    {
        $storeHelper = $this->createStub(Stores::class);
        $storeHelper->method('getStoreId')->willReturn(1);

        $productHelper = $this->createStub(\Infrangible\Core\Helper\Product::class);
        $productHelper->method('loadProduct')->willReturnCallback(
            function (int $productId): Product {
                $this->loadCount++;

                return $this->products[ $productId ] ?? $this->createProduct(0);
            }
        );
        $productHelper->method('getUsedProducts')->willReturnCallback(
            fn(Product $product): array => $this->variants[ (int)$product->getId() ] ?? []
        );
        $productHelper->method('getUsedProductsPrices')->willReturnCallback(
            fn(Product $product): array => array_fill_keys(
                array_map(fn(Product $variant) => $variant->getId(), $this->variants[ (int)$product->getId() ] ?? []),
                ['finalPrice' => ['amount' => 1.0]]
            )
        );

        $eventManager = $this->createStub(ManagerInterface::class);
        $eventManager->method('dispatch')->willReturnCallback(
            function (string $eventName, array $data) use ($observer): void {
                $transportObject = $data[ 'data' ] ?? null;

                if ($transportObject instanceof DataObject) {
                    $this->events[] = ['name' => $eventName, 'data' => $transportObject];

                    if ($observer !== null) {
                        $observer($eventName, $transportObject);
                    }
                }
            }
        );

        return new Data(new Variables(), $storeHelper, $productHelper, $eventManager);
    }

    /**
     * Returns a product with the ID, type and final price.
     *
     * @param int $productId 0 for a product that does not exist
     * @param string $typeId
     * @param float $finalPrice
     *
     * @return Product
     */
    private function createProduct(int $productId, string $typeId = 'simple', float $finalPrice = 0.0): Product
    {
        $price = $this->createStub(PriceInterface::class);
        $price->method('getValue')->willReturn($finalPrice);

        $priceInfo = $this->createStub(Base::class);
        $priceInfo->method('getPrice')->willReturn($price);

        $product = $this->createStub(Product::class);
        $product->method('getId')->willReturn($productId > 0 ? (string)$productId : null);
        $product->method('getTypeId')->willReturn($typeId);
        $product->method('getPriceInfo')->willReturn($priceInfo);

        return $product;
    }

    /**
     * Returns an option with the product and the data.
     *
     * @param int $optionId
     * @param int|null $productId
     * @param array $data
     * @phpstan-param array<string, mixed> $data
     *
     * @return Option
     * @throws ReflectionException
     */
    private function createOption(int $optionId, ?int $productId, array $data = []): Option
    {
        return $this->createModel(
            Option::class,
            'option_id',
            array_merge(
                ['option_id' => (string)$optionId, 'type' => Data::OPTION_TYPE, 'option_product_id' => $productId],
                $data
            )
        );
    }

    /**
     * Returns a custom option of a cart item with the value.
     *
     * @param string $value
     *
     * @return OptionInterface
     */
    private function createCustomOption(string $value): OptionInterface
    {
        $customOption = $this->createStub(OptionInterface::class);
        $customOption->method('getValue')->willReturn($value);

        return $customOption;
    }

    /**
     * Adds a configurable product with the ID 10, its final price 30 and the variants 11 (price 31) and 12 (price 32).
     *
     * @return void
     */
    private function addConfigurableProduct(): void
    {
        $this->products[ 10 ] = $this->createProduct(10, Configurable::TYPE_CODE, 30.0);
        $this->variants[ 10 ] = [$this->createProduct(11, 'simple', 31.0), $this->createProduct(12, 'simple', 32.0)];
    }

    /**
     * @throws ReflectionException
     */
    public function testIsProductOption(): void
    {
        $helper = $this->createHelper();

        $this->assertTrue($helper->isProductOption($this->createOption(1, 5)));
        $this->assertFalse($helper->isProductOption($this->createOption(1, 5, ['type' => 'field'])));
    }

    /**
     * @throws ReflectionException
     */
    public function testGetProductOption(): void
    {
        $options = [1 => $this->createOption(1, 5), 2 => $this->createOption(2, null, ['type' => 'field'])];

        $product = $this->createStub(Product::class);
        $product->method('getOptionById')->willReturnCallback(fn(int $optionId) => $options[ $optionId ] ?? null);

        $helper = $this->createHelper();

        $this->assertSame($options[ 1 ], $helper->getProductOption($product, '1'));
        $this->assertNull($helper->getProductOption($product, 2));
        $this->assertNull($helper->getProductOption($product, 3));
        $this->assertNull($helper->getProductOption($product, null));
    }

    /**
     * @throws ReflectionException
     */
    public function testGetOptionProductLoadsTheProductOnce(): void
    {
        $this->products[ 5 ] = $this->createProduct(5);
        $helper = $this->createHelper();
        $option = $this->createOption(1, 5);

        $this->assertSame($this->products[ 5 ], $helper->getOptionProduct($option));
        $this->assertSame($this->products[ 5 ], $helper->getOptionProduct($option));
        $this->assertSame(1, $this->loadCount);
    }

    /**
     * @throws ReflectionException
     */
    public function testGetOptionProductWithoutProduct(): void
    {
        $helper = $this->createHelper();

        $this->assertNull($helper->getOptionProduct($this->createOption(1, null)));
        $this->assertNull($helper->getOptionProduct($this->createOption(2, 99)));
        $this->assertSame(1, $this->loadCount);
    }

    /**
     * @throws ReflectionException
     */
    public function testGetSelectedProductOfConfigurableProduct(): void
    {
        $this->addConfigurableProduct();
        $helper = $this->createHelper();
        $option = $this->createOption(1, 10);

        $this->assertSame($this->variants[ 10 ][ 1 ], $helper->getSelectedProduct($option, '12'));
        $this->assertSame($this->variants[ 10 ][ 0 ], $helper->getSelectedProduct($option, 11));
        $this->assertNull($helper->getSelectedProduct($option, '10'));
        $this->assertNull($helper->getSelectedProduct($option, '5'));
    }

    /**
     * @throws ReflectionException
     */
    public function testGetSelectedProductOfSimpleProduct(): void
    {
        $this->products[ 5 ] = $this->createProduct(5);
        $helper = $this->createHelper();

        $this->assertSame($this->products[ 5 ], $helper->getSelectedProduct($this->createOption(1, 5), '5'));
    }

    /**
     * @throws ReflectionException
     */
    public function testIsValidValue(): void
    {
        $this->products[ 5 ] = $this->createProduct(5);
        $this->addConfigurableProduct();
        $helper = $this->createHelper();
        $simpleOption = $this->createOption(1, 5);
        $configurableOption = $this->createOption(2, 10);

        $this->assertTrue($helper->isValidValue($simpleOption, '5'));
        $this->assertFalse($helper->isValidValue($simpleOption, '6'));
        $this->assertTrue($helper->isValidValue($configurableOption, '11'));
        $this->assertFalse($helper->isValidValue($configurableOption, '10'));
        $this->assertFalse($helper->isValidValue($configurableOption, '5'));
        $this->assertFalse($helper->isValidValue($this->createOption(3, null), '5'));
    }

    /**
     * @throws ReflectionException
     */
    public function testGetOptionPriceIsTheEnteredPriceOrTheProductPrice(): void
    {
        $this->products[ 5 ] = $this->createProduct(5, 'simple', 7.0);
        $helper = $this->createHelper();

        $this->assertSame(7.0, $helper->getOptionPrice($this->createOption(1, 5)));
        $this->assertSame(7.0, $helper->getOptionPrice($this->createOption(2, 5, ['price' => '0.0000'])));
        $this->assertSame(4.5, $helper->getOptionPrice($this->createOption(3, 5, ['price' => '4.5'])));
        $this->assertSame(0.0, $helper->getOptionPrice($this->createOption(4, null)));
    }

    /**
     * @throws ReflectionException
     */
    public function testGetSelectedPriceIsThePriceOfTheVariant(): void
    {
        $this->addConfigurableProduct();
        $helper = $this->createHelper();
        $option = $this->createOption(1, 10);

        $this->assertSame(32.0, $helper->getSelectedPrice($option, '12'));
        $this->assertSame(30.0, $helper->getSelectedPrice($option, '99'));
    }

    /**
     * @throws ReflectionException
     */
    public function testGetCartOptionPriceCanBeChangedByObservers(): void
    {
        $this->products[ 5 ] = $this->createProduct(5, 'simple', 7.0);
        $helper = $this->createHelper(
            function (string $eventName, DataObject $data): void {
                if ($eventName === 'catalog_product_option_product_price') {
                    $data->setData('price', $data->getData('price') * 3);
                }
            }
        );
        $option = $this->createOption(1, 5);
        $product = $this->createProduct(1);

        $this->assertSame(21.0, $helper->getCartOptionPrice($product, $option, '5'));
        $this->assertSame($product, $this->events[ 0 ][ 'data' ]->getData('product'));
        $this->assertSame($option, $this->events[ 0 ][ 'data' ]->getData('option'));
    }

    /**
     * @throws ReflectionException
     */
    public function testGetUnattachedQty(): void
    {
        $option = $this->createOption(1, 5);
        $product = $this->createProduct(1);

        $this->assertSame(1.0, $this->createHelper()->getUnattachedQty($product, $option));

        $helper = $this->createHelper(
            function (string $eventName, DataObject $data): void {
                if ($eventName === 'catalog_product_option_product_qty') {
                    $data->setData('qty', '2');
                }
            }
        );

        $this->assertSame(2.0, $helper->getUnattachedQty($product, $option));
    }

    /**
     * @throws ReflectionException
     */
    public function testGetSelectedUnattachedOptions(): void
    {
        $options = [
            1 => $this->createOption(1, 5, ['option_product_unattached' => '1']),
            2 => $this->createOption(2, 5),
            3 => $this->createOption(3, 5, ['option_product_unattached' => '1']),
            4 => $this->createOption(4, null, ['type' => 'field', 'option_product_unattached' => '1'])
        ];
        $customOptions = [
            'option_ids' => $this->createCustomOption('1,2,3,4'),
            'option_1'   => $this->createCustomOption('5'),
            'option_2'   => $this->createCustomOption('5'),
            'option_3'   => $this->createCustomOption('0'),
            'option_4'   => $this->createCustomOption('x')
        ];

        $product = $this->createStub(Product::class);
        $product->method('getOptionById')->willReturnCallback(fn(int $optionId) => $options[ $optionId ] ?? null);
        $product->method('getCustomOption')->willReturnCallback(fn(string $code) => $customOptions[ $code ] ?? null);

        $selected = $this->createHelper()->getSelectedUnattachedOptions($product);

        $this->assertSame([1], array_keys($selected));
        $this->assertSame($options[ 1 ], $selected[ 1 ][ 'option' ]);
        $this->assertSame('5', $selected[ 1 ][ 'value' ]);
    }

    /**
     * @throws ReflectionException
     */
    public function testPrepareProductOptionsConfig(): void
    {
        $this->products[ 5 ] = $this->createProduct(5);
        $this->addConfigurableProduct();
        $config = [
            '1' => ['prices' => ['finalPrice' => ['amount' => 7.0]]],
            '2' => ['prices' => ['finalPrice' => ['amount' => 30.0]]],
            '3' => ['prices' => ['finalPrice' => ['amount' => 2.0]]]
        ];
        $options = [
            $this->createOption(1, 5),
            $this->createOption(2, 10),
            $this->createOption(3, null, ['type' => 'field']),
            'no option'
        ];

        $result = $this->createHelper()->prepareProductOptionsConfig($config, $options);

        $this->assertSame(['5' => $config[ '1' ]], $result[ '1' ]);
        $this->assertSame(
            [
                '10' => $config[ '2' ],
                '11' => ['prices' => ['finalPrice' => ['amount' => 1.0]]],
                '12' => ['prices' => ['finalPrice' => ['amount' => 1.0]]]
            ],
            $result[ '2' ]
        );
        $this->assertSame($config[ '3' ], $result[ '3' ]);
    }
}
