<?php
/**
 * Copyright © Software Research and Development. All rights reserved.
 * See LICENSE_SOFTWARE_RESEARCH_AND_DEVELOPMENT.txt for license details.
 */
declare(strict_types=1);

namespace SoftwareResearchAndDevelopment\SplitConfigurable\Console\Command;

use Exception;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Setup\CategorySetup;
use Magento\Eav\Api\AttributeRepositoryInterface;
use Magento\Framework\App\State;
use Magento\Framework\Filesystem;
use Magento\InventoryApi\Api\Data\SourceItemInterfaceFactory;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\Catalog\Api\Data\ProuctInterface;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Catalog\Model\ResourceModel\Product as CatalogProductResourceModel;
use Magento\ConfigurableProduct\Helper\Product\Options\Factory as ProductOptionsFactory;
use Magento\Framework\App\Area;

class DataInstall extends Command
{
    /**
     * @param ProductFactory $productFactory
     * @param CatalogProductResourceModel $resourceModel
     * @param SourceItemsSaveInterface $sourceItemsSaveInterface
     * @param SourceItemInterfaceFactory $sourceItem
     * @param AttributeRepositoryInterface $attributeRepository
     * @param ProductOptionsFactory $optionsFactory
     * @param ProductRepositoryInterface $productRepository
     * @param CategorySetup $categorySetup
     * @param StoreManagerInterface $storeManager
     * @param State $state
     * @param Filesystem $filesystem
     * @param ProductInterface $productInterface
     * @param string|null $name
     */
    public function __construct(
        protected ProductFactory $productFactory,
        protected CatalogProductResourceModel $resourceModel,
        protected SourceItemsSaveInterface $sourceItemsSaveInterface,
        protected SourceItemInterfaceFactory $sourceItem,
        protected AttributeRepositoryInterface $attributeRepository,
        protected ProductOptionsFactory $optionsFactory,
        protected ProductRepositoryInterface $productRepository,
        protected CategorySetup $categorySetup,
        protected StoreManagerInterface $storeManager,
        protected State $state,
        protected Filesystem $filesystem,
        protected ProductInterface $productInterface,
        string $name = null
    ) {
        parent::__construct($name);
    }

    /**
     * Configures the current command.
     */
    protected function configure(): void
    {
        $this->setName('srd:data:install')
            ->setDescription('Install Data')
            ->setHelp('No help for this command');

        parent::configure();
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('');
        $output->writeln('<info>Data Processing Started ...</info>');
        $output->writeln('');

        try {
            $this->state->setAreaCode(Area::AREA_ADMINHTML);
            $product = $this->productFactory->create();
            $colorAttr = $this->attributeRepository->get(Product::ENTITY, 'color');
            $sizeAttr = $this->attributeRepository->get(Product::ENTITY, 'size');
            $isSwatchColorAttr = $this->attributeRepository->get(
                Product::ENTITY,
                'is_swatch_color'
            );
            $mergedSwatchesProductIdAttr = $this->attributeRepository->get(
                Product::ENTITY,
                'merged_swatches_product_id'
            );
            $topSetId = $this->categorySetup->getAttributeSetId(Product::ENTITY, 'Top');

            $colorOptions = $colorAttr->getOptions();
            $sizeOptions = $sizeAttr->getOptions();

            array_shift($colorOptions);
            array_shift($sizeOptions);

            $productImages = [
                'Red'       => 'wj01-red_main.jpg',
                'Blue'      => 'wj01-blue_main.jpg',
                'Yellow'    => 'wj01-yellow_main.jpg'
            ];
            $approvedColorOptions   = ['Red', 'Blue', 'Yellow'];
            $approvedSizeOptions    = ['S', 'M', 'L'];

            $productsCategories = [2,8,34,23]; // Default Category, New Luma Yoga Collection, Erin Recommends, Jackets

            $namePart = "Test Product";
            $skuPart = "TEST";
            $simpleProducts = [];
            $sourceItems = [];
            $configurableSwatchColorProductIds = [];

            foreach ($colorOptions as $colorOption) {
                if (in_array($colorOption->getLabel(), $approvedColorOptions)) {
                    foreach ($sizeOptions as $sizeIndex => $sizeOption) {
                        if (in_array($sizeOption->getLabel(), $approvedSizeOptions)) {
                            /**
                             * Create Simple Product
                             */
                            $product->unsetData();
                            $product->setTypeId(Type::TYPE_SIMPLE)
                                ->setAttributeSetId($topSetId)
                                ->setWebsiteIds([$this->storeManager->getDefaultStoreView()->getWebsiteId()])
                                ->setName($namePart .'-'. $sizeOption->getLabel() .'-'. $colorOption->getLabel())
                                ->setSku($skuPart .'-'. $sizeOption->getLabel() .'-'. $colorOption->getLabel())
                                ->setPrice(35)
                                ->setVisibility(Visibility::VISIBILITY_NOT_VISIBLE)
                                ->setStatus(Status::STATUS_ENABLED)
                                // Assign category for product
                                // Default Category, New Luma Yoga Collection, Erin Recommends, Jackets
                                ->setCategoryIds($productsCategories);
                            $product->setCustomAttribute(
                                $colorAttr->getAttributeCode(),
                                $colorOption->getValue()
                            );
                            $product->setCustomAttribute(
                                $sizeAttr->getAttributeCode(),
                                $sizeOption->getValue()
                            );
                            $mediaPath = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA)
                                ->getAbsolutePath();
                            $image = $productImages[$colorOption->getLabel()];
                            $imagePath = $mediaPath .'catalog/product/w/j/'.$image; // path of the image
                            $product->addImageToMediaGallery(
                                $imagePath,
                                ['image', 'small_image', 'thumbnail'],
                                false,
                                false
                            );

                            $newProduct = $this->productRepository->save($product);
                            $newPid = $newProduct->getId();

                            $simpleProducts[$colorOption->getLabel()][] = (int)$newPid;

                            // Update Stock Data
                            $sourceItem = $this->sourceItem->create();
                            $sourceItem->setSourceCode('default');
                            $sourceItem->setQuantity(100);
                            $sourceItem->setSku($skuPart .'-'. $sizeOption->getLabel() .'-'. $colorOption->getLabel());
                            $sourceItem->setStatus(SourceItemInterface::STATUS_IN_STOCK);
                            array_push($sourceItems, $sourceItem);
                        }
                    }
                    //Execute Update Stock Data
                    $this->sourceItemsSaveInterface->execute($sourceItems);

                    /**
                     * Create Configurable Color Swatch Piece Products
                     */
                    $configurable_product = $this->productFactory->create();
                    $configurable_product->setSku($skuPart .'-'. $colorOption->getLabel()); // set sku
                    $configurable_product->setName($namePart .' - '. $colorOption->getLabel()); // set name
                    $configurable_product->setAttributeSetId($topSetId);
                    $configurable_product->setStatus(Status::STATUS_ENABLED);
                    $configurable_product->setTypeId('configurable');
                    $configurable_product->setPrice(0);
                    $configurable_product->setWebsiteIds(
                        [$this->storeManager->getDefaultStoreView()->getWebsiteId()]
                    ); // set website
                    $configurable_product->setVisibility(Visibility::VISIBILITY_BOTH);
                    $configurable_product->setCategoryIds($productsCategories); // set category
                    $configurable_product->setStockData([
                        'use_config_manage_stock' => 1, //'Use config settings' checkbox
                        'manage_stock' => 0, //manage stock
                        'is_in_stock' => 1, //Stock Availability
                    ]);
                    $configurable_product->setCustomAttribute(
                        $isSwatchColorAttr->getAttributeCode(),
                        1
                    );

                    $mediaPath = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA)
                        ->getAbsolutePath();
                    $image = $productImages[$colorOption->getLabel()];
                    $imagePath = $mediaPath .'catalog/product/w/j/'.$image; // path of the image
                    $configurable_product->addImageToMediaGallery(
                        $imagePath,
                        ['image', 'small_image', 'thumbnail'],
                        false,
                        false
                    );

                    // super attribute
                    $sizeAttrId = $sizeAttr->getId();
                    $colorAttrId = $colorAttr->getId();

                    $configurable_product->getTypeInstance()->setUsedProductAttributeIds(
                        [$colorAttrId, $sizeAttrId],
                        $configurable_product
                    ); //attribute ID of attribute 'size_general' in my store
                    $configurableAttributesData = $configurable_product->getTypeInstance()
                        ->getConfigurableAttributesAsArray($configurable_product);
                    $configurable_product->setCanSaveConfigurableAttributes(true);
                    $configurable_product->setConfigurableAttributesData($configurableAttributesData);
                    $configurableProductsData = [];
                    $configurable_product->setConfigurableProductsData($configurableProductsData);
                    $newConfigurableProduct = $this->productRepository->save($configurable_product);

                    // assign simple product ids
                    $newConfigurableProduct->setAssociatedProductIds(
                        $simpleProducts[$colorOption->getLabel()]
                    ); // Setting Associated Products
                    $newConfigurableProduct->setCanSaveConfigurableAttributes(true);
                    $newConfigurableSwatchColorProduct = $this->productRepository->save($newConfigurableProduct);
                    array_push($configurableSwatchColorProductIds, $newConfigurableSwatchColorProduct->getId());
                }
            }

            /**
             * Create Merged Configurable Product
             */
            $simpleProductsFlatten = call_user_func_array('array_merge', array_values($simpleProducts));

            $configurableProduct = $this->productFactory->create();
            $configurableProduct->setSku($skuPart .'-'. 'All'); // set sku
            $configurableProduct->setName($namePart .' - '. 'All'); // set name
            $configurableProduct->setAttributeSetId($topSetId);
            $configurableProduct->setStatus(Status::STATUS_ENABLED);
            $configurableProduct->setTypeId('configurable');
            $configurableProduct->setPrice(0);
            $configurableProduct->setWebsiteIds(
                [$this->storeManager->getDefaultStoreView()->getWebsiteId()]
            ); // set website
            $configurableProduct->setVisibility(Visibility::VISIBILITY_IN_CATALOG);
            $configurableProduct->setCategoryIds([]); // set category
            $configurableProduct->setStockData([
                'use_config_manage_stock' => 1, //'Use config settings' checkbox
                'manage_stock' => 0, //manage stock
                'is_in_stock' => 1, //Stock Availability
            ]);

            $mediaPath = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA)->getAbsolutePath();
            $image = $productImages['Blue'];
            $imagePath = $mediaPath .'catalog/product/w/j/'.$image; // path of the image
            $configurableProduct->addImageToMediaGallery(
                $imagePath,
                ['image', 'small_image', 'thumbnail'],
                false,
                false
            );

            // super attribute
            $sizeAttrId = $sizeAttr->getId();
            $colorAttrId = $colorAttr->getId();

            $configurableProduct->getTypeInstance()
                ->setUsedProductAttributeIds(
                    [$colorAttrId, $sizeAttrId],
                    $configurableProduct
                ); //attribute ID of attribute 'size_general' in my store
            $configurableAttributesData = $configurable_product->getTypeInstance()
                ->getConfigurableAttributesAsArray($configurableProduct);
            $configurableProduct->setCanSaveConfigurableAttributes(true);
            $configurableProduct->setConfigurableAttributesData($configurableAttributesData);
            $configurableProductsData = [];
            $configurableProduct->setConfigurableProductsData($configurableProductsData);
            $newConfigurableProduct = $this->productRepository->save($configurableProduct);

            // assign simple product ids
            $newConfigurableProduct->setAssociatedProductIds($simpleProductsFlatten); // Setting Associated Products
            $newConfigurableProduct->setCanSaveConfigurableAttributes(true);
            $mergedConfigurableProduct = $this->productRepository->save($newConfigurableProduct);
            $mergedConfigurableId = $mergedConfigurableProduct->getId();

            /**
             * Update custom attributes Color Swatch Configurables
             */
            foreach ($configurableSwatchColorProductIds as $configurableSwatchColorProductId) {
                $configurableProduct = $this->productRepository->getById($configurableSwatchColorProductId);
                $configurableProduct->setCustomAttribute(
                    $mergedSwatchesProductIdAttr->getAttributeCode(),
                    $mergedConfigurableId
                );
                $this->productRepository->save($configurableProduct);
            }

            $output->writeln('');
            $output->writeln('<info>'.print_r($simpleProducts).'</info>');
            $output->writeln('');
            return self::SUCCESS;
        } catch (Exception $e) {
            $output->writeln('');
            $output->writeln('<error>'.$e->getMessage().'</error>');
            $output->writeln('<info>'.$e->getTraceAsString().'</info>');
            $output->writeln('');
            return self::FAILURE;
        }
    }
}
