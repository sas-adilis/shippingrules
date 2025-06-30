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
    const RULE_TYPE_REDUCTION_PERCENT = 6;
    const RULE_TYPE_REDUCTION = 7;

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
    /** @var int */
    public $position;

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
            'position' => ['type' => self::TYPE_INT, 'validate' => 'isInt'],
        ],
    ];

    public function __construct($id_tab = null, $id_lang = null, $id_shop = null)
    {
        Shop::addTableAssociation('shipping_rule', ['type' => 'shop']);
        parent::__construct($id_tab, $id_lang, $id_shop);
    }

    public function add($autoDate = true, $nullValues = false)
    {
        if ($this->position <= 0) {
            $this->position = self::getHigherPosition() + 1;
        }

        return parent::add($autoDate, true);
    }

    public function delete()
    {
        $return = parent::delete();
        $this->cleanPositions();

        return $return;
    }

    public static function getHigherPosition()
    {
        $sql = 'SELECT MAX(`position`) FROM `' . _DB_PREFIX_ . 'shipping_rule`';
        $position = Db::getInstance()->getValue($sql);

        return (is_numeric($position)) ? $position : -1;
    }

    public static function cleanPositions(): bool
    {
        Db::getInstance()->execute('SET @i = -1', false);
        $sql = 'UPDATE `' . _DB_PREFIX_ . 'shipping_rule` SET `position` = @i:=@i+1 ORDER BY `position` ASC';

        return (bool) Db::getInstance()->execute($sql);
    }

    /**
     * @throws PrestaShopDatabaseException
     */
    public function updatePosition($way, $position, $id_shipping_rule = null): bool
    {
        if (!$res = Db::getInstance()->executeS(
            '
			SELECT `position`, `id_shipping_rule`
			FROM `' . _DB_PREFIX_ . 'shipping_rule`
			WHERE `id_shipping_rule` = ' . (int) ($id_shipping_rule ?: $this->id) . '
			ORDER BY `position` ASC'
        )) {
            return false;
        }

        foreach ($res as $shipping_rule) {
            if ((int) $shipping_rule['id_shipping_rule'] == (int) $this->id) {
                $moved_shipping_rule = $shipping_rule;
            }
        }

        if (!isset($moved_shipping_rule) || !isset($position)) {
            return false;
        }

        return Db::getInstance()->execute('
			UPDATE `' . _DB_PREFIX_ . 'shipping_rule`
			SET `position`= `position` ' . ($way ? '- 1' : '+ 1') . '
			WHERE `position`
			' . ($way
                    ? '> ' . (int) $moved_shipping_rule['position'] . ' AND `position` <= ' . (int) $position
                    : '< ' . (int) $moved_shipping_rule['position'] . ' AND `position` >= ' . (int) $position)
        ) && Db::getInstance()->execute('
			UPDATE `' . _DB_PREFIX_ . 'shipping_rule`
			SET `position` = ' . (int) $position . '
			WHERE `id_shipping_rule`=' . (int) $moved_shipping_rule['id_shipping_rule']);
    }
}
