<?php
/**
 * Minimal stand-ins for the PrestaShop classes the module touches, so the
 * logic can be tested without a PrestaShop install. Behaviour is configured
 * through the public statics; reset() restores the defaults.
 */
class PrestaShopException extends Exception
{
}

class PrestaShopDatabaseException extends PrestaShopException
{
}

class ObjectModel
{
    public const TYPE_INT = 1;
    public const TYPE_BOOL = 2;
    public const TYPE_STRING = 3;
    public const TYPE_FLOAT = 4;
    public const TYPE_DATE = 5;

    public $id;

    public function __construct($id = null, $id_lang = null, $id_shop = null)
    {
        $this->id = $id;
    }

    public function add($autoDate = true, $nullValues = false)
    {
        return true;
    }

    public function delete()
    {
        return true;
    }

    public function toggleStatus()
    {
        $this->active = !$this->active;

        return true;
    }
}

class Module
{
    public $name;
    public $author;
    public $need_instance;
    public $bootstrap;
    public $tab;
    public $version;
    public $displayName;
    public $description;
    public $confirmUninstall;
    public $ps_versions_compliancy;
    public $module_key;
    public $context;

    public function __construct()
    {
        $this->context = Context::getContext();
    }

    public function l($string)
    {
        return $string;
    }
}

class ModuleAdminController
{
    public $errors = [];
    public $module;

    public function trans($id, $params = [], $domain = null)
    {
        return $id;
    }

    protected function copyFromPost(&$object, $table)
    {
    }
}

class Translate
{
    public static function getModuleTranslation($module, $string)
    {
        return $string;
    }
}

class Tools
{
    /** @var array<string, mixed> */
    public static $values = [];

    public static function getValue($key, $default = false)
    {
        return array_key_exists($key, self::$values) ? self::$values[$key] : $default;
    }

    public static function getIsset($key)
    {
        return array_key_exists($key, self::$values);
    }

    public static function convertPriceFull($amount, $from, $to)
    {
        return $amount / $from->conversion_rate * $to->conversion_rate;
    }

    public static function ps_round($value, $precision = 0)
    {
        return round($value, $precision);
    }
}

class Configuration
{
    /** @var array<string, mixed> */
    public static $values = [];

    public static function get($key)
    {
        return self::$values[$key] ?? false;
    }
}

class Shop
{
    public const CONTEXT_SHOP = 1;
    public const CONTEXT_ALL = 4;

    public static $featureActive = false;
    public static $context = self::CONTEXT_ALL;
    public static $contextShopId = 1;

    public static function isFeatureActive()
    {
        return self::$featureActive;
    }

    public static function getContext()
    {
        return self::$context;
    }

    public static function getContextShopID()
    {
        return self::$contextShopId;
    }

    public static function addTableAssociation($table, $params)
    {
    }
}

class Tax
{
    public static $excludeTaxOption = false;

    public static function excludeTaxeOption()
    {
        return self::$excludeTaxOption;
    }
}

class Cache
{
    /** @var array<string, mixed> */
    public static $store = [];

    public static function isStored($key)
    {
        return array_key_exists($key, self::$store);
    }

    public static function retrieve($key)
    {
        return self::$store[$key];
    }

    public static function store($key, $value)
    {
        self::$store[$key] = $value;
    }
}

class Db
{
    /** @var callable|null fn(string, string): mixed */
    public static $handler;

    /** @var string[] every "method: sql" received */
    public static $log = [];

    public static function getInstance()
    {
        return new self();
    }

    public function __call($method, $args)
    {
        self::$log[] = $method . ': ' . ($args[0] ?? '');

        return self::$handler ? call_user_func(self::$handler, $method, $args[0] ?? '') : false;
    }
}

class DbQuery
{
    private $parts = ['select' => [], 'from' => '', 'join' => [], 'where' => [], 'order' => ''];

    public function select($fields)
    {
        $this->parts['select'][] = $fields;

        return $this;
    }

    public function from($table, $alias = null)
    {
        $this->parts['from'] = '`' . _DB_PREFIX_ . $table . '`' . ($alias ? ' ' . $alias : '');

        return $this;
    }

    public function innerJoin($table, $alias = null, $on = null)
    {
        $this->parts['join'][] = 'INNER JOIN `' . _DB_PREFIX_ . $table . '` ' . $alias . ' ON (' . $on . ')';

        return $this;
    }

    public function where($restriction)
    {
        $this->parts['where'][] = $restriction;

        return $this;
    }

    public function orderBy($fields)
    {
        $this->parts['order'] = $fields;

        return $this;
    }

    public function build()
    {
        return 'SELECT ' . implode(', ', $this->parts['select']) . ' FROM ' . $this->parts['from']
            . ' ' . implode(' ', $this->parts['join'])
            . ' WHERE ' . implode(' AND ', $this->parts['where'])
            . ' ORDER BY ' . $this->parts['order'];
    }
}

class Context
{
    public $customer;
    public $shop;
    public $currency;
    public $country;

    private static $instance;

    public static function getContext()
    {
        if (!self::$instance) {
            self::$instance = new self();
            self::$instance->shop = (object) ['id' => 1];
        }

        return self::$instance;
    }

    public static function reset()
    {
        self::$instance = null;
    }

    public function getComputingPrecision()
    {
        return 2;
    }
}

class Validate
{
    public static function isLoadedObject($object)
    {
        return is_object($object) && !empty($object->id);
    }

    public static function isPrice($value)
    {
        return (bool) preg_match('/^[0-9]{1,10}(\.[0-9]{1,9})?$/', (string) $value);
    }

    public static function isPercentage($value)
    {
        return is_numeric($value) && $value >= 0 && $value <= 100;
    }

    public static function isPostCode($value)
    {
        return (bool) preg_match('/^[a-zA-Z 0-9-]+$/', (string) $value);
    }
}

class Customer
{
    /** @var int[] ids of the customers that exist */
    public static $existing = [];

    /** @var int[] groups of the customer in session */
    public static $groups = [3];

    public $id;

    public function __construct($id = null)
    {
        $this->id = in_array((int) $id, self::$existing, true) ? (int) $id : null;
    }

    public function getGroups()
    {
        return self::$groups;
    }
}

class Currency
{
    /** @var array<int, float> id_currency => conversion rate */
    public static $rates = [1 => 1.0];
    public static $default = 1;

    public $id;
    public $conversion_rate;

    public function __construct($id)
    {
        $this->id = $id;
        $this->conversion_rate = self::$rates[$id] ?? 1.0;
    }

    public static function getCurrencyInstance($id)
    {
        return new self((int) $id);
    }

    public static function getDefaultCurrency()
    {
        return new self(self::$default);
    }
}

class Carrier
{
    /** @var int[] id_carrier => id_reference (defaults to the id) */
    public static $references = [];

    /** @var int[] ids of the carriers that do not exist */
    public static $missing = [];

    /** @var float */
    public static $taxRate = 20.0;

    public $id;
    public $id_reference;

    public function __construct($id = null)
    {
        $this->id = in_array((int) $id, self::$missing, true) ? null : (int) $id;
        $this->id_reference = self::$references[(int) $id] ?? (int) $id;
    }

    public function getTaxesRate($address = null)
    {
        return self::$taxRate;
    }
}

class Address
{
    /** @var array<int, array{id_country: int, zone: int, postcode: string}> */
    public static $list = [];

    public $id;
    public $id_country;
    public $postcode;

    public static function addressExists($id)
    {
        return isset(self::$list[(int) $id]);
    }

    public static function initialize($id = null, $with_geoloc = false)
    {
        $address = new self();
        $address->id = (int) $id;
        $address->id_country = self::$list[(int) $id]['id_country'] ?? 0;
        $address->postcode = self::$list[(int) $id]['postcode'] ?? '';

        return $address;
    }

    public static function getZoneById($id)
    {
        return self::$list[(int) $id]['zone'] ?? 0;
    }
}

class Product
{
    /** @var array<int, int[]> id_product => category ids */
    public static $categories = [];

    public static function getProductCategories($id_product = '')
    {
        return self::$categories[(int) $id_product] ?? [];
    }
}

class Cart
{
    public const ONLY_PRODUCTS = 1;

    /** @var int[] cart rules applied to the cart (cart_cart_rule) */
    public static $cartRuleIds = [];

    public $id = 1;

    public $id_currency = 1;
    public $id_address_invoice = 0;
    public $id_address_delivery = 0;

    public $virtual = false;
    public $products = [];
    public $totalExcl = 100.0;
    public $totalIncl = 120.0;
    public $weight = 1.0;
    public $averageTaxRate = 0.1;

    public function isVirtualCart()
    {
        return $this->virtual;
    }

    public function getProducts()
    {
        return $this->products;
    }

    public function getOrderTotal($withTaxes = true, $type = 3, $products = null)
    {
        return $withTaxes ? $this->totalIncl : $this->totalExcl;
    }

    public function getTotalWeight($products = null)
    {
        return $this->weight;
    }

    public function getAverageProductsTaxRate()
    {
        return $this->averageTaxRate;
    }
}

function pSQL($string, $htmlOk = false)
{
    return addslashes((string) $string);
}
