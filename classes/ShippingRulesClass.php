<?php

class ShippingRulesClass extends ObjectModel
{
    const RULE_TYPE_ADDITIONAL = 1;
    const RULE_TYPE_FREE = 2;

    public $id_carrier;
    public $id_zone;
    public $id_country;
    public $minimum_amount = 0;
    public $minimum_amount_tax = 0;
    public $maximum_amount = 9999;
    public $maximum_amount_tax = 0;
    public $minimum_weight = 0;
    public $maximum_weight = 9999;
    public $impact_amount = 0;
    public $rule_type = self::RULE_TYPE_FREE;
    public $from;
    public $to;
    public $active = 1;

    public static $definition = array(
        'table' => 'shipping_rule',
        'primary' => 'id_shipping_rule',
        'fields' => array(
            'id_zone' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId'),
            'id_carrier' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId'),
            'id_country' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId'),
            'minimum_amount' => array('type' => self::TYPE_FLOAT, 'validate' => 'isPrice', 'required' => true),
            'minimum_amount_tax' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
            'minimum_amount_currency' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId'],
            'maximum_amount' => array('type' => self::TYPE_FLOAT, 'validate' => 'isPrice', 'required' => true),
            'maximum_amount_tax' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
            'maximum_amount_currency' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId'],
            'impact_amount' => array('type' => self::TYPE_FLOAT, 'validate' => 'isPrice', 'required' => true),
            'minimum_weight' => array('type' => self::TYPE_FLOAT, 'validate' => 'isFloat', 'required' => true),
            'maximum_weight' => array('type' => self::TYPE_FLOAT, 'validate' => 'isFloat', 'required' => true),
            'rule_type' => array('type' => self::TYPE_STRING, 'size' => 10),
            'from' => array('type' => self::TYPE_DATE, 'validate' => 'isDateFormat', 'required' => true),
            'to' => array('type' => self::TYPE_DATE, 'validate' => 'isDateFormat', 'required' => true),
            'active' => array('type' => self::TYPE_INT, 'shop' => true, 'validate' => 'isUnsignedInt'),
        )
    );

    public function __construct($id_tab = null, $id_lang = null, $id_shop = null)
    {
        Shop::addTableAssociation('shipping_rule', array('type' => 'shop'));
        parent::__construct($id_tab, $id_lang, $id_shop);
    }
}