<?php
/**
 * @author    Adilis <support@adilis.fr>
 * @copyright 2024 SAS Adilis
 * @license   http://www.adilis.fr
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

$sql = [];

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'shipping_rule` (
    `id_shipping_rule` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_carrier` int(11) unsigned default 0  NOT NULL,
    `rule_type` tinyint(1) default 1 NOT NULL,
    `id_country` int(11) unsigned default 0 NOT NULL,
    `id_zone` int(11) unsigned default 0 NOT NULL,
    `id_group` int(11) unsigned default 0 NOT NULL,
    `id_customer` int(11) unsigned default 0 NOT NULL,
    `postcode_list` text,
    `minimum_amount` decimal(20,6) default 0 NOT NULL,
    `minimum_amount_tax` tinyint(1) default 0 NOT NULL,
    `minimum_amount_currency` int(11) unsigned NOT NULL,
    `maximum_amount` decimal(20,6) default 9999 NOT NULL,
    `maximum_amount_tax` tinyint(1) default 0 NOT NULL,
    `maximum_amount_currency` tinyint(1) default 0 NOT NULL,
    `minimum_weight` decimal(20,6) default 0 NOT NULL,
    `maximum_weight` decimal(20,6) default 9999 NOT NULL,
    `value` decimal(20,6) default 0 NOT NULL,
    `from` datetime NOT NULL,
    `to` datetime NOT NULL,
    `active` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`id_shipping_rule`)
) ENGINE = ' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8';

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'shipping_rule_shop` (
    `id_shipping_rule` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_shop` int(10) unsigned NOT NULL,
    `active` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`id_shipping_rule`,`id_shop`)
) ENGINE = ' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8';

foreach ($sql as $query) {
    if (!Db::getInstance()->execute($query)) {
        return false;
    }
}
