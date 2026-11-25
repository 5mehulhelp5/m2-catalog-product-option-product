<?php

declare(strict_types=1);

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */

namespace Infrangible\CatalogProductOptionProduct\Observer;

use Infrangible\CatalogProductOptionProduct\Helper\Data;
use Infrangible\Core\Helper\Registry;
use Magento\Catalog\Model\Product;
use Magento\Framework\DataObject;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Stores the price configuration of the options with a product under the IDs of their products.
 *
 * The IDs of the products are the values of these options. The event "catalog_product_option_price_configuration_after"
 * passes the options only from Infrangible Catalog Product Option; Magento's block of the options passes none, then
 * the options of the current product are used.
 */
class CatalogProductOptionPriceConfigurationAfter implements ObserverInterface
{
    /**
     * @param Data $helper
     * @param Registry $registryHelper
     */
    public function __construct(
        protected Data $helper,
        protected Registry $registryHelper
    ) {
    }

    /**
     * Changes the configuration.
     *
     * @param Observer $observer
     *
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $configObject = $observer->getData('configObj');

        if (! $configObject instanceof DataObject) {
            return;
        }

        $config = $configObject->getData('config');
        $options = $observer->getData('options') ?? $this->getCurrentProductOptions();

        if (! is_array($config) || ! is_array($options)) {
            return;
        }

        $configObject->setData('config', $this->helper->prepareProductOptionsConfig($config, $options));
    }

    /**
     * Returns the options of the current product, null if there is no current product.
     *
     * @return array|null
     * @phpstan-return array<int|string, mixed>|null
     */
    private function getCurrentProductOptions(): ?array
    {
        $product = $this->registryHelper->registry('current_product');

        return $product instanceof Product ? $product->getOptions() : null;
    }
}
