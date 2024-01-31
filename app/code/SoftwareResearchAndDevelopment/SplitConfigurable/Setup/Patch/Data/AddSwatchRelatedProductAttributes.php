<?php
/**
 * Copyright © Software Research and Development. All rights reserved.
 * See LICENSE_SOFTWARE_RESEARCH_AND_DEVELOPMENT.txt for license details.
 */
declare(strict_types=1);

namespace SoftwareResearchAndDevelopment\SplitConfigurable\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Model\Entity\Attribute\Source\Boolean;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Validator\ValidateException;

class AddSwatchRelatedProductAttributes implements DataPatchInterface
{
    /**
     * @param EavSetupFactory $eavSetupFactory
     */
    public function __construct(
        private readonly EavSetupFactory $eavSetupFactory
    ) {
    }

    /**
     * @throws LocalizedException
     * @throws ValidateException
     */
    public function apply(): void
    {
        $eavSetup = $this->eavSetupFactory->create();

        /**
         * Boolean Attribute
         */
        $eavSetup->addAttribute(
            Product::ENTITY,
            'is_swatch_color',
            [
                'group' => 'General',
                'type' => 'int',
                'backend' => '',
                'frontend' => '',
                'label' => 'Is Swatch Color',
                'input' => 'boolean',
                'class' => '',
                'source' => Boolean::class,
                'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
                'visible' => true,
                'required' => true,
                'user_defined' => false,
                'default' => '0',
                'searchable' => false,
                'filterable' => false,
                'comparable' => false,
                'visible_on_front' => false,
                'used_in_product_listing' => false,
                'unique' => false,
                'apply_to' => 'configurable'
            ]
        );

        /**
         * Int Attribute
         */
        $eavSetup->addAttribute(
            Product::ENTITY,
            'merged_swatches_product_id',
            [
                'type' => 'int',
                'backend' => '',
                'frontend' => '',
                'label' => 'Merged Swatches Product ID',
                'input' => 'text',
                'class' => '',
                'source' => '',
                'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
                'visible' => true,
                'required' => false,
                'user_defined' => false,
                'default' => '',
                'searchable' => false,
                'filterable' => false,
                'comparable' => false,
                'visible_on_front' => false,
                'used_in_product_listing' => false,
                'unique' => false,
                'apply_to' => 'configurable'
            ]
        );
    }

    /**
     * @return array|string[]
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @return array|string[]
     */
    public function getAliases(): array
    {
        return [];
    }
}
