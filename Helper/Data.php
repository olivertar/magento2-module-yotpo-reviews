<?php

namespace Yotpo\Yotpo\Helper;

use Magento\Catalog\Helper\Image as CatalogImageHelper;
use Magento\Catalog\Model\Product;
use Magento\Framework\App\Area;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Escaper;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Magento\Sales\Model\Order;
use Magento\Store\Model\App\Emulation as AppEmulation;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    const MODULE_NAME = 'Yotpo_Yotpo';

    //= General Settings
    const XML_PATH_YOTPO_ALL = "yotpo";
    const XML_PATH_YOTPO_ENABLED = "yotpo/settings/active";
    const XML_PATH_YOTPO_APP_KEY = 'yotpo/settings/app_key';
    const XML_PATH_YOTPO_SECRET = 'yotpo/settings/secret';
    const XML_PATH_YOTPO_WIDGET_ENABLED = 'yotpo/settings/widget_enabled';
    const XML_PATH_YOTPO_YOTPO_CATEGORY_BOTTOMLINE_ENABLED = 'yotpo/settings/category_bottomline_enabled';
    const XML_PATH_YOTPO_BOTTOMLINE_ENABLED = 'yotpo/settings/bottomline_enabled';
    const XML_PATH_YOTPO_BOTTOMLINE_QNA_ENABLED = 'yotpo/settings/qna_enabled';
    const XML_PATH_YOTPO_MDR_ENABLED = 'yotpo/settings/mdr_enabled';
    const XML_PATH_YOTPO_CUSTOM_ORDER_STATUS = 'yotpo/settings/custom_order_status';
    const XML_PATH_DEBUG_MODE_ENABLED = "yotpo/settings/debug_mode_active";

    protected $_yotpo_secured_api_url = 'https://api.yotpo.com/';
    protected $_yotpo_unsecured_api_url = 'http://api.yotpo.com/';
    protected $_yotpo_widget_url = '//staticw2.yotpo.com/';
    protected $_allStoreIds = null;

    /**
     * @var Product
     */
    protected $_product;

    /**
     * @var array
     */
    protected $_orderStatuses = [];

    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var EncryptorInterface
     */
    protected $_encryptor;

    /**
     * @var Escaper
     */
    protected $_escaper;

    /**
     * @var Registry
     */
    protected $_coreRegistry;

    /**
     * @var CatalogImageHelper
     */
    protected $_catalogImageHelper;

    /**
     * @var AppEmulation
     */
    protected $_appEmulation;

    /**
     * @var LoggerInterface
     */
    protected $_logger;

    /**
     * @method __construct
     * @param  Context               $context
     * @param  StoreManagerInterface $storeManager
     * @param  EncryptorInterface    $encryptor
     * @param  Escaper               $escaper
     * @param  Registry              $coreRegistry
     * @param  CatalogImageHelper    $catalogImageHelper
     * @param  AppEmulation          $appEmulation
     */
    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager,
        EncryptorInterface $encryptor,
        Escaper $escaper,
        Registry $coreRegistry,
        CatalogImageHelper $catalogImageHelper,
        AppEmulation $appEmulation
    ) {
        $this->_context = $context;
        $this->_storeManager = $storeManager;
        $this->_encryptor = $encryptor;
        $this->_escaper = $escaper;
        $this->_coreRegistry = $coreRegistry;
        $this->_catalogImageHelper = $catalogImageHelper;
        $this->_appEmulation = $appEmulation;
        $this->_logger = $context->getLogger();
        parent::__construct($context);

        if (($testEnvApi = rtrim(getenv("TEST_ENV_API"), "/"))) {
            $this->_yotpo_secured_api_url = $testEnvApi . "/";
            $this->_yotpo_unsecured_api_url = $testEnvApi . "/";
        }

        if (($testEnvWidget = rtrim(getenv("TEST_ENV_WIDGET"), "/"))) {
            $this->_yotpo_widget_url = $testEnvWidget . "/";
        }
    }

    ///////////////////////////
    // Constructor Instances //
    ///////////////////////////

    /**
     * @method getStoreManager
     * @return StoreManagerInterface
     */
    public function getStoreManager()
    {
        return $this->_storeManager;
    }

    /**
     * @method getEncryptor
     * @return EncryptorInterface
     */
    public function getEncryptor()
    {
        return $this->_encryptor;
    }

    /**
     * @method getEscaper
     * @return Escaper
     */
    public function getEscaper()
    {
        return $this->_escaper;
    }

    /**
     * @method getCoreRegistry
     * @return Registry
     */
    public function getCoreRegistry()
    {
        return $this->_coreRegistry;
    }

    /**
     * @method getCatalogImageHelper
     * @return CatalogImageHelper
     */
    public function getCatalogImageHelper()
    {
        return $this->_catalogImageHelper;
    }

    /**
     * @method getAppEmulation
     * @return AppEmulation
     */
    public function getAppEmulation()
    {
        return $this->_appEmulation;
    }

    /**
     * @method getLogger
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return $this->_logger;
    }

    ////////////
    // Config //
    ////////////

    /**
     * @return mixed
     */
    public function getConfig($configPath, $scopeId = null, $scope = null, $skipCahce = false)
    {
        $scope = (is_null($scope)) ? ScopeInterface::SCOPE_STORE : $scope;
        $scopeId = (is_null($scopeId)) ? $this->getStoreManager()->getStore()->getId() : $scopeId;
        if ($skipCahce) {
            if ($scope === ScopeInterface::SCOPE_STORE) {
                $scope = ScopeInterface::SCOPE_STORES;
            } elseif ($scope === ScopeInterface::SCOPE_WEBSITE) {
                $scope = ScopeInterface::SCOPE_WEBSITES;
            }
            $collection = $this->_configCollectionFactory->create()
                ->addFieldToFilter('scope', $scope)
                ->addFieldToFilter('scope_id', $scopeId)
                ->addFieldToFilter('path', ['like' => $configPath . '%']);
            if ($collection->count()) {
                return $collection->getFirstItem()->getValue();
            }
        } else {
            return $this->scopeConfig->getValue($configPath, $scope, $scopeId);
        }
    }

    /**
     * @return array
     */
    public function getAllConfig($scopeId = null, $scope = null, $skipCahce = false)
    {
        return $this->getConfig(self::XML_PATH_YOTPO_ALL, $scopeId, $scope, $skipCahce);
    }

    /**
     * @return boolean
     */
    public function isEnabled($scopeId = null, $scope = null, $skipCahce = false)
    {
        return ($this->getConfig(self::XML_PATH_YOTPO_ENABLED, $scopeId, $scope, $skipCahce)) ? true : false;
    }

    /**
     * @return boolean
     */
    public function isDebugMode($scope = null, $scopeId = null, $skipCahce = false)
    {
        return ($this->getConfig(self::XML_PATH_DEBUG_MODE_ENABLED, $scope, $scopeId, $skipCahce)) ? true : false;
    }

    /**
     * @return string
     */
    public function getAppKey($scopeId = null, $scope = null, $skipCahce = false)
    {
        return $this->getConfig(self::XML_PATH_YOTPO_APP_KEY, $scopeId, $scope, $skipCahce);
    }

    /**
     * @return string
     */
    public function getSecret($scopeId = null, $scope = null, $skipCahce = false)
    {
        return (($secret = $this->getConfig(self::XML_PATH_YOTPO_SECRET, $scopeId, $scope, $skipCahce))) ? $this->_encryptor->decrypt($secret) : null;
    }

    /**
     * @return boolean
     */
    public function isWidgetEnabled($scopeId = null, $scope = null, $skipCahce = false)
    {
        return ($this->getConfig(self::XML_PATH_YOTPO_WIDGET_ENABLED, $scopeId, $scope, $skipCahce)) ? true : false;
    }

    /**
     * @return boolean
     */
    public function isCategoryBottomlineEnabled($scopeId = null, $scope = null, $skipCahce = false)
    {
        return ($this->getConfig(self::XML_PATH_YOTPO_YOTPO_CATEGORY_BOTTOMLINE_ENABLED, $scopeId, $scope, $skipCahce)) ? true : false;
    }

    /**
     * @return boolean
     */
    public function isBottomlineEnabled($scopeId = null, $scope = null, $skipCahce = false)
    {
        return ($this->getConfig(self::XML_PATH_YOTPO_BOTTOMLINE_ENABLED, $scopeId, $scope, $skipCahce)) ? true : false;
    }

    /**
     * @return boolean
     */
    public function isBottomlineQnaEnabled($scopeId = null, $scope = null, $skipCahce = false)
    {
        return ($this->getConfig(self::XML_PATH_YOTPO_BOTTOMLINE_QNA_ENABLED, $scopeId, $scope, $skipCahce)) ? true : false;
    }

    /**
     * @return boolean
     */
    public function isMdrEnabled($scopeId = null, $scope = null, $skipCahce = false)
    {
        return ($this->getConfig(self::XML_PATH_YOTPO_MDR_ENABLED, $scopeId, $scope, $skipCahce)) ? true : false;
    }

    /**
     * @return array
     */
    public function getCustomOrderStatus($scopeId = null, $scope = null, $skipCahce = false)
    {
        if (!$this->_orderStatuses) {
            $this->_orderStatuses = $this->getConfig(self::XML_PATH_YOTPO_CUSTOM_ORDER_STATUS, $scopeId, $scope, $skipCahce);
            if (!$this->_orderStatuses) {
                $this->_orderStatuses = [Order::STATE_COMPLETE];
            } else {
                $this->_orderStatuses = array_map('strtolower', explode(',', $this->_orderStatuses));
            }
        }
        return $this->_orderStatuses;
    }

    /**
     * @return boolean
     */
    public function isAppKeyAndSecretSet($scopeId = null, $scope = null, $skipCahce = false)
    {
        return ($this->getAppKey($scopeId, $scope, $skipCahce) && $this->getSecret($scopeId, $scope, $skipCahce)) ? true : false;
    }

    /**
     * @return string
     */
    public function getTimeFrame()
    {
        return date('Y-m-d', strtotime('-90 days'));
    }

    /**
     * @method getYotpoNoSchemaApiUrl
     * @param  string $path
     * @return string
     */
    public function getYotpoNoSchemaApiUrl($path = "")
    {
        return preg_replace('#^https?:#', '', $this->_yotpo_secured_api_url) . $path;
    }

    /**
     * @method getYotpoSecuredApiUrl
     * @param  string $path
     * @return string
     */
    public function getYotpoSecuredApiUrl($path = "")
    {
        return $this->_yotpo_secured_api_url . $path;
    }

    /**
     * @method getYotpoUnsecuredApiUrl
     * @param  string $path
     * @return string
     */
    public function getYotpoUnsecuredApiUrl($path = "")
    {
        return $this->_yotpo_unsecured_api_url . $path;
    }

    /**
     * @method getYotpoWidgetUrl
     * @return string
     */
    public function getYotpoWidgetUrl()
    {
        return $this->_yotpo_widget_url . $this->getAppKey() . '/widget.js';
    }

    ///////////////////////////////
    // App Environment Emulation //
    ///////////////////////////////

    /**
     * Start environment emulation of the specified store
     *
     * Function returns information about initial store environment and emulates environment of another store
     *
     * @param  integer $storeId
     * @param  string  $area
     * @param  bool    $force   A true value will ensure that environment is always emulated, regardless of current store
     * @return \Yotpo\Yotpo\Helper\Data
     */
    public function startEnvironmentEmulation($storeId, $area = Area::AREA_FRONTEND, $force = false)
    {
        $this->getAppEmulation()->startEnvironmentEmulation($storeId, $area, $force);
        return $this;
    }

    /**
     * Stop environment emulation
     *
     * Function restores initial store environment
     *
     * @return \Yotpo\Yotpo\Helper\Data
     */
    public function stopEnvironmentEmulation()
    {
        $this->getAppEmulation()->stopEnvironmentEmulation();
        return $this;
    }

    public function emulateFrontendArea($storeId, $force = false)
    {
        $this->startEnvironmentEmulation($storeId, Area::AREA_FRONTEND, $force);
        return $this;
    }

    public function emulateAdminArea($storeId, $force = false)
    {
        $this->startEnvironmentEmulation($storeId, Area::AREA_ADMINHTML, $force);
        return $this;
    }

    ///////////////
    // Renderers //
    ///////////////

    public function showWidget($thisObj, $product = null, $print=true)
    {
        return $this->renderYotpoProductBlock($thisObj, 'widget_div', $product, $print);
    }

    public function showBottomline($thisObj, $product = null, $print=true)
    {
        return $this->renderYotpoProductBlock($thisObj, 'bottomline', $product, $print);
    }

    public function showQABottomline($thisObj, $product = null, $print=true)
    {
        return $this->renderYotpoProductBlock($thisObj, 'yotpo-qa-bottomline', $product, $print);
    }

    public function showQuestions($thisObj, $product = null, $print=true)
    {
        return $this->renderYotpoProductBlock($thisObj, 'yotpo-questions', $product, $print);
    }

    protected function renderYotpoProductBlock($thisObj, $blockName, $product = null, $print=true)
    {
        $block = $thisObj->getLayout()->getBlock($blockName);
        if ($block == null) {
            $this->_logger->addDebug("can't find yotpo block");
            return;
        }
        $block->setAttribute('fromHelper', true);

        if ($product != null) {
            $block->setAttribute('product', $product);
        }

        if ($print == true) {
            $block->setAttribute('fromHelper', false);
        } else {
            $ret = $block->toHtml();
            $block->setAttribute('fromHelper', false);
            return $ret;
        }
    }

    ////////////
    // Utils //
    ///////////

    /**
     * @method log
     * @param  mixed  $message
     * @param  string $type
     * @param  array  $data
     * @return $this
     */
    public function log($message, $type = "info", $data = [])
    {
        if ($this->isDebugMode()) { //Log to system.log
            switch ($type) {
            case 'error':
                $this->_logger->error(print_r($message, true), $data);
                break;
            case 'debug':
                $this->_logger->debug(print_r($message, true), $data);
                break;
            default:
                $this->_logger->info(print_r($message, true), $data);
                break;
            }
        }
        return $this;
    }

    /**
     * @method escapeHtml
     * @param  string $str
     * @return string
     */
    public function escapeHtml($str)
    {
        return $this->_escaper->escapeHtml($str);
    }

    /**
     * @method getMediaUrl
     * @param  string $mediaPath
     * @param  string $filePath
     * @return string
     */
    public function getMediaUrl($mediaPath, $filePath)
    {
        return $this->_storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA) . trim($mediaPath, "/") . "/" . ltrim($filePath, "/");
    }

    /**
     * @method getProductMainImageUrl
     * @param  Product $product
     * @return string
     */
    public function getProductMainImageUrl(Product $product)
    {
        if (($filePath = $product->getData("image"))) {
            return (string) $this->getMediaUrl("catalog/product", $filePath);
        }
        return "";
    }

    /**
     * @method getProductImageUrl
     * @param  Product $product
     * @param  string  $imageId
     * @return string
     */
    public function getProductImageUrl(Product $product, $imageId = 'product_page_image_large')
    {
        return $this->_catalogImageHelper->init($product, $imageId)->getUrl();
    }

    public function getCurrentProduct()
    {
        if (is_null($this->_product)) {
            $this->_product = $this->_coreRegistry->registry('current_product');
            if (!$this->_product) {
                $this->_product = false;
            }
        }
        return $this->_product;
    }

    /**
     * @method getAllStoreIds
     * @param  boolean $withDefault
     * @return array
     */
    public function getAllStoreIds($withDefault = false)
    {
        if (is_null($this->_allStoreIds)) {
            $this->_allStoreIds = [];
            foreach ($this->_storeManager->getStores($withDefault) as $store) {
                $this->_allStoreIds[] = $store->getId();
            }
        }
        return $this->_allStoreIds;
    }
}
