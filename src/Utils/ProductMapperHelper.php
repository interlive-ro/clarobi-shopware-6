<?php declare(strict_types=1);

namespace Clarobi\Utils;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;

/**
 * Class ProductMapperHelper
 * @package Clarobi\Utils
 */
class ProductMapperHelper
{
    protected $connection;

    /**
     * ClarobiOrderController constructor.
     *
     */
    public function __construct(
        Connection $connection
    )
    {
        $this->connection = $connection;
    }

    /**
     * @param $options
     * @param array $properties
     * @return array
     */
    public function mergeOptionsAndProperties($options, $properties)
    {
        $mergedArray = array_merge($options, $properties);
        $mapped = [];

        foreach ($mergedArray as $item) {
            if (!key_exists($item['label'], $mapped)) {
                $mapped[$item['label']] = [
                    'value' => $item['value'],
                    'attribute_id' => $item['attribute_id']
                ];
            } else {
                $oldValues = explode(', ', $mapped[$item['label']]['value']);
                $oldValues[] = $item['value'];
                $mapped[$item['label']]['value'] = implode(', ', $oldValues);
            }
        }

        return $mapped;
    }

    /**
     * @param array $properties
     * @return array
     */
    public function propertiesToMultiValues($properties)
    {
        $propertiesArray = [];

        foreach ($properties as $property) {
            if (!key_exists($property['label'], $propertiesArray)) {
                $propertiesArray[$property['label']] = [
                    'value' => $property['value'],
                    'attribute_id' => $property['attribute_id']
                ];
            } else {
                $oldValues = explode(', ', $propertiesArray[$property['label']]['value']);
                $oldValues[] = $property['value'];
                $propertiesArray[$property['label']]['value'] = implode(', ', $oldValues);
            }
        }

        return $propertiesArray;
    }

    /**
     * @param $product
     * @return array
     */
    public function getProductOptions($product)
    {
        $optionsArray = [];
        // if product is simple - get options
        if (!$product['childCount']) {
            $optionsArray = $this->mapOptionCollection($product['options']);
        } else {
            // for each children - get options
            /** @var ProductCollection $children */
            $children = $product['children'];
            foreach ($children->getElements() as $element) {
                $elementOptions = $this->mapOptionCollection($element->getOptions());
                $optionsArray = array_merge($optionsArray, $elementOptions);
            }
        }

        return array_unique($optionsArray, SORT_REGULAR);
    }

    /**
     * @param PropertyGroupOptionCollection $options
     * @return array
     */
    public function mapOptionCollection(PropertyGroupOptionCollection $options)
    {
        /**
         * Add options to every line item
         * "options":{
         *      "attribute_id": "1",
         *      "item_id": "381", #order item id,// to be set in client
         *      "label": "Manufacturer",
         *      "value": "Made In China"
         * }
         */

        $mappedOptions = [];

        /** @var PropertyGroupOptionEntity $option */
        foreach ($options as $option) {
            $property_group_id = $option->getGroupId();
            $groupLangId = $option->getGroup()->getTranslations()->first()->getLanguageId();

            $attr_id = $property_group_id . $groupLangId;
            $mappedOptions[] = [
                'value' => $option->getName(),
                'label' => $option->getGroup()->getName(),
                'attribute_id' => $attr_id
            ];
        }

        return $mappedOptions;
    }

    /**
     * @param $order
     * @return array
     * @throws DBALException
     */
    public function mapOrderLineItems($order)
    {
        $lineItems = [];

        /** @var OrderLineItemEntity $lineItem */
        foreach ($order['lineItems'] as $lineItem) {
            $item = $lineItem->jsonSerialize();
            if ($lineItem->getType() == 'product') {
                /** @var ProductEntity $product */
                $product = $item['product'];

                // Map product to get less data
                unset($item['product']);

//                $options = $this->getProductOptions($product->jsonSerialize());
                $options = $this->mapOptionCollection($product->getOptions());
                $properties = $this->mapOptionCollection($product->getProperties());
//                $mappedProp = $this->propertiesToMultiValues($properties);

                $parentAutoIncrement = $parentProductNumber = null;
                if ($product->getParentId()) {
                    $result = $this->connection->executeQuery(
                        'SELECT `auto_increment`, `product_number`
                                FROM `product` WHERE id = ' . '0x' . $product->getParentId() . ';'
                    )->fetch();
                    $parentAutoIncrement = $result['auto_increment'];
                    $parentProductNumber = $result['product_number'];
                }

                $item['product'] = [
                    'autoIncrement' => $product->getAutoIncrement(),
                    'productNumber' => $product->getProductNumber(),
                    'childCount' => $product->getChildCount(),
                    'categories' => ($product->getCategories()->first() ?
                        $product->getCategories()->first()->getBreadcrumb()
                        : []
                    ),
                    'parent' => [
                        'autoIncrement' => $parentAutoIncrement,
                        'productNumber' => $parentProductNumber
                    ],
                    'options' => $this->mergeOptionsAndProperties($options, $properties)
//                    'options' => $options,
//                    'properties' => $mappedProp
                ];

                $lineItems[] = $item;
            }
        }

        return $lineItems;
    }
}
