<?php

function upgrade_module_1_1_1($module)
{

    $queries = [
        'ALTER TABLE `' . _DB_PREFIX_ . 'shipping_rule` ADD `minimum_amount_currency` tinyint(1) default 0 NOT NULL;',
        'ALTER TABLE `' . _DB_PREFIX_ . 'shipping_rule` ADD `maximum_amount_currency` tinyint(1) default 0 NOT NULL;',
        'UPDATE `' . _DB_PREFIX_ . 'shipping_rule` SET `minimum_amount_currency` = '.Configuration::get('PS_CURRENCY_DEFAULT').';',
        'UPDATE `' . _DB_PREFIX_ . 'shipping_rule` SET `maximum_amount_currency` = '.Configuration::get('PS_CURRENCY_DEFAULT').';',
    ];

    foreach ($queries as $query) {
        if (!Db::getInstance()->execute($query)) {
            return false;
        }
    }

    return true;
}
?>