<?php

namespace Hitkaipe\CurrencySymbolPosition\Controller\Adminhtml\System\Currencysymbolposition;

use Magento\Backend\App\Action;

class Index extends Action
{
    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */
    const ADMIN_RESOURCE = 'Hitkaipe_CurrencySymbolPosition::symbols_position';

    /**
     * Show Currency Symbol Position Management dialog
     *
     * @return void
     */
    public function execute(): void
    {
        // set active menu and breadcrumbs
        $this->_view->loadLayout();
        $this->_setActiveMenu(
            'Hitkaipe_CurrencySymbolPosition::symbols_position'
        );

        $this->_view->getPage()->getConfig()->getTitle()->prepend(__('Currency Symbols Position'));
        $this->_view->renderLayout();
    }
}
