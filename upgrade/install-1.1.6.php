<?php
/**
 * @author    Adilis <support@adilis.fr>
 * @copyright 2024 SAS Adilis
 * @license   http://www.adilis.fr
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_1_6()
{
    $field_exists = Db::getInstance()->executeS('SHOW COLUMNS FROM `' . _DB_PREFIX_ . 'shipping_rule` LIKE "position"');
    if (!$field_exists) {
        $queries = [];
        $queries[] = 'ALTER TABLE `' . _DB_PREFIX_ . 'shipping_rule` ADD `position` INT(10) UNSIGNED NOT NULL DEFAULT "0"';

        $rules = Db::getInstance()->executeS('SELECT * FROM `' . _DB_PREFIX_ . 'shipping_rule` ORDER BY id_shipping_rule');
        if ($rules) {
            $i = 0;
            foreach ($rules as $rule) {
                $queries[] = 'UPDATE `' . _DB_PREFIX_ . 'shipping_rule` SET position = ' . $i . ' WHERE id_shipping_rule = ' . (int) $rule['id_shipping_rule'] . ' LIMIT 1;';
                ++$i;
            }
        }

        foreach ($queries as $query) {
            if (!Db::getInstance()->execute($query)) {
                return false;
            }
        }
    }

    return true;
}
