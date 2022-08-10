<?php

namespace Hitkaipe\CurrencySymbolPosition\Block\Adminhtml\System;

use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Form;
use Magento\Framework\Phrase;
use Magento\Framework\View\Element\AbstractBlock;

class CurrencySymbolPosition extends Form
{
    /**
     * @var \Hitkaipe\CurrencySymbolPosition\Model\System\CurrencySymbolPositionFactory
     */
    protected $_symbolPositionSystemFactory;

    /**
     * @param Context $context
//     * @param \Hitkaipe\CurrencySymbolPosition\Model\System\CurrencySymbolPosition $symbolPositionSystemFactory
     * @param \Hitkaipe\CurrencySymbolPosition\Model\System\CurrencySymbolPositionFactory $symbolPositionSystemFactory
     * @param array $data
     */
    public function __construct(
        Context                                     $context,
        \Hitkaipe\CurrencySymbolPosition\Model\System\CurrencySymbolPositionFactory $symbolPositionSystemFactory,
        array                                                                       $data = []
    ) {
        $this->_symbolPositionSystemFactory = $symbolPositionSystemFactory;
        parent::__construct($context, $data);
    }

    /**
     * Constructor. Initialization required variables for class instance.
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->_controller = 'adminhtml_system_currencysymbolpostion';
        parent::_construct();
    }

    /**
     * Custom currency symbol position properties
     *
     * @var array
     */
    protected array $_postionData = [];

    /**
     * Prepares layout
     *
     * @return AbstractBlock
     */
    protected function _prepareLayout(): AbstractBlock
    {
        $this->getToolbar()->addChild(
            'save_button',
            \Magento\Backend\Block\Widget\Button::class,
            [
                'label' => __('Save Currency Symbols Position'),
                'class' => 'save primary save-currency-symbols-postion',
                'data_attribute' => [
                    'mage-init' => ['button' => ['event' => 'save', 'target' => '#currency-symbols-position-form']],
                ]
            ]
        );

        return parent::_prepareLayout();
    }

    /**
     * Returns page header
     *
     * @return Phrase
     * @codeCoverageIgnore
     */
    public function getHeader(): Phrase
    {
        return __('Currency Symbols Position');
    }

    /**
     * Returns URL for save action
     *
     * @return string
     * @codeCoverageIgnore
     */
    public function getFormActionUrl(): string
    {
        return $this->getUrl('adminhtml/*/save');
    }

    /**
     * Returns website id
     *
     * @return int
     * @codeCoverageIgnore
     */
    public function getWebsiteId(): int
    {
        return $this->getRequest()->getParam('website');
    }

    /**
     * Returns store id
     *
     * @return int
     * @codeCoverageIgnore
     */
    public function getStoreId(): int
    {
        return $this->getRequest()->getParam('store');
    }

    /**
     * Returns Custom currency symbol properties
     *
     * @return array
     */
    public function getCurrencySymbolsPositionData(): array
    {
        if (!$this->_postionData) {
            $this->_postionData = $this->_symbolPositionSystemFactory->create()->getPositionsData();
        }
        return $this->_postionData;
    }

    /**
     * Returns inheritance text
     *
     * @return Phrase
     * @codeCoverageIgnore
     */
    public function getInheritText(): Phrase
    {
        return __('Use Standard');
    }
}
