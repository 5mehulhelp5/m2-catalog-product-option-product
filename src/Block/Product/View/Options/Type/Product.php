<?php

declare(strict_types=1);

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */

namespace Infrangible\CatalogProductOptionProduct\Block\Product\View\Options\Type;

use FeWeDev\Base\Json;
use FeWeDev\Base\Variables;
use Infrangible\CatalogProductOptionProduct\Helper\Data;
use Infrangible\Core\Helper\Stores;
use Magento\Catalog\Block\Product\View\Options\AbstractOptions;
use Magento\Catalog\Model\Product\Option;
use Magento\ConfigurableProduct\Helper\Data as ConfigurableHelper;
use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Pricing\Helper\Data as PricingHelper;
use Magento\Framework\View\Element\Template\Context;
use Magento\Swatches\Block\Product\Renderer\Configurable;
use Magento\Swatches\Block\Product\Renderer\ConfigurableFactory;

/**
 * Renders an option with a product on the product page.
 *
 * The option is a checkbox with the name and price of the product, for a configurable product its swatches. The
 * configuration of the swatches comes from Magento's swatch renderer for the product of the option, with the attribute
 * option mappings and preselects of the option.
 *
 * Unlike other option blocks, getProduct() returns the product of the option; getMainProduct() returns the product of
 * the page.
 *
 * Observers of the events "catalog_product_option_product_init_attribute_mappings" and
 * "catalog_product_option_product_init_attribute_preselects" can decide if the mappings and preselects are applied
 * when the swatches are rendered ("result").
 */
class Product extends AbstractOptions
{
    /** @var array<string, Configurable> swatch renderers by option ID */
    private array $swatchRenderers = [];

    /**
     * @param Context $context
     * @param PricingHelper $pricingHelper
     * @param \Magento\Catalog\Helper\Data $catalogData
     * @param Data $helper
     * @param Stores $storeHelper
     * @param ConfigurableHelper $configurableHelper
     * @param Variables $variables
     * @param Json $json
     * @param ManagerInterface $eventManager
     * @param ConfigurableFactory $swatchRendererFactory
     * @param array $data
     * @phpstan-param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        PricingHelper $pricingHelper,
        \Magento\Catalog\Helper\Data $catalogData,
        protected Data $helper,
        protected Stores $storeHelper,
        protected ConfigurableHelper $configurableHelper,
        protected Variables $variables,
        protected Json $json,
        protected ManagerInterface $eventManager,
        protected ConfigurableFactory $swatchRendererFactory,
        array $data = []
    ) {
        parent::__construct($context, $pricingHelper, $catalogData, $data);
    }

    /**
     * Returns the product of the option, null if it has none.
     *
     * @return \Magento\Catalog\Model\Product|null
     */
    public function getProduct(): ?\Magento\Catalog\Model\Product
    {
        return $this->helper->getOptionProduct($this->getOption());
    }

    /**
     * Returns the product of the page.
     *
     * @return \Magento\Catalog\Model\Product
     */
    public function getMainProduct(): \Magento\Catalog\Model\Product
    {
        return parent::getProduct();
    }

    /**
     * Returns the preconfigured value of the option (the ID of the selected product).
     *
     * Options are preconfigured when a cart item is edited, for example.
     *
     * @param Option $option
     *
     * @return mixed
     */
    public function getPreconfiguredValue(Option $option): mixed
    {
        return $this->getMainProduct()->getPreconfiguredValues()->getData('options/' . $option->getId());
    }

    /**
     * Checks if the option is preconfigured with a product.
     *
     * @param Option $option
     *
     * @return bool
     */
    public function isPreconfigured(Option $option): bool
    {
        $value = $this->getPreconfiguredValue($option);

        return is_scalar($value) && (string)$value !== '' && (string)$value !== '0';
    }

    /**
     * Returns the price of the option: the price entered for it, or the final price of its product.
     *
     * @return float
     */
    public function getOptionPrice(): float
    {
        return $this->helper->getOptionPrice($this->getOption());
    }

    /**
     * Returns the price converted into the currency of the store, as number.
     *
     * @param float $price
     *
     * @return float
     */
    public function getCurrencyByStore(float $price): float
    {
        $result = $this->pricingHelper->currencyByStore($price, $this->getMainProduct()->getStore(), false);

        return is_numeric($result) ? (float)$result : 0.0;
    }

    /**
     * Returns the formatted price for the label of the option.
     *
     * @param float $price
     *
     * @return string
     */
    public function formatPrice(float $price): string
    {
        return $this->_formatPrice(['is_percent' => false, 'pricing_value' => $price]);
    }

    /**
     * Returns the number of swatches that are shown per attribute.
     *
     * @return string
     */
    public function getNumberSwatchesPerProduct(): string
    {
        return $this->variables->stringValueOrDefault($this->getSwatchRenderer()?->getNumberSwatchesPerProduct());
    }

    /**
     * Returns the configuration of the configurable product for the swatches.
     *
     * It contains the attribute option mappings and preselects of the option.
     *
     * @return string
     */
    public function getJsonConfig(): string
    {
        $config = $this->json->decode($this->getSwatchRenderer()?->getJsonConfig() ?? '{}');

        $config = is_array($config) ? $config : [];

        $config[ 'optionAttributeMappings' ] = $this->getOptionAttributeMappings();
        $config[ 'initOptionAttributeMappings' ] =
            $this->isInitialized('catalog_product_option_product_init_attribute_mappings');
        $config[ 'optionAttributePreselects' ] = $this->getOptionAttributePreselects();
        $config[ 'initOptionAttributePreselects' ] =
            $this->isInitialized('catalog_product_option_product_init_attribute_preselects');

        return $this->json->encode($config) ?? '{}';
    }

    /**
     * Returns the configuration of the swatches of the configurable product.
     *
     * @return string
     */
    public function getJsonSwatchConfig(): string
    {
        return $this->variables->stringValueOrDefault($this->getSwatchRenderer()?->getJsonSwatchConfig(), '{}');
    }

    /**
     * Returns the URL that loads the images of a variant.
     *
     * @return string
     */
    public function getMediaCallback(): string
    {
        return $this->variables->stringValueOrDefault($this->getSwatchRenderer()?->getMediaCallback());
    }

    /**
     * Returns the sizes of the swatch images.
     *
     * @return string
     */
    public function getJsonSwatchSizeConfig(): string
    {
        return $this->variables->stringValueOrDefault($this->getSwatchRenderer()?->getJsonSwatchSizeConfig(), '{}');
    }

    /**
     * Returns 1 if the swatches show a tooltip, otherwise 0.
     *
     * @return int
     */
    public function getShowSwatchTooltip(): int
    {
        return $this->storeHelper->getStoreConfigFlag('catalog/frontend/show_swatch_tooltip', true) ? 1 : 0;
    }

    /**
     * Returns the attribute option mappings of the option.
     *
     * Selecting the attribute option of the product of the page selects the attribute options of the configurable
     * product.
     *
     * The mappings are entered as "sourceAttributeId:sourceAttributeOptionId:targetAttributeId:targetAttributeOptionId"
     * separated by commas.
     *
     * @return array
     * @phpstan-return array<string, array<string, array<string, list<string>>>>
     */
    public function getOptionAttributeMappings(): array
    {
        $mappings = [];

        foreach ($this->getConfigList('option_product_attribute_option_mapping') as $mapping) {
            $parts = array_map('trim', explode(':', $mapping));

            if (count($parts) === 4) {
                [$sourceAttributeId, $sourceAttributeOptionId, $targetAttributeId, $targetAttributeOptionId] = $parts;

                $mappings[ $sourceAttributeId ][ $sourceAttributeOptionId ][ $targetAttributeId ][] =
                    $targetAttributeOptionId;
            }
        }

        return $mappings;
    }

    /**
     * Returns the preselected attribute options of the configurable product by attribute ID.
     *
     * These are the attribute options of the preconfigured variant, for example when a cart item is edited, otherwise
     * those entered for the option.
     *
     * They are entered as "attributeId:attributeOptionId" separated by commas, or only "attributeId" for the first
     * option of the attribute (0).
     *
     * @return array
     * @phpstan-return array<string, string|int>
     */
    public function getOptionAttributePreselects(): array
    {
        $preconfiguredPreselects = $this->getPreconfiguredPreselects();

        if ($preconfiguredPreselects !== null) {
            return $preconfiguredPreselects;
        }

        $preselects = [];

        foreach ($this->getConfigList('option_product_attribute_option_preselect') as $preselect) {
            $parts = array_map('trim', explode(':', $preselect, 2));

            $preselects[ $parts[ 0 ] ] = $parts[ 1 ] ?? 0;
        }

        return $preselects;
    }

    /**
     * Returns the attribute options of the preconfigured variant by attribute ID, null if there is none.
     *
     * @return array|null
     * @phpstan-return array<string, string|int>|null
     */
    private function getPreconfiguredPreselects(): ?array
    {
        $option = $this->getOption();

        if (! $this->isPreconfigured($option)) {
            return null;
        }

        $variant = $this->helper->getSelectedProduct($option, $this->getPreconfiguredValue($option));
        $product = $this->getProduct();

        if ($variant === null || $product === null || $variant->getId() === $product->getId()) {
            return null;
        }

        $preselects = [];

        foreach ($this->configurableHelper->getAllowAttributes($product) as $attribute) {
            $productAttribute = $attribute->getProductAttribute();

            if ($productAttribute !== null) {
                $attributeOptionId = $variant->getData($productAttribute->getAttributeCode());

                $preselects[ $this->variables->stringValueOrDefault($productAttribute->getId()) ] =
                    is_int($attributeOptionId) ? $attributeOptionId :
                        $this->variables->stringValueOrDefault($attributeOptionId);
            }
        }

        return $preselects;
    }

    /**
     * Returns the entries of a comma separated value of the option.
     *
     * @param string $key
     *
     * @return string[]
     */
    private function getConfigList(string $key): array
    {
        $value = $this->variables->stringValueOrDefault($this->getOption()->getData($key));

        return array_values(array_filter(array_map('trim', explode(',', $value)), fn(string $entry) => $entry !== ''));
    }

    /**
     * Returns if the mappings or preselects are applied when the swatches are rendered.
     *
     * This is true, unless an observer of the event changes it.
     *
     * @param string $eventName
     *
     * @return bool
     */
    private function isInitialized(string $eventName): bool
    {
        $transportObject = new DataObject(
            ['product' => $this->getProduct(), 'option' => $this->getOption(), 'result' => true]
        );

        $this->eventManager->dispatch($eventName, ['data' => $transportObject]);

        return (bool)$transportObject->getData('result');
    }

    /**
     * Returns Magento's swatch renderer for the product of the option, null if the option has no product.
     *
     * @return Configurable|null
     */
    private function getSwatchRenderer(): ?Configurable
    {
        $product = $this->getProduct();

        if ($product === null) {
            return null;
        }

        $key = $this->variables->stringValueOrDefault($this->getOption()->getId());

        if (! isset($this->swatchRenderers[ $key ])) {
            $renderer = $this->swatchRendererFactory->create();

            $renderer->setProduct($product);

            $this->swatchRenderers[ $key ] = $renderer;
        }

        return $this->swatchRenderers[ $key ];
    }
}
