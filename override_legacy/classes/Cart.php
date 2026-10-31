<?php
/**
 * @author    Adilis <support@adilis.fr>
 * @copyright 2024 SAS Adilis
 * @license   http://www.adilis.fr
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class Cart extends CartCore
{
    public function getPackageShippingCost(
        $id_carrier = null,
        $use_tax = true,
        ?Country $default_country = null,
        $product_list = null,
        $id_zone = null,
        bool $keepOrderPrices = false
    ) {
        $shipping_cost = parent::getPackageShippingCost(
            $id_carrier,
            $use_tax,
            $default_country,
            $product_list,
            $id_zone,
            $keepOrderPrices
        );

        Hook::exec('actionGetPackageShippingCost', [
            'cart' => $this,
            'shipping_cost' => &$shipping_cost,
            'id_carrier' => $id_carrier,
            'use_tax' => $use_tax,
            'default_country' => $default_country,
            'product_list' => $product_list,
            'id_zone' => $id_zone,
            'keepOrderPrices' => $keepOrderPrices,
        ]);

        return $shipping_cost;
    }
}
