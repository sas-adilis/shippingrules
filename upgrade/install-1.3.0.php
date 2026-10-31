<?php
/**
 * @author    Adilis <support@adilis.fr>
 * @copyright 2024 SAS Adilis
 * @license   http://www.adilis.fr
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_3_0(): bool
{
    $columns = [
        'minimum_quantity' => 'int(10) unsigned NOT NULL DEFAULT 0',
        'maximum_quantity' => 'int(10) unsigned NOT NULL DEFAULT 0',
        'categories' => 'text',
        'manufacturers' => 'text',
        'cart_rules' => 'text',
        'days' => 'varchar(20) DEFAULT NULL',
        'hour_from' => 'varchar(5) DEFAULT NULL',
        'hour_to' => 'varchar(5) DEFAULT NULL',
    ];

    foreach ($columns as $column => $definition) {
        $exists = Db::getInstance()->executeS('SHOW COLUMNS FROM `' . _DB_PREFIX_ . 'shipping_rule` LIKE "' . pSQL($column) . '"');
        if (!$exists && !Db::getInstance()->execute('ALTER TABLE `' . _DB_PREFIX_ . 'shipping_rule` ADD `' . $column . '` ' . $definition)) {
            return false;
        }
    }

    return true;
}
