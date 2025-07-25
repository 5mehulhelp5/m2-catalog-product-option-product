<?php

declare(strict_types=1);

namespace Infrangible\CatalogProductOptionProduct\Helper;

use FeWeDev\Base\Arrays;
use FeWeDev\Base\Variables;
use Infrangible\Core\Helper\Product;
use Infrangible\Core\Helper\Stores;
use Magento\Catalog\Model\Product\Option;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2025 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Data
{
    /** @var Variables */
    protected $variables;

    /** @var Stores */
    protected $storeHelper;

    /** @var Product */
    protected $productHelper;

    /** @var Arrays */
    protected $arrays;

    /** @var \Magento\Catalog\Model\Product[] */
    private $products = [];

    public function __construct(Variables $variables, Stores $storeHelper, Product $productHelper, Arrays $arrays)
    {
        $this->variables = $variables;
        $this->storeHelper = $storeHelper;
        $this->productHelper = $productHelper;
        $this->arrays = $arrays;
    }

    /**
     * @throws \Exception
     */
    public function getOptionProduct(Option $option): ?\Magento\Catalog\Model\Product
    {
        $optionId = $option->getId();

        if (! array_key_exists(
            $optionId,
            $this->products
        )) {
            $sourceProductId = $this->variables->intValue($option->getData('option_product_id'));

            if ($sourceProductId) {
                $storeId = $this->storeHelper->getStoreId();

                $this->products[ $optionId ] = $this->productHelper->loadProduct(
                    $sourceProductId,
                    $storeId
                );
            }
        }

        return array_key_exists(
            $optionId,
            $this->products
        ) ? $this->products[ $optionId ] : null;
    }

    /**
     * @throws \Exception
     */
    public function getOptionPrice(Option $option, bool $inclTax = true): float
    {
        if (! $option->getData(Option::KEY_PRICE) || ! $inclTax) {
            $optionPrice = 0;

            $product = $this->getOptionProduct($option);

            if ($product && $product->getId()) {
                $optionPrice = $product->getPriceInfo()->getPrice('final_price')->getAmount()->getValue(
                    $inclTax ? [] : ['tax', 'weee_tax']
                );
            }

            if ($inclTax) {
                $option->setPrice(floatval($optionPrice));
            } else {
                return $optionPrice;
            }
        }

        return $option->getData(Option::KEY_PRICE);
    }

    /**
     * @param Option[] $options
     */
    public function prepareProductOptionsConfig(array $config, array $options): array
    {
        foreach ($options as $option) {
            if ($option->getType() === 'product') {
                $optionId = $option->getId();

                $priceConfiguration = $this->arrays->getValue(
                    $config,
                    $optionId,
                    []
                );

                try {
                    $product = $this->getOptionProduct($option);

                    unset($config[ $optionId ]);

                    $config[ $optionId ][ $product->getId() ] = $priceConfiguration;

                    if ($product->getTypeId() === Configurable::TYPE_CODE) {
                        foreach ($this->productHelper->getUsedProductsPrices($product) as $usedProductId =>
                            $usedProductsPrices) {

                            $config[ $optionId ][ $usedProductId ][ 'prices' ] = $usedProductsPrices;
                        }
                    }
                } catch (\Exception $exception) {
                }
            }
        }

        return $config;
    }
}
