<?php
/**
 * @author    Adilis <support@adilis.fr>
 * @copyright 2024 SAS Adilis
 * @license   http://www.adilis.fr
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_2_4()
{
    $queries = [
        'ALTER TABLE `' . _DB_PREFIX_ . 'shipping_rule` MODIFY `minimum_amount_currency` int(11) unsigned NOT NULL DEFAULT 0',
        'ALTER TABLE `' . _DB_PREFIX_ . 'shipping_rule` MODIFY `maximum_amount_currency` int(11) unsigned NOT NULL DEFAULT 0',
        'UPDATE `' . _DB_PREFIX_ . 'shipping_rule` SET `maximum_amount_currency` = `minimum_amount_currency`',
        'ALTER TABLE `' . _DB_PREFIX_ . 'shipping_rule_shop` MODIFY `id_shipping_rule` int(11) unsigned NOT NULL',
        'INSERT IGNORE INTO `' . _DB_PREFIX_ . 'shipping_rule_shop` (`id_shipping_rule`, `id_shop`, `active`)
            SELECT sr.`id_shipping_rule`, s.`id_shop`, sr.`active`
            FROM `' . _DB_PREFIX_ . 'shipping_rule` sr
            CROSS JOIN `' . _DB_PREFIX_ . 'shop` s
            WHERE NOT EXISTS (
                SELECT 1 FROM `' . _DB_PREFIX_ . 'shipping_rule_shop` x WHERE x.`id_shipping_rule` = sr.`id_shipping_rule`
            )',
    ];

    foreach ($queries as $query) {
        if (!Db::getInstance()->execute($query)) {
            return false;
        }
    }

    return true;
}
