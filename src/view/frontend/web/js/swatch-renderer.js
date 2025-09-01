/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2025 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */

define([
    'jquery',
    'domReady',
    'Magento_Swatches/js/swatch-renderer'
], function ($, domReady) {
    'use strict';

    $.widget('mage.productOptionSwatches', $.mage.SwatchRenderer, {
        _init: function ($this, $widget) {
            this._super($this, $widget);

            var self = this;

            domReady(function() {
                $('.swatch-opt .swatch-input').on('change', function() {
                    self.processOptionAttributeMappings($(this));
                });

                self.element.closest('.control').on('catalog-option-qty-init', function() {
                    self.initAttributes(false);
                });
            });
        },

        _OnClick: function ($this, $widget) {
            this._super($this, $widget);

            this.updateSelection($this);
        },

        _RenderControls: function($this, $widget) {
            this._super($this, $widget);

            var self = this;
            var container = this.element;

            $.each(self.options.jsonConfig.attributes, function () {
                var item = this;

                var attributeElement = container.find('[data-attribute-code="' + item.code + '"]');
                var attributeAriaAttribute = attributeElement.find('div');

                $(attributeAriaAttribute).attr('aria-required', false);

                $(attributeAriaAttribute).find('div.swatch-option').each(function() {
                    $(this).attr('title', $(this).data('option-label'));
                });
            });

            self.initAttributes(true);

            container.find('select.catalog-product-option-product-attribute').parent().parent().find('.' + self.options.classes.attributeInput).on('change', function() {
                self.updateSelection($(this));
            });
        },

        initAttributes: function(isInit) {
            var self = this;

            if (! isInit || self.options.jsonConfig.initOptionAttributeMappings) {
                self.processOptionAttributePreselects();
            }

            if (! isInit || self.options.jsonConfig.initOptionAttributePreselects) {
                $('.swatch-opt .swatch-input').each(function () {
                    self.processOptionAttributeMappings($(this));
                });
            }
        },

        _RenderFormInput: function($this, $widget) {
            var formInputElement = this._super($this, $widget);

            var formInput = $(formInputElement);
            formInput.attr('data-validate', '{required: false}');
            formInput.attr('aria-required', false);

            return formInput[0].outerHTML;
        },

        _RenderSwatchSelect: function($this, $widget) {
            var selectHtml = this._super($this, $widget);

            if (selectHtml !== '') {
                var select = $(selectHtml);

                select.addClass('catalog-product-option-product-attribute');

                select.children().each(function() {
                    var option = $(this);

                    if (option.attr('data-option-id') === undefined) {
                        option.attr('data-option-id', option.attr('option-id'));
                    }
                });

                selectHtml = select[0].outerHTML;
            }

            return selectHtml;
        },

        _UpdatePrice: function() {
        },

        _loadMedia: function() {
        },

        updateSelection: function(element) {
            var fieldWrapper = element.closest('.field-wrapper');
            var optionId = fieldWrapper.data('option-id');

            var optionValue = fieldWrapper.find('#checkbox_options_' + optionId);
            var currentProductId = optionValue.val();
            var productId = this.getProductId();

            if (currentProductId !== productId) {
                optionValue.prop('checked', false);
                optionValue.trigger('change');

                optionValue.val(productId);
                optionValue.prop('checked', !!productId);
                optionValue.trigger('change');
            }
        },

        processOptionAttributePreselects: function() {
            var self = this;

            console.log(self.options.jsonConfig.optionAttributePreselects);
            console.log(self.options.jsonConfig.attributes);
            $.each(self.options.jsonConfig.optionAttributePreselects, function(attributeId, attributeOptionId) {
                if (attributeOptionId === 0) {
                    $.each(self.options.jsonConfig.attributes, function(key, attributeData) {
                        if (attributeData.id === attributeId) {
                            var firstAttributeOption = attributeData.options[0];

                            self.options.jsonConfig.optionAttributePreselects[attributeId] = firstAttributeOption.id;
                        }
                    });
                }
            });
            console.log(self.options.jsonConfig.optionAttributePreselects);

            self._EmulateSelectedByAttributeId(self.options.jsonConfig.optionAttributePreselects);
        },

        processOptionAttributeMappings: function(swatchInput) {
            var self = this;

            var productSwatches = $(self.element);

            var selectedAttribute = swatchInput.parent();
            var attributeId = parseInt(selectedAttribute.attr('data-attribute-id'));
            var attributeOptionId = parseInt(selectedAttribute.attr('data-option-selected'));

            if (! attributeId || ! attributeOptionId) {
                return;
            }

            $.each(self.options.jsonConfig.optionAttributeMappings, function(sourceAttributeId, sourceAttributeOptionIds) {
                if (parseInt(sourceAttributeId) === attributeId) {
                    $.each(sourceAttributeOptionIds, function(sourceAttributeOptionId, targetAttributeData) {
                        if (parseInt(sourceAttributeOptionId) === attributeOptionId) {
                            $.each(targetAttributeData, function(targetAttributeId, targetAttributeOptionIds) {
                                $.each(targetAttributeOptionIds, function(key, targetAttributeOptionId) {
                                    var swatchAttribute = productSwatches.find(
                                        'div[data-attribute-id=' + targetAttributeId + '] div[data-option-id=' + targetAttributeOptionId + ']');

                                    if (swatchAttribute.length > 0) {
                                        if (swatchAttribute.hasClass('disabled')) {
                                            productSwatches.find('.swatch-option.selected').each(function() {
                                                $(this).click();
                                            });

                                            productSwatches.find('.swatch-attribute select').each(function() {
                                                $(this).val('');
                                                $(this).find('option[data-option-id="0"]').attr('selected', 'selected');
                                                $(this).trigger('change');
                                            });

                                            if (! swatchAttribute.hasClass('disabled')) {
                                                swatchAttribute.click();
                                            }
                                        } else if (! swatchAttribute.hasClass('selected')) {
                                            swatchAttribute.click();
                                        }
                                    } else {
                                        var swatchSelect = productSwatches.find(
                                            '.swatch-attribute[data-attribute-id=' + targetAttributeId + '] select.swatch-select');

                                        if (swatchSelect.length > 0) {
                                            swatchSelect.find('option').removeAttr('selected');

                                            var swatchAttributeOption = swatchSelect.find(
                                                'option[data-option-id=' + targetAttributeOptionId + ']');

                                            if (swatchAttributeOption.length > 0) {
                                                if (swatchAttributeOption.is(':disabled')) {
                                                    productSwatches.find('.swatch-option.selected').each(function() {
                                                        $(this).click();
                                                    });

                                                    productSwatches.find('.swatch-attribute select').each(function() {
                                                        $(this).val('');
                                                        $(this).find('option[data-option-id="0"]').attr('selected', 'selected');
                                                        $(this).trigger('change');
                                                    });

                                                    if (! swatchAttributeOption.is(':disabled')) {
                                                        swatchAttributeOption.attr('selected', true);
                                                        swatchAttributeOption.trigger('change');
                                                    }

                                                    var firstEnabledSwatchAttributeOption =
                                                        productSwatches.find('.swatch-option:not([disabled="disabled"]):first');

                                                    if (firstEnabledSwatchAttributeOption.length > 0) {
                                                        firstEnabledSwatchAttributeOption.click();
                                                        firstEnabledSwatchAttributeOption.attr('selected', true);
                                                        firstEnabledSwatchAttributeOption.trigger('change');
                                                    }
                                                } else {
                                                    swatchAttributeOption.attr('selected', true);
                                                    swatchAttributeOption.trigger('change');
                                                }
                                            } else {
                                                swatchAttributeOption = swatchSelect.find(
                                                    'option[option-id=' + targetAttributeOptionId + ']');

                                                if (swatchAttributeOption.length > 0) {
                                                    swatchAttributeOption.attr('selected', true);
                                                    swatchAttributeOption.trigger('change');
                                                }
                                            }
                                        }
                                    }
                                });
                            });
                        }
                    });
                }
            });
        }
    });

    return $.mage.productOptionSwatches;
});
