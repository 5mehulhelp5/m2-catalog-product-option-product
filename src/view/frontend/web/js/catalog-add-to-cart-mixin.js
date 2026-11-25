/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */

define([
    'jquery'
], function($) {
    'use strict';

    return function (widget) {
        return $.widget('mage.catalogAddToCart', widget, {
            submitForm: function(form) {
                $('#product-options-wrapper .fieldset .field-wrapper .field input[name*="super_attribute"]').remove();

                return this._super(form);
            }
        });
    };
});
