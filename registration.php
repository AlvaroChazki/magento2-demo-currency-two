<?php

/**
 * Module register
 *
 * @category Class
 * @package  Hitkaipe
 * @author   Hitkaipe
 */

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'Hitkaipe_CurrencySymbolPosition',
    __DIR__
);
