<?php

namespace Hitkaipe\CurrencySymbolPosition\Observer;

use Magento\Framework\Currency;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class DisplayOptions implements ObserverInterface
{
    /**
     * @var \Hitkaipe\CurrencySymbolPosition\Model\System\CurrencySymbolPositionFactory
     */
    protected $_currencySymbolPosition;

    /**
     * @param \Hitkaipe\CurrencySymbolPosition\Model\System\CurrencySymbolPositionFactory $symbolPositionSystemFactory
     */
    public function __construct(
        \Hitkaipe\CurrencySymbolPosition\Model\System\CurrencySymbolPositionFactory $symbolPositionSystemFactory
    )
    {
        $this->_currencySymbolPosition = $symbolPositionSystemFactory;
    }

    /**
     * hook to event currency_display_options_forming
     * change currency symbol position
     *
     * @param Observer $observer
     * @return $this
     */
    public function execute(Observer $observer)
    {
        // Get the position value from configuration
        $positionValue = $this->_currencySymbolPosition->create()->unserializeStoreConfig();
        $baseCode = $observer->getEvent()->getBaseCode();
        $position = null;
        foreach ($positionValue as $key => $value) {
            if ($key == $baseCode) {
                $position = (int)$value;
            }
        }

        if (in_array($position, [Currency::RIGHT, Currency::LEFT])) {
            $currencyOptions = $observer->getEvent()->getCurrencyOptions();
            // change currency symbol position to $position
            $currencyOptions->setData('position', (int)$position);
        }

        return $this;
    }
}
