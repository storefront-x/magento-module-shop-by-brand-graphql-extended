<?php
declare(strict_types=1);

namespace StorefrontX\ShopByBrandGraphQlExtended\Model\Resolver;

use Magento\Catalog\Model\Product\Attribute\Repository as AttributeRepository;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;

use Magento\Eav\Model\Entity\Attribute\Option;
use Amasty\ShopbyBase\Model\OptionSetting;
use Amasty\ShopbyBrand\Block\Widget\BrandList as BrandsWidget;
use Amasty\ShopbyBase\Model\ResourceModel\OptionSetting\CollectionFactory as AmShopBySettingsCollectionFactory;
use Amasty\ShopbyBrand\Helper\Data as AmastyShopByDataHelper;
use Magento\Store\Model\StoreManagerInterface;
use Amasty\ShopbyBrand\Model\ProductCount;
use Amasty\ShopbyBase\Helper\FilterSetting;

/**
 * Class GetAmBrandById
 * @package  StorefrontX\ShopByBrandGraphQlExtended\Model\Resolver
 */
class GetAmBrandById implements ResolverInterface
{
    /**
     * @var BrandsWidget
     */
    protected $brandList;

    /** @var AmShopBySettingsCollectionFactory  */
    protected $amShopBySettingsCollectionFactory;

    /**
     * @var AmastyShopByDataHelper
     */
    protected $amastyShopByDataHelper;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var AttributeRepository
     */
    protected $attributeRepository;

    /** @var ProductCount  */
    protected $productCount;

    /**
     * Class Construct
     *
     * @param  BrandsWidget $brandList
     * @param  AmShopBySettingsCollectionFactory $amShopBySettingsCollectionFactory
     * @param  AmastyShopByDataHelper $amastyShopByDataHelper
     * @param  ProductCount $productCount
     * @param  StoreManagerInterface $storeManager
     * @param  AttributeRepository $attributeRepository
     */
    public function __construct(
        BrandsWidget $brandList,
        AmShopBySettingsCollectionFactory $amShopBySettingsCollectionFactory,
        AmastyShopByDataHelper $amastyShopByDataHelper,
        ProductCount $productCount,
        StoreManagerInterface $storeManager,
        AttributeRepository $attributeRepository
    ) {
        $this->brandList = $brandList;
        $this->amShopBySettingsCollectionFactory = $amShopBySettingsCollectionFactory;
        $this->productCount = $productCount;
        $this->amastyShopByDataHelper = $amastyShopByDataHelper;
        $this->storeManager = $storeManager;
        $this->attributeRepository = $attributeRepository;
    }

    /**
     * @param Field $field
     * @param \Magento\Framework\GraphQl\Query\Resolver\ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return array|\Magento\Framework\GraphQl\Query\Resolver\Value|mixed
     * @throws \Exception|GraphQlInputException|GraphQlNoSuchEntityException
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        $brandId = $this->getBrandId($args);

        if ( !$brandId) {
            throw new GraphQlInputException(__('Brand id should be specified'));
        }

        $attributeCode = $this->amastyShopByDataHelper->getBrandAttributeCode();
        /** @var \Amasty\ShopbyBase\Model\ResourceModel\OptionSetting\Collection $collection */
        $collection = $this->amShopBySettingsCollectionFactory->create();

        /** @var OptionSetting $setting */
        $setting = $collection->addFieldToSelect('*')
            ->addFieldToFilter('filter_code', ['eq' => FilterSetting::ATTR_PREFIX . $attributeCode])
            ->addFieldToFilter('value', ['eq' => $brandId])
            ->setCurPage(1)
            ->setPageSize(1)
            ->getFirstItem();

        if (!$setting->getData('value')) {
            throw new GraphQlNoSuchEntityException(__('Brand not found'));
        }

        /** @var Option $brandOption */
        $brandOption = $this->getBrandOption($attributeCode, $setting);

        return $this->prepareResult($setting, $brandOption);
    }

    /**
     * Prepare formatted response result
     *
     * @param OptionSetting $setting
     * @param Option $brandOption
     *
     * @return array
     */
    public function prepareResult($setting, $brandOption) {
        $count = $this->productCount->get($setting->getData('value'));
        $baseUrl = $this->storeManager->getStore()->getBaseUrl();
        $label = $setting->getLabel() ?: $brandOption->getLabel();
        return [
            'brandId' => $setting->getData('value'),
            'label' => $label,
            'url' => $setting->getData('url_alias'),
            'img' => str_replace($baseUrl, '', $setting->getSliderImageUrl()),
            'image' =>  str_replace($baseUrl, '', $setting->getImageUrl()),
            'description' => $setting->getDescription(true),
            'short_description' => $setting->getShortDescription(),
            'cnt' => $count,
            'letter' => $this->brandList->getLetter($label),
            'alt' => $setting->getSmallImageAlt() ?: $setting->getLabel()
        ];
    }

    /**
     * Gets brand Id attribute from input args
     *
     * @param array $args
     *
     * @return int
     */
    private function getBrandId(array $args): int
    {
        return (int)($args['brandId'] ?? 0);
    }

    /**
     *
     * Gets brand option from available attribute options
     *
     * @param string $attributeCode
     * @param OptionSetting $setting
     *
     * @return null|Option
     */
    private function getBrandOption($attributeCode, $setting) {
        $brandOption = null;
        $options = $this->attributeRepository->get($attributeCode)->getOptions();
        foreach ($options as $option) {
            if ($option->getValue() == $setting->getData('value')) {
                $brandOption = $option;
                break;
            }
        }
        return $brandOption;
    }

}
