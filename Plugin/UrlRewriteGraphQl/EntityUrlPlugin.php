<?php
namespace StorefrontX\ShopByBrandGraphQlExtended\Plugin\UrlRewriteGraphQl;

use Amasty\ShopbyBrand\Helper\Data as AmastyShopByDataHelper;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\UrlRewriteGraphQl\Model\Resolver\EntityUrl;
use Magento\Store\Model\StoreManagerInterface;
use Amasty\ShopbyBase\Model\ResourceModel\OptionSetting\CollectionFactory as AmShopBySettingsCollectionFactory;

/**
 * Class EntityUrlPlugin
 * @package  StorefrontX\ShopByBrandGraphQlExtended\Plugin\UrlRewriteGraphQl
 */
class EntityUrlPlugin
{
    /** @var string  */
    public const AMASTY_BRAND = 'AMASTY_BRAND';

    /**
     * @var AmastyShopByDataHelper
     */
    protected $amastyShopByDataHelper;

    /** @var StoreManagerInterface  */
    protected $storeManager;

    /** @var AmShopBySettingsCollectionFactory  */
    protected $amShopBySettingsCollectionFactory;

    /**
     * Class construct
     *
     * @param AmastyShopByDataHelper $amastyShopByDataHelper
     * @param AmShopBySettingsCollectionFactory $amShopBySettingsCollectionFactory
     * @param StoreManagerInterface $StoreManager
     */
    public function __construct(
        AmastyShopByDataHelper $amastyShopByDataHelper,
        AmShopBySettingsCollectionFactory $amShopBySettingsCollectionFactory,
        StoreManagerInterface $StoreManager
    )
    {
        $this->amastyShopByDataHelper = $amastyShopByDataHelper;
        $this->amShopBySettingsCollectionFactory = $amShopBySettingsCollectionFactory;
        $this->storeManager = $StoreManager;
    }

    /**
     * Clear $_GET param from slug before resolve
     *
     * @param EntityUrl $subject
     * @param array $args
     */
    public function beforeResolve(EntityUrl $subject, ...$args) {
        $url = $args[4]['url'] ?? '';
        if ($url && strpos($url, '?') !== false) {
            $explodedUrl = explode('?', $url);
            $args[4]['url'] = reset($explodedUrl);
        }
        return $args;
    }



    /**
     *
     * In case UrlResolver returns empty response - checks if brand urls exists.
     * Changing response for urls resolver with brand results.
     *
     * @param EntityUrl $subject
     * @param $result
     * @param array $args
     *
     * @return array
     * @throws NoSuchEntityException
     */
    public function afterResolve(EntityUrl $subject, $result, ...$args)
    {
        $url = $args[4]['url'] ?? '';

        $emptyResult = [ null ];
        $urlKey = $this->getUrlKey($url);

        $attributeCode = $this->amastyShopByDataHelper->getBrandAttributeCode();
        /** @var \Amasty\ShopbyBase\Model\ResourceModel\OptionSetting\Collection $collection */
        $collection = $this->amShopBySettingsCollectionFactory->create();

        $brandItem = $collection->addFieldToSelect('*')
            ->addFieldToFilter('filter_code', ['eq' => \Amasty\ShopbyBase\Helper\FilterSetting::ATTR_PREFIX . $attributeCode])
            ->addFieldToFilter('url_alias', ['eq' => $urlKey])
            ->setCurPage(1)
            ->setPageSize(1)
            ->getFirstItem();

        if ($brandId = $brandItem->getData('value')) {
            $result['id'] = $brandId;
            $result['type'] = self::AMASTY_BRAND;
            $result['relative_url'] = $brandItem->getData('url_alias');
        }

        if (!isset($result) || !$result) {
            $result = $emptyResult;
        }
        return $result;
    }


    /**
     * Gets url key of entitny and possible prefix like /tag/ /category/
     *
     * @param string $url
     * @return string
     *
     */
    public function getUrlKey(string $url): string {

        $cleanUrl = trim($url, '/');

        // Remove base url
        $baseUrl = $this->getBaseUrl();
        if ($baseUrl && $baseUrl ==  mb_substr($cleanUrl, 0, strlen($baseUrl))) {
            $cleanUrl = trim(mb_substr($cleanUrl, strlen($baseUrl)), '/');
        }
        // Explode
        $explode = explode('/', $cleanUrl);

        return end($explode);
    }


    /**
     * Get base url of shop
     *
     * @retrun string
     */
    public function getBaseUrl(): string
    {
        /** Support for magento2 frontend */
        $storeId = $this->storeManager->getStore()->getId();
        return $this->storeManager->getStore($storeId)->getBaseUrl();
    }

}

