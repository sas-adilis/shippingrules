<?php
/**
 * @author    Adilis <support@adilis.fr>
 * @copyright 2024 SAS Adilis
 * @license   http://www.adilis.fr
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_1_3($module)
{
    $queries = [
        'ALTER TABLE `' . _DB_PREFIX_ . 'shipping_rule` ADD `impact_percent` decimal(20,6) default 0 NOT NULL;',
        'ALTER TABLE `' . _DB_PREFIX_ . 'shipping_rule` ADD `id_group` int(11) unsigned NOT NULL DEFAULT "0";',
    ];

    foreach ($queries as $query) {
        if (!Db::getInstance()->execute($query)) {
            return false;
        }
    }

    return true;
}
