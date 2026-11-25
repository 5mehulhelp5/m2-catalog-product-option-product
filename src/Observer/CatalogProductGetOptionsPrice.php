<?php

declare(strict_types=1);

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */

namespace Infrangible\CatalogProductOptionProduct\Observer;

use Infrangible\CatalogProductOptionProduct\Helper\Data;
use Magento\Catalog\Model\Product;
use Magento\Framework\DataObject;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Adds the prices of the selected unattached options with a product to the unattached options price of the product
 * (event "catalog_product_get_options_price" of Infrangible Catalog Product Option Price).
 */
class CatalogProductGetOptionsPrice implements ObserverInterface
{
    /**
     * @param Data $helper
     */
    public function __construct(protected Data $helper)
    {
    }

    /**
     * Adds the prices to "options_price_unattached".
     *
     * @param Observer $observer
     *
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $transportObject = $observer->getData('data');

        if (! $transportObject instanceof DataObject) {
            return;
        }

        $product = $transportObject->getData('product');

        if (! $product instanceof Product) {
            return;
        }

        $price = $transportObject->getData('options_price_unattached');
        $price = is_numeric($price) ? (float)$price : 0.0;

        foreach ($this->helper->getSelectedUnattachedOptions($product) as $selectedOption) {
            $price +=
                $this->helper->getCartOptionPrice($product, $selectedOption[ 'option' ], $selectedOption[ 'value' ]);
        }

        $transportObject->setData('options_price_unattached', $price);
    }
}
