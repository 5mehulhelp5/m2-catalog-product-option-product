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
 * Adds the price of an option with a product to the options of a cart item.
 *
 * This is the event "catalog_product_option_price_item_options" of Infrangible Catalog Product Option Cart. Unattached
 * options are marked as such.
 */
class CatalogProductOptionPriceItemOptions implements ObserverInterface
{
    /**
     * @param Data $helper
     */
    public function __construct(protected Data $helper)
    {
    }

    /**
     * Sets the price, "display" and "attached".
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

        $product = $item->getProduct();
        $option = $this->helper->getProductOption($product, $transportObject->getData('option_id'));

        if ($option === null) {
            return;
        }

        $value = $product->getCustomOption('option_' . $option->getId())?->getValue();

        $transportObject->setData('price', $this->helper->getCartOptionPrice($product, $option, $value));
        $transportObject->setData('display', true);
        $transportObject->setData('attached', ! $this->helper->isUnattached($option));
    }
}
