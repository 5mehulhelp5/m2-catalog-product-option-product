<?php

declare(strict_types=1);

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */

namespace Infrangible\CatalogProductOptionProduct\Observer;

use FeWeDev\Base\Arrays;
use Infrangible\CatalogProductOptionProduct\Helper\Data;
use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Framework\DataObject;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order\Item;

/**
 * Sets the price of unattached options with a product in the prices of the order item options.
 *
 * This is the event "order_item_option_price" of Infrangible Catalog Product Option Price. The price is the price of
 * the selected product including its tax; attached options already have their price there.
 */
class OrderItemOptionPrice implements ObserverInterface
{
    /**
     * @param Arrays $arrays
     * @param Data $helper
     * @param CatalogHelper $catalogHelper
     */
    public function __construct(
        protected Arrays $arrays,
        protected Data $helper,
        protected CatalogHelper $catalogHelper
    ) {
    }

    /**
     * Sets the price.
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

        $optionData = $transportObject->getData('item_product_option_data');
        $item = $transportObject->getData('item');

        if (! is_array($optionData) || ! $item instanceof Item ||
            $this->arrays->getValue($optionData, 'option_type') !== Data::OPTION_TYPE) {

            return;
        }

        $parentItem = $item->getParentItem();
        $product = $parentItem instanceof Item ? $parentItem->getProduct() : $item->getProduct();
        $option = $product === null ? null :
            $this->helper->getProductOption($product, $this->arrays->getValue($optionData, 'option_id'));

        if ($option === null || ! $this->helper->isUnattached($option)) {
            return;
        }

        $value = $this->arrays->getValue($optionData, 'option_value');
        $price = $this->helper->getSelectedPrice($option, $value);
        $taxProduct = $this->helper->getSelectedProduct($option, $value) ?? $this->helper->getOptionProduct($option);

        $transportObject->setData(
            'price',
            $taxProduct === null ? $price : $this->catalogHelper->getTaxPrice($taxProduct, $price, true)
        );
    }
}
