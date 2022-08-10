<?php

namespace Hitkaipe\CurrencySymbolPosition\Model\System;

use Magento\Config\Model\Config\Factory;
use Magento\Directory\Model\Currency;
use Magento\Framework\App\Cache\Type\Block;
use Magento\Framework\App\Cache\Type\Config;
use Magento\Framework\App\Cache\Type\Layout;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Locale\Bundle\CurrencyBundle;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\PageCache\Model\Cache\Type;
use Magento\Store\Model\Group;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\System\Store;
use Magento\Store\Model\Website;

class CurrencySymbolPosition
{
    /**
     * Custom currency symbol position properties
     *
     * @var array
     */
    protected $_positionsData = [];

    /**
     * Store id
     *
     * @var string|null
     */
    protected $_storeId;

    /**
     * Website id
     *
     * @var string|null
     */
    protected $_websiteId;

    /**
     * Cache types which should be invalidated
     *
     * @var array
     */
    protected $_cacheTypes = [
        Config::TYPE_IDENTIFIER,
        Block::TYPE_IDENTIFIER,
        Layout::TYPE_IDENTIFIER,
        Type::TYPE_IDENTIFIER,
    ];

    /**
     * Config path to custom currency symbol value
     */
    const XML_PATH_CUSTOM_CURRENCY_SYMBOL_POSITION = 'currency/options/symbol_position';

    const XML_PATH_ALLOWED_CURRENCIES = Currency::XML_PATH_CURRENCY_ALLOW;

    /*
     * Separator used in config in allowed currencies list
     */
    const ALLOWED_CURRENCIES_CONFIG_SEPARATOR = ',';

    /**
     * Config currency section
     */
    const CONFIG_SECTION = 'currency';

    /**
     * Core event manager proxy
     *
     * @var ManagerInterface
     */
    protected $_eventManager;

    /**
     * @var TypeListInterface
     */
    protected $_cacheTypeList;

    /**
     * @var Factory
     */
    protected $_configFactory;

    /**
     * @var Store
     */
    protected $_systemStore;

    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var ResolverInterface
     */
    protected $localeResolver;

    /**
     * @var ReinitableConfigInterface
     */
    protected $_coreConfig;

    /**
     * Core store config
     *
     * @var ScopeConfigInterface
     */
    protected $_scopeConfig;

    /**
     * @var Json
     */
    private $serializer;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param ReinitableConfigInterface $coreConfig
     * @param Factory $configFactory
     * @param TypeListInterface $cacheTypeList
     * @param StoreManagerInterface $storeManager
     * @param ResolverInterface $localeResolver
     * @param Store $systemStore
     * @param ManagerInterface $eventManager
     * @param Json|null $serializer
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ReinitableConfigInterface $coreConfig,
        Factory $configFactory,
        TypeListInterface $cacheTypeList,
        StoreManagerInterface $storeManager,
        ResolverInterface $localeResolver,
        Store $systemStore,
        ManagerInterface $eventManager,
        Json $serializer = null
    ) {
        $this->_coreConfig = $coreConfig;
        $this->_configFactory = $configFactory;
        $this->_cacheTypeList = $cacheTypeList;
        $this->_storeManager = $storeManager;
        $this->localeResolver = $localeResolver;
        $this->_systemStore = $systemStore;
        $this->_eventManager = $eventManager;
        $this->_scopeConfig = $scopeConfig;
        $this->serializer = $serializer ?: ObjectManager::getInstance()->get(Json::class);
    }

    /**
     * Return currency symbol position properties array based on configuration values
     *
     * @return array
     */
    public function getPositionsData()
    {
        if ($this->_positionsData) {
            return $this->_positionsData;
        }

        $this->_positionsData = [];

        $currentSymbols = $this->unserializeStoreConfig();

        foreach ($this->getAllowedCurrencies() as $code) {
            $currencies = (new CurrencyBundle())->get($this->localeResolver->getLocale())['Currencies'];
            $symbol = $currencies[$code][0] ? '' : $code;
            $name = $currencies[$code][1] ?: $code;
            $this->_positionsData[$code] = ['parentPosition' => $symbol, 'displayName' => $name];

            if (isset($currentSymbols[$code]) && !empty($currentSymbols[$code])) {
                $this->_positionsData[$code]['displayPosition'] = $currentSymbols[$code];
            } else {
                $this->_positionsData[$code]['displayPosition'] = $this->_positionsData[$code]['parentPosition'];
            }
            $this->_positionsData[$code]['inherited'] =
                ($this->_positionsData[$code]['parentPosition'] == $this->_positionsData[$code]['displayPosition']);
        }

        return $this->_positionsData;
    }

    /**
     * Save currency symbol postion to config
     *
     * @param  $symbols array
     * @return $this
     */
    public function setPositionData($symbols = [])
    {
        foreach ($this->getPositionsData() as $code => $values) {
            if (isset($symbols[$code]) && ($symbols[$code] == $values['parentPosition'] || empty($symbols[$code]))) {
                unset($symbols[$code]);
            }
        }
        $value = [];
        if ($symbols) {
            $value['options']['fields']['symbol_position']['value'] = $this->serializer->serialize($symbols);
        } else {
            $value['options']['fields']['symbol_position']['inherit'] = 1;
        }

        $this->_configFactory->create()
            ->setSection(self::CONFIG_SECTION)
            ->setWebsite(null)
            ->setStore(null)
            ->setGroups($value)
            ->save();

        $this->_eventManager->dispatch(
            'admin_system_config_changed_section_currency_symbol_position_before_reinit',
            ['website' => $this->_websiteId, 'store' => $this->_storeId]
        );

        // reinit configuration
        $this->_coreConfig->reinit();

        $this->clearCache();
        //Reset position cache since new data is added
        $this->_positionsData = [];

        $this->_eventManager->dispatch(
            'admin_system_config_changed_section_currency_symbol_position',
            ['website' => $this->_websiteId, 'store' => $this->_storeId]
        );

        return $this;
    }

    /**
     * Return custom currency symbol position by currency code
     *
     * @param string $code
     * @return string|false
     */
    public function getCurrencySymbolPosition($code)
    {
        $customSymbols = $this->unserializeStoreConfig();
        if (array_key_exists($code, $customSymbols)) {
            return $customSymbols[$code];
        }

        return false;
    }

    /**
     * Clear translate cache
     *
     * @return $this
     */
    protected function clearCache()
    {
        // clear cache for frontend
        foreach ($this->_cacheTypes as $cacheType) {
            $this->_cacheTypeList->invalidate($cacheType);
        }
        return $this;
    }

    /**
     * Unserialize data from Store Config.
     *
     * @param int $storeId
     * @return array
     */
    public function unserializeStoreConfig($storeId = null)
    {
        $configPath = self::XML_PATH_CUSTOM_CURRENCY_SYMBOL_POSITION;
        $result = [];
        $configData = (string)$this->_scopeConfig->getValue(
            $configPath,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        if ($configData) {
            $result = $this->serializer->unserialize($configData);
        }

        return is_array($result) ? $result : [];
    }

    /**
     * Return allowed currencies
     *
     * @return array
     */
    protected function getAllowedCurrencies()
    {
        $allowedCurrencies = explode(
            self::ALLOWED_CURRENCIES_CONFIG_SEPARATOR,
            $this->_scopeConfig->getValue(
                self::XML_PATH_ALLOWED_CURRENCIES,
                ScopeInterface::SCOPE_STORE,
                null
            )
        );

        $storeModel = $this->_systemStore;
        /** @var Website $website */
        foreach ($storeModel->getWebsiteCollection() as $website) {
            $websiteShow = false;
            /** @var Group $group */
            foreach ($storeModel->getGroupCollection() as $group) {
                if ($group->getWebsiteId() != $website->getId()) {
                    continue;
                }
                /** @var \Magento\Store\Model\Store $store */
                foreach ($storeModel->getStoreCollection() as $store) {
                    if ($store->getGroupId() != $group->getId()) {
                        continue;
                    }
                    if (!$websiteShow) {
                        $websiteShow = true;
                        $websiteSymbols = $website->getConfig(self::XML_PATH_ALLOWED_CURRENCIES);
                        $allowedCurrencies = array_merge(
                            $allowedCurrencies,
                            explode(self::ALLOWED_CURRENCIES_CONFIG_SEPARATOR, $websiteSymbols)
                        );
                    }
                    $storeSymbols = $this->_scopeConfig->getValue(
                        self::XML_PATH_ALLOWED_CURRENCIES,
                        ScopeInterface::SCOPE_STORE,
                        $store
                    );
                    $allowedCurrencies = array_merge(
                        $allowedCurrencies,
                        explode(self::ALLOWED_CURRENCIES_CONFIG_SEPARATOR, $storeSymbols)
                    );
                }
            }
        }
        return array_unique($allowedCurrencies);
    }
}
