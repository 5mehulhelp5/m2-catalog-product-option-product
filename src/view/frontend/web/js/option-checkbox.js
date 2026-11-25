/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */

/**
 * Writes the value of the checkbox of an option with a product (the ID of the product or of the selected variant) into
 * the field of the option, "0" if the checkbox is not checked.
 */
define([
    'jquery'
], function($) {
    'use strict';

    return function(config, element) {
        $(element).on('change', function() {
            $(config.target).val($(this).is(':checked') ? $(this).val() : 0);
        });
    };
});
