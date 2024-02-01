<?php
/**
 * Copyright © Software Research and Development. All rights reserved.
 * See LICENSE_SOFTWARE_RESEARCH_AND_DEVELOPMENT.txt for license details.
 */
declare(strict_types=1);

namespace SoftwareResearchAndDevelopment\SplitConfigurable\Model\Catalog;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\Data\ProductLinkExtensionFactory;
use Magento\Catalog\Api\Data\ProductLinkInterfaceFactory;
use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Catalog\Model\FilterProductCustomAttribute;
use Magento\Catalog\Model\Product\Attribute\Backend\Media\EntryConverterPool;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Configuration\Item\OptionFactory;
use Magento\Catalog\Model\Product\Image\CacheFactory;
use Magento\Catalog\Model\Product\Link;
use Magento\Catalog\Model\Product\LinkTypeProvider;
use Magento\Catalog\Model\Product\Media\Config;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Url;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Model\ProductLink\CollectionProvider;
use Magento\CatalogInventory\Api\Data\StockItemInterfaceFactory;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Catalog\Model\Product as CatalogModelProduct;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Framework\Api\ExtensionAttribute\JoinProcessorInterface;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\Data\CollectionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Framework\Model\Context;
use Magento\Framework\Module\Manager;
use Magento\Framework\Registry;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Helper\Product as CatalogProductHelper;
use Magento\Catalog\Model\ResourceModel\Product as CatalogProductResourceModel;
use Magento\Catalog\Model\ResourceModel\Product\Collection as CatalogProductCollection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as CatalogProductCollectionFactory;
use Magento\Catalog\Model\Indexer\Product\Flat\Processor as ProductFlatIndexerProcessor;
use Magento\Catalog\Model\Indexer\Product\Price\Processor as ProductPriceIndexerProcessor;
use Magento\Catalog\Model\Indexer\Product\Eav\Processor as ProductEavIndexerProcessor;
use Magento\Eav\Model\Config as EavModelConfig;

class Product extends CatalogModelProduct
{

    public const COLOR_ATTRIBUTE_CODE = 'color';


    public const IS_SWATCH_COLOR_ATTRIBUTE = 'is_swatch_color';

    /**
     * merged_swatches_product_id attribute key
     */
    public const MERGED_SWATCHES_PRODUCT_ID_ATTRIBUTE = 'merged_swatches_product_id';

    /**
     * @var CatalogProductResourceModel $productResourceModel
     */
    protected CatalogProductResourceModel $productResourceModel;

    /**
     * @var ProductFactory $productFactory
     */
    protected ProductFactory $productFactory;

    /**
     * @var CatalogProductCollectionFactory $productCollectionFactory
     */
    protected CatalogProductCollectionFactory $productCollectionFactory;

    public function __construct(
        Context $context,
        Registry $registry,
        ExtensionAttributesFactory $extensionFactory,
        AttributeValueFactory $customAttributeFactory,
        StoreManagerInterface $storeManager,
        ProductAttributeRepositoryInterface $metadataService,
        Url $url,
        Link $productLink,
        OptionFactory $itemOptionFactory,
        StockItemInterfaceFactory $stockItemFactory,
        CatalogModelProduct\OptionFactory $catalogProductOptionFactory,
        Visibility $catalogProductVisibility,
        Status $catalogProductStatus,
        Config $catalogProductMediaConfig,
        Type $catalogProductType,
        Manager $moduleManager,
        CatalogProductHelper $catalogProduct,
        CatalogProductResourceModel $resource,
        CatalogProductCollection $resourceCollection,
        CollectionFactory $collectionFactory,
        Filesystem $filesystem,
        IndexerRegistry $indexerRegistry,
        ProductFlatIndexerProcessor $productFlatIndexerProcessor,
        ProductPriceIndexerProcessor $productPriceIndexerProcessor,
        ProductEavIndexerProcessor $productEavIndexerProcessor,
        CategoryRepositoryInterface $categoryRepository,
        CacheFactory $imageCacheFactory,
        CollectionProvider $entityCollectionProvider,
        LinkTypeProvider $linkTypeProvider,
        ProductLinkInterfaceFactory $productLinkFactory,
        ProductLinkExtensionFactory $productLinkExtensionFactory,
        EntryConverterPool $mediaGalleryEntryConverterPool,
        DataObjectHelper $dataObjectHelper,
        JoinProcessorInterface $joinProcessor,
        CatalogProductResourceModel $productResourceModel,
        ProductFactory $productFactory,
        CatalogProductCollectionFactory $productCollectionFactory,
        array $data = [],
        EavModelConfig$config = null,
        FilterProductCustomAttribute $filterCustomAttribute = null
    ) {
        $this->productResourceModel = $productResourceModel;
        $this->productFactory = $productFactory;
        $this->productCollectionFactory = $productCollectionFactory;
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $storeManager,
            $metadataService,
            $url,
            $productLink,
            $itemOptionFactory,
            $stockItemFactory,
            $catalogProductOptionFactory,
            $catalogProductVisibility,
            $catalogProductStatus,
            $catalogProductMediaConfig,
            $catalogProductType,
            $moduleManager,
            $catalogProduct,
            $resource,
            $resourceCollection,
            $collectionFactory,
            $filesystem,
            $indexerRegistry,
            $productFlatIndexerProcessor,
            $productPriceIndexerProcessor,
            $productEavIndexerProcessor,
            $categoryRepository,
            $imageCacheFactory,
            $entityCollectionProvider,
            $linkTypeProvider,
            $productLinkFactory,
            $productLinkExtensionFactory,
            $mediaGalleryEntryConverterPool,
            $dataObjectHelper,
            $joinProcessor,
            $data,
            $config,
            $filterCustomAttribute
        );
    }

    /**
     * Retrieve Product URL
     *
     * @param bool $useSid
     * @return string
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function getProductUrl($useSid = null): string
    {
        if ($this->getIsSwatchColor()) {
            $swatch_color_piece_product_url = $this->getSwatchColorPieceProductUrl();
            if ($swatch_color_piece_product_url != '') {
                $url = "/".$swatch_color_piece_product_url.".html?".self::COLOR_ATTRIBUTE_CODE."=".
                    $this->getSwatchColorPiece();
            } else {
                $url = $this->getUrlModel()->getProductUrl($this, $useSid);
            }
        } else {
            $url = $this->getUrlModel()->getProductUrl($this, $useSid);
        }
        return $url;
    }

    /**
     * Get product name
     *
     * @return string
     */
    public function getName(): string
    {
        if ($this->getIsSwatchColor()) {
//            $name = self::SWAP_NAME;
            $name = $this->getSwatchColorPieceProductName();

            if ($name == '') {
                $name = $this->_getData(self::NAME);
            }
        } else {
            $name = $this->_getData(self::NAME);
        }
        return $name;
    }

    /**
     * @return bool
     */
    private function getIsSwatchColor(): bool
    {
        return (bool)$this->_resource->getAttributeRawValue(
            $this->getId(),
            self::IS_SWATCH_COLOR_ATTRIBUTE,
            $this->getStore()->getId());
    }

    /**
     * @return string
     * @throws LocalizedException
     */
    private function getSwatchColorPiece(): string
    {
        $swatch_color_piece = '';
        $children_Ids = $this->getTypeInstance()->getChildrenIds($this->getId());
        $productCollectionFactory = $this->productCollectionFactory->create()->addIdFilter($children_Ids);
        $productCollectionFactory->addAttributeToSelect(self::COLOR_ATTRIBUTE_CODE);
        $productCollectionFactory->setPageSize(count($children_Ids))->setCurPage(1);
        $productCollectionFactory->groupByAttribute(self::COLOR_ATTRIBUTE_CODE);
        $product = $productCollectionFactory->getFirstItem();
        if ($product instanceof CatalogModelProduct) {
            $swatch_color_piece = lcfirst(
                $this->_resource->getAttribute(self::COLOR_ATTRIBUTE_CODE)->getFrontend()->getValue($product)
            );
        }
        return $swatch_color_piece;
    }

    /**
     * @return int
     */
    private function getMergedSwatchesProductId(): int
    {
        $value = (int)$this->_resource->getAttributeRawValue(
            $this->getId(),
            self::MERGED_SWATCHES_PRODUCT_ID_ATTRIBUTE,
            $this->getStore()->getId()
        );
        if (empty($value)) {
            $value = 0;
        }
        return $value;
    }

    /**
     * @return CatalogModelProduct|null
     */
    private function getMergedSwatchProduct(): ?CatalogModelProduct
    {
        $product = null;
        $merged_swatches_product_id = $this->getMergedSwatchesProductId();
        if ($merged_swatches_product_id) {
            $product = $this->productFactory->create();
            $this->productResourceModel->load($product, $merged_swatches_product_id);
        }
        return $product;
    }

    /**
     * @return string
     */
    private function getSwatchColorPieceProductName(): string
    {
        $name = '';
        $product = $this->getMergedSwatchProduct();
        if ($product instanceof CatalogModelProduct) {
            $name = $product->getName();
        }
        return $name;
    }

    /**
     * @return string
     */
    private function getSwatchColorPieceProductUrl(): string
    {
        $url = '';
        $product = $this->getMergedSwatchProduct();
        if ($product instanceof CatalogModelProduct) {
            $url = $product->getUrlKey();
        }

        return $url;
    }
}
