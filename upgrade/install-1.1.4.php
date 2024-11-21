<?php
/**
 * @author    Adilis <support@adilis.fr>
 * @copyright 2024 SAS Adilis
 * @license   http://www.adilis.fr
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_1_4($module)
{
    $queries = [
        'ALTER TABLE `' . _DB_PREFIX_ . 'shipping_rule` ADD `value` decimal(20,6) default 0 NOT NULL;',
        'ALTER TABLE `' . _DB_PREFIX_ . 'shipping_rule` ADD `id_customer` int(11) unsigned NOT NULL DEFAULT "0";',
        'UPDATE `' . _DB_PREFIX_ . 'shipping_rule` SET `value` = `impact_amount` WHERE rule_type = ' . ShippingRulesClass::RULE_TYPE_ADDITIONAL . ';',
        'ALTER TABLE `' . _DB_PREFIX_ . 'shipping_rule` DROP `impact_amount`;',
        'UPDATE `' . _DB_PREFIX_ . 'shipping_rule` SET `value` = `impact_percent` WHERE rule_type = ' . ShippingRulesClass::RULE_TYPE_ADDITIONAL_PERCENT . ';',
        'ALTER TABLE `' . _DB_PREFIX_ . 'shipping_rule` DROP `impact_percent`;',
        'ALTER TABLE `' . _DB_PREFIX_ . 'shipping_rule` ADD `postcode_list` text;',
    ];

    foreach ($queries as $query) {
        if (!Db::getInstance()->execute($query)) {
            return false;
        }
    }

    return true;
}
