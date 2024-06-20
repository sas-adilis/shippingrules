<?php

function upgrade_module_1_1_0($module)
{
    $queries = [
        'ALTER TABLE `' . _DB_PREFIX_ . 'shipping_rule` CHANGE `amount` `minimum_amount` decimal(20,6) NOT NULL;',
        'ALTER TABLE `' . _DB_PREFIX_ . 'shipping_rule` ADD `minimum_amount_tax` tinyint(1) default 0 NOT NULL;',
        'ALTER TABLE `' . _DB_PREFIX_ . 'shipping_rule` ADD `maximum_amount` decimal(20,6) NOT NULL;',
        'ALTER TABLE `' . _DB_PREFIX_ . 'shipping_rule` ADD `maximum_amount_tax` tinyint(1) default 0 NOT NULL;',
        'ALTER TABLE `' . _DB_PREFIX_ . 'shipping_rule` ADD `minimum_weight` decimal(20,6) NOT NULL;',
        'ALTER TABLE `' . _DB_PREFIX_ . 'shipping_rule` ADD `maximum_weight` decimal(20,6) NOT NULL;',
        'ALTER TABLE `' . _DB_PREFIX_ . 'shipping_rule` ADD `impact_amount` decimal(20,6) NOT NULL;',
        'UPDATE `' . _DB_PREFIX_ . 'shipping_rule` SET `maximum_amount` = 9999;',
        'UPDATE `' . _DB_PREFIX_ . 'shipping_rule` SET `maximum_weight` = 9999;',
    ];

    foreach ($queries as $query) {
        if (!Db::getInstance()->execute($query)) {
            return false;
        }
    }

    return true;
}
