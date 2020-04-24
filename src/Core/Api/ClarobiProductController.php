<?php declare(strict_types=1);

namespace Clarobi\Core\Api;

use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\Framework\Context;
use Clarobi\Service\ClarobiConfigService;
use Clarobi\Service\EncodeResponseService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Shopware\Core\Content\Product\ProductEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Clarobi\Core\Framework\Controller\ClarobiAbstractController;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

/**
 * Class ClarobiProductController
 *
 * @RouteScope(scopes={"storefront"})
 * @package Clarobi\Core\Api
 */
class ClarobiProductController extends ClarobiAbstractController
{
    /**
     * @var EntityRepositoryInterface
     */
    protected $productRepository;

    /**
     * @var EncodeResponseService
     */
    protected $encodeResponse;

    /**
     * @var ClarobiConfigService
     */
    protected $configService;

    const ENTITY_NAME = 'product';

    const IGNORED_KEYS = [
//         'autoIncrement', 'active', 'productNumber', 'stock', 'availableStock', 'available', 'name',
//        'variantRestrictions',  'options', 'visibilities', 'createdAt', 'updatedAt', 'id',
//        'price',
//        'childCount',
        'children',
        'parentId',
        'parent',
        'optionIds',
        'media',
        'properties',
        'propertyIds', 'categories', 'categoryTree', 'taxId', 'manufacturerId', 'unitId', 'displayGroup',
        'manufacturerNumber', 'ean', 'deliveryTimeId', 'deliveryTime', 'restockTime', 'isCloseout', 'purchaseSteps',
        'maxPurchase', 'minPurchase', 'purchaseUnit', 'referenceUnit', 'shippingFree', 'purchasePrice',
        'markAsTopseller', 'weight', 'width', 'height', 'length', 'releaseDate', 'keywords', 'description',
        'metaDescription', 'metaTitle', 'packUnit', 'configuratorGroupConfig', 'tax', 'manufacturer', 'unit', 'prices',
        'listingPrices', 'cover', 'searchKeywords', 'translations', 'tags', 'configuratorSettings',
        'categoriesRo', 'coverId', 'blacklistIds', 'whitelistIds', 'customFields', 'tagIds', 'productReviews',
        'ratingAverage', 'mainCategories', 'seoUrls', 'orderLineItems', 'crossSellings', 'crossSellingAssignedProducts',
        '_uniqueIdentifier', 'versionId', 'translated', 'extensions', 'parentVersionId', 'productManufacturerVersionId',
        'productMediaVersionId'
    ];

    /**
     * @todo add mapping on multiple levels
     */

    /**
     * ClarobiProductController constructor.
     *
     * @param EntityRepositoryInterface $productRepository
     * @param ClarobiConfigService $configService
     * @param EncodeResponseService $encodeResponse
     */
    public function __construct(
        EntityRepositoryInterface $productRepository,
        ClarobiConfigService $configService,
        EncodeResponseService $responseService
    )
    {
        $this->productRepository = $productRepository;
        $this->configService = $configService;
        $this->encodeResponse = $responseService;
    }

    /**
     * @Route("/clarobi/product", name="clarobi.product.list")
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function listAction(Request $request): JsonResponse
    {
        try {
            // Verify request
            $this->verifyParam($request);
            $this->verifyToken($request, $this->configService->getConfigs());
            // Get param
            $from_id = $request->get('from_id');

            $context = Context::createDefaultContext();
            $criteria = new Criteria();
            $criteria->setLimit(1)
                ->addFilter(new RangeFilter('autoIncrement', ['gte' => $from_id]))
                ->addSorting(new FieldSorting('autoIncrement', FieldSorting::ASCENDING))
//                ->addAssociation('parent')
//                ->addAssociation('options')
                ->addAssociation('options.group')
                ->addAssociation('children.options.group')
//                ->addAssociation('variants')
//                ->addAssociation('properties')
            ;

            /** @var EntityCollection $entities */
            $entities = $this->productRepository->search($criteria, $context);

            $mappedEntities = [];
            /** @var ProductEntity $element */
            foreach ($entities->getElements() as $element) {
                // map by ignoring keys
                $mappedEntities[] = $this->mapProductEntity($element->jsonSerialize());
            }
            $lastId = $element->getAutoIncrement();

            return new JsonResponse($this->encodeResponse->encodeResponse(
                $mappedEntities,
                self::ENTITY_NAME,
                $lastId
            ));
        } catch (\Exception $exception) {
            return new JsonResponse(['status' => 'error', 'message' => $exception->getMessage()]);
        }
    }

    /**
     * @param array $product
     * @return array
     */
    private function mapProductEntity($product)
    {
        $mappedKeys['entity_name'] = self::ENTITY_NAME;
        foreach ($product as $key => $value) {
            if (in_array($key, self::IGNORED_KEYS)) {
                continue;
            }
            $mappedKeys[$key] = $value;
        }
        $mappedKeys['type'] = ($product['childCount'] ? 'configurable' : 'simple');

        if ($product['parentId']) {
            $criteria = new Criteria([$product['parentId']]);
            /** @var ProductEntity $parentProduct */
            $parentProduct = $this->productRepository->search($criteria, Context::createDefaultContext())->first();
            $mappedKeys['parent_auto_increment'] = $parentProduct->getAutoIncrement();
        } else {
            $mappedKeys['parent_auto_increment'] = 0;
        }

        $options = $this->getProductOptions($product);
        $mappedKeys['options'] = $options;

        return $mappedKeys;
    }

    private function getProductOptions($product)
    {
        $optionsArray = [];
        // if product is simple - get options
        if (!$product['childCount']) {
//            /** @var PropertyGroupOptionCollection $options */
//            $options = $product['options'];
//            $serOptions = $options->jsonSerialize();
//
//            /** @var PropertyGroupOptionEntity $option */
//            foreach ($serOptions as $option) {
//                $serOpt = $option->jsonSerialize();
//                $group = $option->getGroup()->jsonSerialize();
//                $optionsArray[] = [
//                    'value' => $serOpt['name'],
//                    'label' => $group['name']
//                ];
//            }
            $optionsArray = $this->mapPropertyGroupOptionEntity($product['options']);
        } else {
            // for each children - get options
            /** @var ProductCollection $children */
            $children = $product['children'];
            foreach ($children->getElements() as $element) {
//                /** @var PropertyGroupOptionCollection $options */
//                $options = $element->getOptions();
//                $serOptions = $options->jsonSerialize();
//
//                /** @var PropertyGroupOptionEntity $option */
//                foreach ($serOptions as $option) {
//                    $serOpt = $option->jsonSerialize();
//                    $group = $option->getGroup()->jsonSerialize();
//                    $optionsArray[] = [
//                        'value' => $serOpt['name'],
//                        'label' => $group['name']
//                    ];
//                }
                $optionsArray = array_merge($optionsArray, $this->mapPropertyGroupOptionEntity($element->getOptions()));
//                $optionsArray[] = $this->mapPropertyGroupOptionEntity($element->getOptions());
            }
        }
        return array_unique($optionsArray, SORT_REGULAR);
//        return $optionsArray;
    }

    private function mapPropertyGroupOptionEntity(PropertyGroupOptionCollection $options)
    {
        $mappedOptions = [];
        $serOptions = $options->jsonSerialize();

        /** @var PropertyGroupOptionEntity $option */
        foreach ($serOptions as $option) {
            $serOpt = $option->jsonSerialize();
            $group = $option->getGroup()->jsonSerialize();
            $mappedOptions[] = [
                'value' => $serOpt['name'],
                'label' => $group['name']
            ];
        }

        return $mappedOptions;
    }
}
