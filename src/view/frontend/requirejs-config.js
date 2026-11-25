/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */

const config = {
    map: {
        '*': {
            productOptionSwatches: 'Infrangible_CatalogProductOptionProduct/js/swatch-renderer',
        }
    },
    config: {
        mixins: {
            'Magento_Catalog/js/catalog-add-to-cart': {
                'Infrangible_CatalogProductOptionProduct/js/catalog-add-to-cart-mixin': true
            }
        }
    }
};
