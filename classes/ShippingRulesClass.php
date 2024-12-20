<?php
/**
 * @author    Adilis <support@adilis.fr>
 * @copyright 2024 SAS Adilis
 * @license   http://www.adilis.fr
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class ShippingRulesClass extends ObjectModel
{
    const RULE_TYPE_ADDITIONAL = 1;
    const RULE_TYPE_FREE = 2;
    const RULE_TYPE_ADDITIONAL_PERCENT = 3;
    const RULE_TYPE_DISABLE = 4;
    const RULE_TYPE_SET_AMOUNT = 5;

    public $id_carrier;
    public $id_zone;
    public $id_country;
    public $id_group;
    public $id_customer;
    public $postcode_list;
    public $minimum_amount = 0;
    public $minimum_amount_currency;
    public $minimum_amount_tax = 0;
    public $maximum_amount = 9999;
    public $maximum_amount_currency;
    public $maximum_amount_tax = 0;
    public $minimum_weight = 0;
    public $maximum_weight = 9999;
    public $impact_amount = 0;
    public $impact_percent = 0;
    public $new_amount = 0;
    public $rule_type = self::RULE_TYPE_FREE;
    public $from;
    public $to;
    public $active = 1;

    public static $definition = [
        'table' => 'shipping_rule',
        'primary' => 'id_shipping_rule',
        'fields' => [
            'id_zone' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId'],
            'id_carrier' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId'],
            'id_country' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId'],
            'id_group' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId'],
            'id_customer' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId'],
            'postcode_list' => ['type' => self::TYPE_STRING],
            'minimum_amount' => ['type' => self::TYPE_FLOAT, 'validate' => 'isPrice', 'required' => true],
            'minimum_amount_tax' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
            'minimum_amount_currency' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId'],
            'maximum_amount' => ['type' => self::TYPE_FLOAT, 'validate' => 'isPrice', 'required' => true],
            'maximum_amount_tax' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
            'maximum_amount_currency' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId'],
            'value' => ['type' => self::TYPE_FLOAT, 'required' => true],
            'minimum_weight' => ['type' => self::TYPE_FLOAT, 'validate' => 'isFloat', 'required' => true],
            'maximum_weight' => ['type' => self::TYPE_FLOAT, 'validate' => 'isFloat', 'required' => true],
            'rule_type' => ['type' => self::TYPE_STRING, 'size' => 10],
            'from' => ['type' => self::TYPE_DATE, 'validate' => 'isDateFormat', 'required' => true],
            'to' => ['type' => self::TYPE_DATE, 'validate' => 'isDateFormat', 'required' => true],
            'active' => ['type' => self::TYPE_INT, 'shop' => true, 'validate' => 'isUnsignedInt'],
        ],
    ];

    public function __construct($id_tab = null, $id_lang = null, $id_shop = null)
    {
        Shop::addTableAssociation('shipping_rule', ['type' => 'shop']);
        parent::__construct($id_tab, $id_lang, $id_shop);
    }
}
