<?php

declare(strict_types=1);

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */

namespace Infrangible\CatalogProductOptionProduct\Observer;

use Infrangible\CatalogProductOptionProduct\Helper\Data;
use Magento\Framework\DataObject;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Model\Quote\Item\AbstractItem;

/**
 * Hides options with a product from the options of the cart item renderer.
 *
 * This is the event "catalog_product_option_price_item_renderer" of Infrangible Catalog Product Option Cart; the
 * options are shown with their price.
 */
class CatalogProductOptionPriceItemRenderer implements ObserverInterface
{
    /**
     * @param Data $helper
     */
    public function __construct(protected Data $helper)
    {
    }

    /**
     * Sets "display" to false for options with a product.
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

        $item = $transportObject->getData('item');

        if (! $item instanceof AbstractItem) {
            return;
        }

        if ($this->helper->getProductOption($item->getProduct(), $transportObject->getData('option_id')) !== null) {
            $transportObject->setData('display', false);
        }
    }
}
