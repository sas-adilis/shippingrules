<?php
/**
 * @author    Adilis <support@adilis.fr>
 * @copyright 2024 SAS Adilis
 * @license   http://www.adilis.fr
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_2_9(): bool
{
    $index_exists = Db::getInstance()->executeS('SHOW INDEX FROM `' . _DB_PREFIX_ . 'shipping_rule` WHERE `Key_name` = "idx_lookup"');
    if ($index_exists) {
        return true;
    }

    return (bool) Db::getInstance()->execute(
        'ALTER TABLE `' . _DB_PREFIX_ . 'shipping_rule` ADD INDEX `idx_lookup` (`id_zone`, `id_country`, `id_group`, `id_customer`)'
    );
}
