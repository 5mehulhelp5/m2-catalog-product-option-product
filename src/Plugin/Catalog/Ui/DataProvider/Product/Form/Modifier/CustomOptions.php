<?php

declare(strict_types=1);

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */

namespace Infrangible\CatalogProductOptionProduct\Plugin\Catalog\Ui\DataProvider\Product\Form\Modifier;

use FeWeDev\Base\Arrays;
use Infrangible\CatalogProductOptionProduct\Helper\Data;
use Magento\Catalog\Ui\DataProvider\Product\Form\Modifier\CustomOptions as Modifier;
use Magento\Ui\Component\Form\Element\Checkbox;
use Magento\Ui\Component\Form\Element\DataType\Text;
use Magento\Ui\Component\Form\Element\Input;
use Magento\Ui\Component\Form\Field;

/**
 * Adds the option type "Product" and its fields to the custom options of the product form in the admin.
 */
class CustomOptions
{
    public const FIELD_OPTION_PRODUCT_ID_NAME = 'option_product_id';

    public const FIELD_OPTION_PRODUCT_CODE_ATTRIBUTE_NAME = 'option_product_code_attribute';

    public const FIELD_OPTION_UNATTACHED_NAME = 'option_product_unattached';

    public const FIELD_OPTION_ATTRIBUTE_OPTION_MAPPING_NAME = 'option_product_attribute_option_mapping';

    public const FIELD_OPTION_ATTRIBUTE_OPTION_PRESELECT_NAME = 'option_product_attribute_option_preselect';

    /**
     * @param Arrays $arrays
     */
    public function __construct(protected Arrays $arrays)
    {
    }

    /**
     * Adds the fields to the static fields of the options and the type to the types of the options.
     *
     * @param Modifier $subject
     * @param array $meta
     * @phpstan-param array<int|string, mixed> $meta
     *
     * @return array
     * @phpstan-return array<int|string, mixed>
     * @noinspection PhpUnusedParameterInspection
     */
    public function afterModifyMeta(Modifier $subject, array $meta): array
    {
        $fields = [
            self::FIELD_OPTION_PRODUCT_ID_NAME                => $this->getFieldConfig(__('Product'), 110),
            self::FIELD_OPTION_PRODUCT_CODE_ATTRIBUTE_NAME    => $this->getFieldConfig(__('Product Code'), 111),
            self::FIELD_OPTION_UNATTACHED_NAME                => $this->getUnattachedFieldConfig(112),
            self::FIELD_OPTION_ATTRIBUTE_OPTION_MAPPING_NAME  => $this->getFieldConfig(
                __('Attribute Option Mapping (srcAttrId:srcAttrOptionId:targetAttrId:targetAttrOptionId, ...)'),
                113
            ),
            self::FIELD_OPTION_ATTRIBUTE_OPTION_PRESELECT_NAME => $this->getFieldConfig(
                __('Attribute Option Preselect'),
                114
            )
        ];

        $optionPath = [
            Modifier::GROUP_CUSTOM_OPTIONS_NAME,
            'children',
            Modifier::GRID_OPTIONS_NAME,
            'children',
            'record',
            'children',
            Modifier::CONTAINER_OPTION,
            'children'
        ];

        foreach ($fields as $fieldName => $fieldConfig) {
            $fieldConfig[ 'arguments' ][ 'data' ][ 'config' ][ 'dataScope' ] = $fieldName;

            $meta = $this->arrays->addDeepValue(
                $meta,
                [...$optionPath, Modifier::CONTAINER_TYPE_STATIC_NAME, 'children', $fieldName],
                $fieldConfig
            );
        }

        return $this->arrays->addDeepValue(
            $meta,
            [
                ...$optionPath,
                Modifier::CONTAINER_COMMON_NAME,
                'children',
                Modifier::FIELD_TYPE_NAME,
                'arguments',
                'data',
                'config',
                'groupsConfig',
                Data::OPTION_TYPE
            ],
            [
                'values'  => [Data::OPTION_TYPE],
                'indexes' => [
                    Modifier::CONTAINER_TYPE_STATIC_NAME,
                    Modifier::FIELD_SKU_NAME,
                    self::FIELD_OPTION_PRODUCT_ID_NAME
                ]
            ]
        );
    }

    /**
     * Returns the configuration of a text field.
     *
     * @param mixed $label
     * @param int $sortOrder
     *
     * @return array
     * @phpstan-return array{arguments: array{data: array{config: array<string, mixed>}}}
     */
    protected function getFieldConfig(mixed $label, int $sortOrder): array
    {
        return [
            'arguments' => [
                'data' => [
                    'config' => [
                        'label'         => $label,
                        'componentType' => Field::NAME,
                        'formElement'   => Input::NAME,
                        'dataType'      => Text::NAME,
                        'sortOrder'     => $sortOrder
                    ]
                ]
            ]
        ];
    }

    /**
     * Returns the configuration of the checkbox "Unattached".
     *
     * @param int $sortOrder
     *
     * @return array
     * @phpstan-return array{arguments: array{data: array{config: array<string, mixed>}}}
     */
    protected function getUnattachedFieldConfig(int $sortOrder): array
    {
        return [
            'arguments' => [
                'data' => [
                    'config' => [
                        'label'         => __('Unattached'),
                        'componentType' => Field::NAME,
                        'formElement'   => Checkbox::NAME,
                        'dataType'      => Text::NAME,
                        'sortOrder'     => $sortOrder,
                        'value'         => '0',
                        'valueMap'      => [
                            'true'  => '1',
                            'false' => '0'
                        ]
                    ]
                ]
            ]
        ];
    }
}
